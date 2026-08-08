# Local Development Runbook — LOA Cert Platform

## Overview

The Cert Platform has a **standalone** Docker Compose setup for isolated development. A **shared Seq logging service** is included for centralized log inspection.

- **Compose file**: `docker-compose.yml` (in `assemblies/loa-cert-platform/`)
- **Project name**: `loa-cert`
- **Default DB**: `loa_cert` (database auto-initialized by MySQL on first boot)

### Services

| Service        | Purpose                              | Ports (host:container)         |
|----------------|--------------------------------------|----------------------------------|
| `cert-app`     | PHP 8.3 application container         | — (exposed via nginx)            |
| `nginx`        | Web server / reverse proxy           | `9001:80`                        |
| `mysql`        | MySQL 8.0 database                    | `33060:3306`                     |
| `scheduler`    | Laravel scheduled task runner         | —                                |
| `mailpit`      | Email capture for testing             | `8025:8025`, `1026:1025`          |
| `seq`          | Centralized log server                | `5341:5341`                      |

---

## Quick Start (First-Time / Clean)

From this directory (`assemblies/loa-cert-platform`):

```bash
# Ensure a clean slate (removes old volumes)
docker compose down -v

# Build and start all services
docker compose up -d --build

# Wait for MySQL healthcheck (up to 2 minutes)
docker compose ps

# Install dependencies (if vendor/ not present)
docker compose run --rm cert-app composer install --no-interaction --no-progress

# Run migrations
docker compose run --rm cert-app php artisan migrate --force

# Seed the database
docker compose run --rm cert-app php artisan db:seed --force

# Generate Swagger documentation
docker compose run --rm cert-app php artisan l5-swagger:generate
```

### Verify It's Working

- API server: `http://localhost:9001`
- Swagger UI: `http://localhost:9001/api/docs`
- Mailpit UI: `http://localhost:8025`
- Seq log server: `http://localhost:5341`

---

## Dependency Chain

| Component         | Provided By                           | Notes                                                                 |
|-------------------|--------------------------------------|-----------------------------------------------------------------------|
| PHP runtime       | Docker image (see `docker/php/Dockerfile`) | PHP 8.2 — no system PHP needed on host                              |
| Composer          | Run via `docker compose run`         | No global composer required                                           |
| Laravel Artisan   | Pre-installed in container           | Use `docker compose run --rm cert-app php artisan <command>`          |
| PHPUnit           | Via Laravel's framework              | Run tests with `docker compose run --rm cert-app php artisan test`     |
| l5-swagger        | Vendored in `vendor/`                | Generate docs with `php artisan l5-swagger:generate` (see below)       |
| DOMPDF            | Vendored in `vendor/`                | Used by PdfService for certificate rendering                          |

> **Note**: `vendor/` is committed in the repository, so `composer install` is optional unless dependencies change.

---

## Logging & Observability

Centralized logging is provided by **Seq**, running as a service in this compose stack.

### How It Works

1. Seq runs on container port `5341` (exposed on host at `5341`).
2. Laravel's `config/logging.php` is configured to send logs to Seq via UDP syslog handler when the `SEQ_URL` environment variable is set.
3. The `cert-app` container receives `SEQ_URL=http://seq:5341` from the compose environment.

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
docker compose run --rm cert-app php artisan migrate --force
```

### Rollback All Migrations

```bash
docker compose run --rm cert-app php artisan migrate:reset --force
```

### Seed Database

```bash
docker compose run --rm cert-app php artisan db:seed --force
```

### Run All Tests

```bash
docker compose run --rm cert-app php artisan test
```

### Run a Specific Test File

```bash
docker compose run --rm cert-app php artisan test tests/Feature/Api/CertificateTest.php
```

### Run a Specific Test Method

```bash
docker compose run --rm cert-app php artisan test --filter testMethodName
```

### Generate Swagger Documentation

```bash
docker compose run --rm cert-app php artisan l5-swagger:generate
```

> Run this after adding or modifying OpenAPI annotations in controllers.

### Open a Shell in the App Container

```bash
docker compose run --rm cert-app bash
```

### Tail Logs (stdout/stderr)

```bash
docker compose logs -f cert-app
```

---

## Local Environment Notes

- The database volume (`mysql_data`) persists across restarts. Use `docker compose down -v` to wipe it.
- `APP_ENV=local` is set in the compose environment — debug mode is enabled.
- For email testing, Mailpit is available at `http://localhost:8025`.
- The `created_by` field exists in migrations but author-scoping is handled by the `jwt.endpoint` middleware later (deferred per decision #20). Controllers just accept/save the field from request attributes if present.

---

## Troubleshooting

### Port 33060 or 5341 Already Allocated

Another stack (likely the **root-level** compose file) may already be using these ports. Either:
- Stop the conflicting stack: `cd .. && docker compose down`
- Or run this stack with remapped ports (not recommended for local dev).

### Migrations Fail

1. Verify MySQL is healthy: `docker compose ps mysql`
2. Check MySQL logs: `docker compose logs mysql`
3. Ensure migrations have run: `docker compose run --rm cert-app php artisan migrate:status`

### Seq Not Receiving Logs

1. Confirm `seq` service is running: `docker compose ps seq`
2. Check `SEQ_URL` is set in `cert-app` environment: `docker compose exec cert-app env | grep SEQ`
3. Verify `config/logging.php` has the `seq` channel configured.

### Missing `artisan` File

If artisan commands fail, ensure the `artisan` file exists in `cert-app/`. See [Root LOCAL-DEV-RUNBOOK](LOCAL-DEV-RUNBOOK.md) for the bootstrap content.

### `.env` Parsing Errors

All values with spaces must be quoted:

```env
# Wrong
APP_NAME=LOA Cert Platform

# Correct
APP_NAME="LOA Cert Platform"
```

---

## Related Resources

- [Root Local Dev Runbook](../../LOCAL-DEV-RUNBOOK.md)
- [Auth Platform Runbook](../loa-auth-platform/LOCAL-DEV-RUNBOOK.md)
- [API Endpoints Spec](api-endpoints.md)

## Centralized Seq Logging

The root-level Docker Compose includes a Seq service (`datalust/seq`) on port **5341**. The Cert app container sends structured logs to Seq when the `SEQ_URL` environment variable is set (configured in the root `docker-compose.yml` environment block).

### Seq Log Format

- **Channel**: `seq` (configured in `cert-app/config/logging.php`)
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

### Verifying Logs Reach Seq

After starting services:

1. Visit `http://localhost:5341`
2. Look for recent log entries from the `cert-app` channel
3. Check that certificate issuance, attendee import, and PDF generation operations produce structured log entries
4. Confirm the `APP_DEBUG` flag enables debug-level logging

### Notes

- If Seq shows no logs, verify `SEQ_URL=http://seq:5341` is set in the `cert-app` service environment.
- The `cert-app/config/logging.php` uses Monolog's `SyslogUdpHandler` — no extra Composer packages are required.
- Logs are also written to `cert-app/storage/logs/laravel.log` on disk (file channel) as a fallback.
