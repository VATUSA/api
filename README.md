# VATUSA API

The backend API for the [VATUSA](https://www.vatusa.net) (VATSIM US Division) website,
served at **api.vatusa.net**. It runs in parallel with the main site
([`vatusa/current`](https://github.com/VATUSA/current)) and is the legacy/current API that
the newer Go (`cobalt`) and Rust (`mithril`) backends are intended to extend and eventually
replace.

> **Status:** Legacy. This is a long-lived Laravel application in maintenance mode. Prefer
> making new functionality in the newer backends where possible; changes here should be
> targeted and well-scoped.

---

## Tech stack

| Area | Technology |
|------|-----------|
| Language / runtime | PHP 8.1+ |
| Framework | Laravel 10 |
| Database | MySQL (via PDO). Multiple connections: `mysql` (primary), plus optional `forum`, `email`, `moodle` |
| Cache / queue | Redis (`predis`) |
| Auth | JWT (`tymon/jwt-auth`), OAuth2 (VATSIM Connect via `league/oauth2-client`), API keys, plus `web-token/jwt-*` for RFC7519 |
| API docs | OpenAPI / Swagger UI (`darkaonline/l5-swagger`, `zircote/swagger-php`) |
| HTML sanitizing | `mews/purifier` |
| Email | SMTP / AWS SES |
| File storage | AWS S3 (`aws/aws-sdk-php-laravel`) |
| Academy | Moodle REST (`llagerlof/moodlerest`) |
| Error tracking | Sentry (`sentry/sentry-laravel`) |
| Tests | PHPUnit 10 |
| Container | `php:8.1-fpm-alpine` + nginx + supervisord |
| CI/CD | GitHub Actions → Docker Hub → ArgoCD/Kustomize (see [`gitops`](https://github.com/VATUSA/gitops)) |

---

## Repository layout

```
app/
  *.php                 Eloquent models live at the TOP LEVEL (User.php, Facility.php, …)
                        — this is the old Laravel 5 convention, not app/Models/.
  Http/Controllers/     Controllers, mostly under API/v2/
  Http/Middleware/      Auth/CORS tiers (see "Authentication" below)
  Jobs/, Mail/          Queued work and mailers
  Classes/, Helpers/    app/Helpers/helpers.php is globally autoloaded
  Console/              Artisan commands
config/                 Standard Laravel config (note the extra DB connections + sso/oauth)
database/migrations/    66 migrations; database/seeds/ for seeders
routes/
  api.php               Entry point; wires up v2 routing + middleware
  api-v2.php            The bulk of the API surface
  login.php             VATSIM SSO login flow
  web.php, channels.php, console.php
resources/
  views/emails/         Blade email templates
  docker/               nginx / php-fpm / supervisord config baked into the prod image
tests/                  PHPUnit (Unit + Feature)
```

---

## Authentication & middleware tiers

Auth is multi-layered. Routes are grouped by middleware that enforces different access:

- **`auth:jwt,web`** — session authentication only; **no** CORS checks.
- **`private`** — internal calls only; includes CORS checks.
- **`semiprivate`** — tries both session auth and API key.
- **`public`** — readability marker only, being phased out.
- **`APIKey`** — legacy v1; checks the API key in the URL path.
- **`apikeyv2`** — validates an `apikey` query/body parameter (v2).

See `app/Http/Kernel.php` for how these map to middleware classes.

---

## Local development

You'll need either Docker (recommended — no local PHP required) or a local PHP 8.1+
toolchain with Composer.

### Option A — Docker (recommended)

Brings up MySQL + Redis + the API together. Source is bind-mounted, so edits are live.

```sh
docker compose -f compose.dev.yml up --build
```

The first time (and after adding migrations), run them in another terminal:

```sh
docker compose -f compose.dev.yml exec app php artisan migrate
# optional sample data:
docker compose -f compose.dev.yml exec app php artisan db:seed
```

The API is then at **http://localhost:5002** (Swagger docs at `/docs`).

> Many endpoints require real VATSIM SSO / API credentials to do anything useful — fill in
> the relevant values in `.env` (created automatically from `.env.example` on first boot).

### Option B — Native PHP

```sh
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret          # if using HMAC JWTs

# point .env at a MySQL + Redis you control, then:
php artisan migrate
php artisan serve               # http://localhost:8000
```

### Useful commands

```sh
php artisan migrate                  # run DB migrations
php artisan db:seed                  # seed data
php artisan l5-swagger:generate      # regenerate OpenAPI docs
php artisan tinker                   # REPL
./vendor/bin/phpunit                 # run tests
```

---

## Testing

Tests use PHPUnit 10. The committed suite is currently a small set of **smoke tests** that
verify the framework boots and helpers behave — they run against in-memory SQLite and need
no external services:

```sh
./vendor/bin/phpunit
```

Full DB-backed feature testing is **not yet wired up**: the app targets MySQL and relies on
several connections (`forum`, `email`, `moodle`), so it can't simply run against SQLite.
Building that out (a MySQL service + a trimmed test schema/factories) is the main thing
needed to make integration tests possible.

---

## Code style

Style is checked with [Laravel Pint](https://laravel.com/docs/pint) (config in `pint.json`,
`laravel` preset). Install it once and run:

```sh
composer global require laravel/pint     # one-time
pint            # fix style in place
pint --test     # check only
```

The codebase is **not yet fully Pint-clean**, so there is no CI style job yet. To add an
enforced one later: run `pint`, commit the result, then add a `pint --test` job to
`.github/workflows/ci.yml`.

---

## Continuous integration

Two GitHub Actions workflows:

- **`.github/workflows/ci.yml` (PR Checks)** — runs on every pull request (and pushes to
  `master`/`dev`): PHP syntax lint, `composer validate`, and the PHPUnit smoke tests. This
  validates changes without needing a full database.
- **`.github/workflows/ci-master.yml` (CI to Docker Hub)** — runs on pushes to
  `master`/`dev` only: builds the production Docker image, pushes it to Docker Hub tagged
  with the commit SHA, then bumps the image tag in the `gitops` repo for ArgoCD to deploy.

---

## Deployment

- `Dockerfile` builds the production image (`php-fpm` + nginx + supervisord). Config lives
  under `resources/docker/`.
- `build.sh` is the container entrypoint: sets up `storage/logs` and, on
  `prod`/`livedev`/`staging`, runs `php artisan migrate` and fixes purifier permissions
  before starting `supervisord`.
- `deploy.sh` is a legacy server-side `git pull` + `composer install`.
- Cluster deployment (Kubernetes/ArgoCD manifests) lives in the
  [`gitops`](https://github.com/VATUSA/gitops) repo, not here.

---

## Known issues / cleanup backlog

These are worth addressing as the codebase is maintained:

- **`composer.json` rough edges**: `"minimum-stability": "dev"` is risky; `mockery/mockery`
  is pinned to the ancient `0.9.*` (predates PHPUnit 10) and should be upgraded or removed;
  `doctrine/dbal ^2.5` and `guzzlehttp/psr7 ^1.5` are old major versions.
- **No real test coverage**: only smoke tests exist (see [Testing](#testing)).
- **Secure randomness**: `randomPassword()` in `app/Helpers/helpers.php` uses `rand()`,
  which is not cryptographically secure — prefer `random_int()` / `Str::random()`.
- **Commented-out middleware**: CSRF (`VerifyCsrfToken`) and `EncryptCookies` are disabled
  in `app/Http/Kernel.php`; confirm this is intentional for an API.
- **Broken `web.php` route**: `/` returns `view('welcome')`, but no `welcome` view exists.
- **Stale dependency PRs**: several open Dependabot branches; review and merge or close.
