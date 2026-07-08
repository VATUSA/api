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
erroring — it's being skipped before it ever starts, almost always because of a stuck
`->onOneServer()` and/or `->withoutOverlapping()` mutex lock in Redis (`CACHE_DRIVER=redis`,
see pod env `REDIS_HOST`/`REDIS_PORT`). These locks are meant to auto-release right after a
run finishes, but if the background process is killed/OOMed/crashes before the `finally`
release runs, the lock persists in Redis for its full TTL (default 1440 minutes / 24h) and
silently blocks every subsequent due-time from firing — with zero log output, since the skip
happens before the `before()` hook.

To check:

```sh
kubectl logs -n current deploy/api-worker --since=6h | grep -i "scheduled task"
# count occurrences per command — a command due every 5 min should show ~12 hits in 1h;
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

A large positive TTL (hours, out of the 24h max) on a command whose expected run time is
seconds means the lock is stuck. Fix by deleting that Redis key (`$r->del($key)` via the same
tinker approach, or `Cache::forget()`), which lets the next due tick acquire the lock and run
normally. This has been observed to repeat across `api-worker` pod restarts — if it recurs,
suspect the underlying command (`moodle:competency`, `moodle:sync`) is crashing or hanging
mid-run rather than the lock mechanism itself.

### The Academy/Moodle → controller eligibility pipeline

Relevant when a controller's "Needs to complete the Basic ATC/S1 courses or RCE Exam?" flag
(rendered in `current`'s `resources/views/mgt/controller/parts/summary.blade.php`, computed
in `current/app/Models/User.php::transferEligible()` from `checks['needbasic']`) doesn't
clear after training reports a passed exam:

- The value is read from `App\Models\ControllerEligibilityCache` (table
  `controller_eligibility_cache`), specifically `competency_rating` / `competency_date`.
- `api`'s `moodle:competency` command (`app/Console/Commands/MoodleCompetency.php`, every 5
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
