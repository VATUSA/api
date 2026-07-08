# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this
repository. For how this repo relates to the other VATUSA projects, see the workspace
`CLAUDE.md` one directory up.

## Project Overview

The **VATUSA API** (api.vatusa.net) — a Laravel 10 / PHP 8.1+ application. It works in
parallel with `current` and is the legacy/current API that the newer `cobalt` and
`mithril` backends are intended to extend or replace.

## Build & Development Commands

```sh
composer install        # PHP dependencies (no npm — this is API-only)

php artisan migrate     # Run DB migrations
php artisan l5-swagger:generate   # Regenerate OpenAPI/Swagger docs (darkaonline/l5-swagger)
php artisan tinker      # REPL

./vendor/bin/phpunit    # Run tests (PHPUnit 10, suite in ./tests)
```

### Local environment

Two paths (see `README.md` for full detail):

- **Docker (preferred, no local PHP needed):** `docker compose -f compose.dev.yml up --build`
  brings up MySQL + Redis + the API (`artisan serve`) at http://localhost:5002. Run
  migrations with `docker compose -f compose.dev.yml exec app php artisan migrate`. The dev
  image is `docker/dev.Dockerfile` — separate from the production root `Dockerfile`.
- **Native:** `cp .env.example .env && composer install && php artisan key:generate`, point
  `.env` at a MySQL+Redis, then `php artisan migrate` and `php artisan serve`.

`.env.example` is the authoritative, sectioned list of env vars (DB connections, JWT,
VATSIM SSO/OAuth, AWS, Moodle, Swagger).

### Testing & CI

- Tests are **smoke tests only** right now (in-memory SQLite, no external services). Real
  DB-backed feature tests aren't wired up because the app needs MySQL + the `forum`/`email`/
  `moodle` connections. `phpunit.xml` uses the PHPUnit 10 schema and sets a throwaway
  `APP_KEY` + sqlite so the suite runs with zero setup.
- **PR validation:** `.github/workflows/ci.yml` runs on `pull_request` — PHP syntax lint,
  `composer validate`, and PHPUnit. `ci-master.yml` is the separate push-only pipeline that
  builds/pushes the Docker image and deploys via gitops.
- **Code style:** Laravel Pint (`pint.json`, `laravel` preset). Install with
  `composer global require laravel/pint`; `pint --test` to check, `pint` to fix. There's no
  CI style job yet — the tree isn't Pint-clean.

## Authentication & Middleware

Auth is multi-layered (JWT via `tymon/jwt-auth` + OAuth2 + API keys). Middleware tiers,
per the README:
- `auth:jwt,web` — session authentication only; **no** CORS checks.
- `Private` — internal calls only; includes CORS checks.
- `SemiPrivate` — tries both session auth and API key.
- `Public` — readability marker only, being phased out.
- `APIKey` — legacy v1; checks API key in the URL path.

## Architecture

Laravel app under `app/`. Note that **Eloquent models live at the top level of `app/`**
(e.g. `User.php`, `Facility.php`, `Rating.php`, `Transfer.php`), not in `app/Models/`.
- `Http/` — controllers, the middleware tiers above
- `Jobs/`, `Mail/` — queued work and mailers
- `Classes/`, `Helpers/` (`app/Helpers/helpers.php` is autoloaded), `Console/`
- Routes split across `routes/`: `api.php`, `api-v2.php`, `login.php`, `web.php`

## Deployment

`build.sh` runs in the container: sets up `storage/logs`, and on `prod`/`livedev`/`staging`
runs migrations and fixes purifier permissions, then starts `supervisord`. `deploy.sh` does
a `git pull` + `composer install` on the server. Cluster deployment is managed in `gitops`.

GitHub reports this repo has moved to `git@github.com:VATUSA/api.git` — pushes to the old
remote URL still succeed via redirect, but update your `origin` remote to the new URL to
avoid relying on that. `master` has branch protection requiring PRs; direct pushes need
admin override.

## Scheduled jobs / Laravel scheduler

The schedule is defined in `app/Console/Kernel.php`. **It does not run via cron** — the
`build.sh` line that would install a `schedule:run` crontab entry
(`echo "* * * * * cd /www && php artisan schedule:run" >> /etc/crontabs/application`) has
been commented out since 2021. Instead, `gitops/current/base/api/worker.yaml` deploys a
dedicated `api-worker` Deployment (namespace `current`, pod `api-worker-*`) running
`php artisan schedule:work` in the foreground — that single long-lived process is what fires
every scheduled command (`moodle:sync`, `moodle:competency`, `moodle:sendexamemails`,
`vatsim:flights`, `controller:eligibility`, `stats:monthly`). The `api-queue` deployment is
unrelated (queued jobs, not the scheduler). Don't confuse the two when triaging.

### Diagnosing a scheduled job that silently stops running

Each entry in `Kernel::schedule()` wraps a `before`/`after` hook that logs
`Starting scheduled task: {name}` / `Finished scheduled task: {name}` via `logger()`. If a
job's due time comes and goes with **no** "Starting scheduled task" line for it, it isn't
erroring — it's being skipped before it ever starts, because of a `->onOneServer()` and/or
`->withoutOverlapping()` mutex lock in Redis (`CACHE_DRIVER=redis`, see pod env
`REDIS_HOST`/`REDIS_PORT`) that's currently held.

To check:

```sh
kubectl logs -n current deploy/api-worker --since=6h | grep -i "scheduled task"
# count occurrences per command — a command due every 10 min should show ~6 hits in 1h;
# zero hits for a job with a due schedule means it's being skipped, not failing

kubectl exec -n current deploy/api-worker -- sh -c 'cd /www && php artisan schedule:list'
# "Has Mutex" marks commands using onOneServer/withoutOverlapping

# Compute each event's actual mutex key + check its Redis TTL:
kubectl exec -n current deploy/api-worker -- sh -c 'cd /www && php artisan tinker --execute="
\$schedule = app(Illuminate\Console\Scheduling\Schedule::class);
\$r = \Illuminate\Support\Facades\Cache::getRedis();
foreach (\$schedule->events() as \$e) {
  \$key = \"laravel:\" . \$e->mutexName();
  echo \$e->command . \" => exists=\" . (\$r->exists(\$key)?1:0) . \" ttl=\" . \$r->ttl(\$key) . PHP_EOL;
}
"'
```

**Before assuming a held lock means "stuck," check for a live process** — a large TTL has
two very different causes and they need different responses:

```sh
kubectl exec -n current deploy/api-worker -- sh -c 'ps -o pid,etime,time,args | grep -i <command>'
```

- **A live process is running** (visible in `ps`, with elapsed time > CPU time, i.e.
  I/O-bound waiting on Moodle's API) — the job is just genuinely slow, not broken. Do
  nothing; it will finish and release its own lock via the `schedule:finish` callback (see
  `vendor/laravel/framework/.../CommandBuilder.php::buildBackgroundCommand()` — background
  events are wrapped as `(cmd ; php artisan schedule:finish "<mutex>" "$?") &`, so the mutex
  releases on exit regardless of success/failure — this part of Laravel isn't the bug).
  `moodle:competency` in particular does fully sequential, unbatched HTTP calls to Moodle
  per controller × per rating × per course with no concurrency or visible timeout
  (`app/Console/Commands/MoodleCompetency.php::checkControllerCompetency()`), so a single run
  can legitimately take longer than its own schedule interval under load.
- **No live process, but the lock is still held** — this is a genuine orphan: the wrapping
  subshell (and the command inside it) was killed outright, most likely by an `api-worker`
  pod restart/OOM/rolling deploy mid-run, before it ever reached the `schedule:finish` call
  that would have released the lock. This will not self-heal until the mutex's `expiresAt`
  TTL naturally runs out. Force-release it immediately rather than waiting:

  ```sh
  kubectl exec -n current deploy/api-worker -- sh -c 'cd /www && php artisan tinker --execute="
  \$key = \"framework/schedule-<mutex-hash>\"; // from schedule:list / mutexName() above, no \"laravel:\" prefix
  \Illuminate\Support\Facades\Cache::store(null)->getStore()->lock(\$key, 86400)->forceRelease();
  "'
  ```

  Plain `Cache::forget()` / `DEL` on the raw key usually also works since Redis's lock is
  just a keyed value under the hood, but `forceRelease()` is what Laravel's own
  `CacheEventMutex::forget()` calls and is the safest match.

As of 2026-07-08 (commit `a324474`), `moodle:sync` and `moodle:competency` both pass an
explicit `->withoutOverlapping($minutes)` argument (120 and 60, respectively) instead of
relying on the 1440-minute (24h) default — this was after `moodle:sync`'s lock was found
orphaned for 12+ hours following a pod restart with nothing live to release it. **Don't
"clean up" those explicit arguments back to the bare default** — they exist specifically so
a future orphaned lock self-heals in ~1-2h instead of a full day. `moodle:competency` was
also slowed from every 5 to every 10 minutes to reduce how often it collides with and skips
its own still-running previous invocation.

### The Academy/Moodle → controller eligibility pipeline

Relevant when a controller's "Needs to complete the Basic ATC/S1 courses or RCE Exam?" flag
(rendered in `current`'s `resources/views/mgt/controller/parts/summary.blade.php`, computed
in `current/app/Models/User.php::transferEligible()` from `checks['needbasic']`) doesn't
clear after training reports a passed exam:

- The value is read from `App\Models\ControllerEligibilityCache` (table
  `controller_eligibility_cache`), specifically `competency_rating` / `competency_date`.
- `api`'s `moodle:competency` command (`app/Console/Commands/MoodleCompetency.php`, every 10
  min) polls Moodle quiz attempts and, on a pass, upserts `academy_competency` and
  conditionally bumps `controller_eligibility_cache` (only if a cache row already exists for
  the cid, and only if the new completion date is newer than what's cached).
- `api`'s `controller:eligibility` command (`app/Console/Commands/CacheControllerEligibility.php`,
  hourly) creates/rebuilds `controller_eligibility_cache` rows and can independently
  recompute `competency_rating`/`competency_date` from transfers/promotions/facility
  presence — this can overwrite a Moodle-derived value.
- There is **no on-demand sync** — it's purely these two cron-driven commands. If either is
  silently skipped (see mutex-lock section above), or a cache row doesn't exist yet for a
  newly-home controller, the page will show stale data indefinitely with no error anywhere.
- Note: `controllers.flag_needbasic` (set in `SSOController.php` and
  `SendAcademyRatingExamEmails.php`) is a **different, legacy** flag used at SSO
  login/onboarding time — it does not feed the mgt page's `needbasic` check. Don't confuse
  the two when triaging.
