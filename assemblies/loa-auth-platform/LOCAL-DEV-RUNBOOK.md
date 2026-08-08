# Local Development Runbook — LOA Auth Platform

## Overview

The Auth Platform has a **standalone** Docker Compose setup for isolated development. A **shared Seq logging service** is included for centralized log inspection.

- **Compose file**: `docker-compose.yml` (in `assemblies/loa-auth-platform/`)
- **Project name**: `loa-auth`
- **Default DB**: `loa_auth` (database auto-initialized by MySQL on first boot)

> **Note**: For multi-app development (testing Auth + Cert together), use the root-level `docker-compose.yml` which shares a single MySQL instance.

### Services

| Service        | Purpose                              | Ports (host:container)         |
|----------------|--------------------------------------|----------------------------------|
| `app`          | PHP 8.3 application container         | — (exposed via nginx)            |
| `nginx`        | Web server / reverse proxy           | `8080:80`                        |
| `mysql`        | MySQL 8.0 database                    | `33060:3306`                     |
| `scheduler`    | Laravel scheduled task runner         | —                                |
| `mailpit`      | Email capture for testing             | `8026:8025`, `1026:1025`          |
| `seq`          | Centralized log server                | `5341:5341`                      |

---

## Quick Start (First-Time / Clean)

From this directory (`assemblies/loa-auth-platform`):

```bash
# Ensure a clean slate (removes old volumes)
docker compose down -v

# Build and start all services
docker compose up -d --build

# Wait for MySQL healthcheck (up to 2 minutes)
docker compose ps

# Install dependencies (only needed if vendor/ not present)
docker compose run --rm app composer install --no-interaction --no-progress

# Run migrations
docker compose run --rm app php artisan migrate --force

# Run database seeder
docker compose run --rm app php artisan db:seed --force

# Generate Swagger documentation
docker compose run --rm app php artisan l5-swagger:generate

# Generate application key (if APP_KEY not set in .env)
docker compose run --rm app php artisan key:generate --force
```

### Verify It's Working

- API server: `http://localhost:8080`
- Swagger UI: `http://localhost:8080/api/docs`
- Mailpit UI: `http://localhost:8026`
- Seq log server: `http://localhost:5341`

---

## Dependency Chain

| Component         | Provided By                           | Notes                                                                 |
|-------------------|--------------------------------------|-----------------------------------------------------------------------|
| PHP runtime       | Docker image (see `docker/php/Dockerfile`) | PHP 8.3 — no system PHP needed on host                              |
| Composer          | Run via `docker compose run`         | No global composer required                                           |
| Laravel Artisan   | Pre-installed in container           | Use `docker compose run --rm app php artisan <command>`               |
| PHPUnit           | Vendored in `vendor/bin/phpunit`     | Run tests with `docker compose run --rm app php artisan test`          |
| l5-swagger        | Vendored in `vendor/`                | Generate docs with `php artisan l5-swagger:generate` (see below)       |
| JWT Service       | Custom pure-PHP implementation       | No external JWT library dependency                                    |
| Sanctum           | Installed but unused                 | Legacy dependency — not used in current auth flows                    |

> **Note**: `vendor/` and `composer.lock` are committed in the repository, so `composer install` is optional unless dependencies change.

---

## Logging & Observability

Centralized logging is provided by **Seq**, running as a service in this compose stack.

### How It Works

1. Seq runs on container port `5341` (exposed on host at `5341`).
2. Laravel's `config/logging.php` is configured to send logs to Seq via UDP syslog handler when the `SEQ_URL` environment variable is set.
3. The `app` container receives `SEQ_URL=http://seq:5341` from the compose environment.

### Accessing Logs

Open your browser to:

```
http://localhost:5341
```

The Seq UI provides:
- Real-time log streaming
- Structured log query with SQL or signal syntax
- Filtering by level (Debug, Information, Warning, Error, etc.)
- Retention policy management

### Log Levels

| Level      | When Used                                    |
|------------|----------------------------------------------|
| Debug      | Detailed debug info (disabled in production) |
| Information| General operational events                   |
| Warning    | Unexpected events that don't stop execution  |
| Error      | Error exceptions and failures                |

---

## Common Tasks

### Migrate Database

```bash
docker compose run --rm app php artisan migrate --force
```

### Rollback All Migrations

```bash
docker compose run --rm app php artisan migrate:reset --force
```

### Run Database Seeder

```bash
docker compose run --rm app php artisan db:seed --force
```

> The `loa-auth-admin` group + all permissions + admin user are seeded from `.env` values. This operation is idempotent.

### Run All Tests

```bash
docker compose run --rm app php artisan test
```

### Run a Specific Test File

```bash
docker compose run --rm app php artisan test tests/Feature/Auth/LoginTest.php
```

### Run a Specific Test Method

```bash
docker compose run --rm app php artisan test --filter testLoginSuccess
```

### Generate Swagger Documentation

```bash
docker compose run --rm app php artisan l5-swagger:generate
```

> Run this after adding or modifying OpenAPI annotations in controllers.

### Open a Shell in the App Container

```bash
docker compose run --rm app bash
```

### Tail Logs (stdout/stderr)

```bash
docker compose logs -f app
```

---

## Local Environment Notes

- The database volume (`mysql_data`) persists across restarts. Use `docker compose down -v` to wipe it.
- `APP_ENV=local` is set in the compose environment — debug mode is enabled.
- The auth app serves as the shared identity provider. Its JWT secret must match the Cert and Consult apps' `JWT_SECRET` for cross-app token validation.
- For email testing, Mailpit is available at `http://localhost:8030`.

---

## Troubleshooting

### Port 33060 or 5341 Already Allocated

Another stack is already using these ports. Either:
- Stop the conflicting stack (e.g., root-level: `cd .. && docker compose down`)
- Or run this stack with remapped ports (not recommended for local dev).

### Migrations Fail

1. Verify MySQL is healthy: `docker compose ps mysql`
2. Check MySQL logs: `docker compose logs mysql`
3. Ensure migrations have run: `docker compose run --rm app php artisan migrate:status`

### Seeder Fails

1. Confirm migrations completed successfully
2. Check `.env` for required values: `APP_KEY`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
3. Inspect seeder output: `docker compose run --rm app php artisan db:seed --force -v`

### Seq Not Receiving Logs

1. Confirm `seq` service is running: `docker compose ps seq`
2. Check `SEQ_URL` is set in `app` environment: `docker compose exec app env | grep SEQ`
3. Verify `config/logging.php` has the `seq` channel configured.

### Missing `artisan` File

If artisan commands fail, ensure the `artisan` file exists at the app root. See [Root LOCAL-DEV-RUNBOOK](LOCAL-DEV-RUNBOOK.md) for the bootstrap content.

### `.env` Parsing Errors

All values with spaces must be quoted:

```env
# Wrong
APP_NAME=LOA Auth Platform

# Correct
APP_NAME="LOA Auth Platform"
```

---

## Related Resources

- [Root Local Dev Runbook](../../LOCAL-DEV-RUNBOOK.md)
- [Cert Platform Runbook](../loa-cert-platform/LOCAL-DEV-RUNBOOK.md)
- [Auth Platform Session Prompt](SESSION-PROMPT.md)

## Centralized Seq Logging

The root-level Docker Compose includes a Seq service (`datalust/seq`) on port **5341**. The Auth app container sends structured logs to Seq when the `SEQ_URL` environment variable is set (configured in the root `docker-compose.yml` environment block).

### Seq Log Format

- **Channel**: `seq` (configured in `config/logging.php`)
- **Handler**: SyslogUdpHandler
- **Facility**: USER (LOG_USER)
- **Format**: `%channel%.%level_name% %message% %context% %extra%`

### Accessing Seq

Open your browser to:

```
http://localhost:5341
```

Seq provides:

- Real-time log streaming
- Structured log query with SQL/Signal syntax
- Filtering by level (Debug, Information, Warning, Error)
- Retention policy management

### Running Standalone with Seq

When using the Auth platform's standalone `docker-compose.yml`, Seq is also included (ports `5341:5341` + `8026:8025` / `1026:1025` for Mailpit).

```bash
cd assemblies/loa-auth-platform
docker compose up -d --build
```

Logs are automatically forwarded to Seq at `http://localhost:5341`.

### Verifying Logs Reach Seq

After starting services, trigger a request then check Seq UI:

1. Visit `http://localhost:5341`
2. Look for recent entries tagged with `App` channel
3. Filter by level or message content using Seq's query bar
4. Confirm debug-level logs appear (ensure `APP_DEBUG=true` in environment)

### Notes

- If Seq shows no logs, verify `SEQ_URL=http://seq:5341` is set in the app service environment.
- The `config/logging.php` file uses Monolog's `SyslogUdpHandler` — no extra Composer packages are required.
- Logs are also written to `storage/logs/laravel.log` on disk (file channel) as a fallback.
