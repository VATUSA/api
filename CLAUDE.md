# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this
repository. For how this repo relates to the other VATUSA projects, see the workspace
`CLAUDE.md` one directory up.

## Project Overview

The **VATUSA API** (api.vatusa.net) — a Laravel 10 / PHP 8.1+ application. It works in
parallel with `vatusa/current` and is the legacy/current API that the newer `cobalt` and
`mithril` backends are intended to extend or replace.

## Build & Development Commands

```sh
composer install        # PHP dependencies (no npm — this is API-only)

php artisan migrate     # Run DB migrations
php artisan l5-swagger:generate   # Regenerate OpenAPI/Swagger docs (darkaonline/l5-swagger)
php artisan tinker      # REPL

./vendor/bin/phpunit    # Run tests (PHPUnit 10, suite in ./tests)
```

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
