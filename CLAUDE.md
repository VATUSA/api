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
