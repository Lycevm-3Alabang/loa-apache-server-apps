# Local Development Runbook — LOA Consult Platform

## Overview

The Consult Platform joins the **shared root stack** — it does NOT get its own MySQL, Mailpit, or Seq. Canonical launcher (per `docs/local-dev-multi-app-spec.md`):

- **Compose file**: `docker-compose.yml` (repo root, `loa-platform` project)
- **Shared services**: `mysql` (MySQL 8, all app DBs), `mailpit`, `seq`, network `loa`
- **Consult database**: `loa_consult` (own DB on shared MySQL — separate databases, shared server)

> Do NOT copy `assemblies/loa-cert-platform/docker-compose.yml` (standalone pattern with its own MySQL). That file predates the multi-app spec and exists for isolated cert work only.

### Planned services (added when testable code exists — see §2)

| Service | Purpose | Ports (host:container) |
|---------|---------|------------------------|
| `consult-app` | PHP 8.3 application container | — (exposed via nginx) |
| `consult-nginx` | Web server / reverse proxy | `9002:80` |
| `consult-scheduler` | Laravel scheduled task runner | — |
| `mysql` (shared) | MySQL 8.0, hosts `loa_auth` + `loa_cert` + `loa_consult` | `33060:3306` |
| `mailpit` (shared) | Email capture | `8025:8025`, `1025:1025` |
| `seq` (shared) | Centralized logs | `5341:80` (UI), ingestion `seq:5341` |

Port plan avoids collisions: auth `8080`, cert `9001`, consult **`9002`**.

---

## 1. Current Status (2026-09-24 — WIRED + GREEN)

Assembly built (13 controllers, migrations 000001–000014, 104+5 flat routes) and root-stack wired (`consult-app`/`consult-nginx`/`consult-scheduler`, `:9002`). `init.sql` carries `loa_consult` + `loa_consult_test`. Pipelines green (`reset-all`/`run-tests`/`mega.ps1` end-to-end 2026-09-23; full suite 120 passed). Tenant `loa-consultation` (own-tenant cert pattern). §2 gate MET 2026-09-23 — kept below as record.

## 2. Wiring Gate (do not skip)

Add compose wiring **only** when ALL of these exist and pass:

Gate MET 2026-09-23 (record — all boxes were checked at wiring time):

- [x] Minimal Laravel 12 skeleton (`artisan`, `bootstrap/app.php`, `routes/api.php`, `public/index.php` + `.htaccess` Authorization rule, `.env`/`.env.example`, `composer.json`) per AI-GUIDE scaffolding checklist
- [x] `GET /api/v1/health` live (public)
- [x] First migration + passing phpunit test via `docker compose exec consult-app php artisan test`
- [x] `docker/mysql/init.sql` extended with `loa_consult` DB + grant (see §3)

Wiring checklist (done 2026-09-23):

- [x] `consult-app` block in root `docker-compose.yml` (build context `./assemblies/loa-consult-platform`, `DB_DATABASE: loa_consult`, `SEQ_URL: http://seq:5341`, same `loa` network)
- [x] `consult-nginx` block (port `9002:80`, assembly bind-mount + nginx conf)
- [x] `consult-scheduler` block (`schedule:work`)
- [x] `init.sql` creates `loa_consult` + `loa_consult_test` + grants (fresh volumes pick it up; existing volumes need manual `CREATE DATABASE`)
- [x] `docker compose up -d --build consult-app consult-nginx consult-scheduler` starts clean
- [x] Migrate + test green inside the container (§4; NO seed — consult ships no seeders per `docker-compose-spec.md` §7)

## 3. MySQL Sharing

One server, separate databases (multi-app spec: shared infrastructure, NOT shared database):

```sql
-- docker/mysql/init.sql (landed 2026-09-23; fresh volumes pick it up automatically):
CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%';
CREATE DATABASE IF NOT EXISTS loa_consult_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult_test.* TO 'loa'@'%';
FLUSH PRIVILEGES;
```

- Connection from consult: `DB_HOST=mysql`, `DB_PORT=3306`, `DB_DATABASE=loa_consult`, `DB_USERNAME=loa`, `DB_PASSWORD=loa-secret`.
- Each app's migrations touch only its own database. Never cross-database joins (spec §3.3).
- Existing `mysql_data` volumes do NOT re-run `init.sql` — after adding the lines, either `docker compose down -v` (wipes all local data) or create the DB manually.

## 4. Common Tasks (once wired)

> Per `AGENTS.md`: the AI agent never runs `docker compose` — the USER runs every command below from the repo root. Run test suites sequentially (never overlapping runs — concurrent suites deadlock the shared test DB).

From the repo root (`loa-apache-server-apps/`):

```bash
# Start consult only (shared infra comes up as dependency)
docker compose up -d --build consult-app consult-nginx consult-scheduler

# Migrate / test inside the container (NO seed — consult ships no seeders per `docker-compose-spec.md` §7)
docker compose exec consult-app php artisan migrate --force
docker compose exec consult-app php artisan test

# Single test file / method
docker compose exec consult-app php artisan test tests/Feature/Api/HealthTest.php
docker compose exec consult-app php artisan test --filter testName

# Swagger docs (best-effort: app/ carries no OpenApi attributes, so generation warns + continues per `reset-all.ps1`)
docker compose exec consult-app php artisan l5-swagger:generate

# Shell / logs
docker compose run --rm consult-app bash
docker compose logs -f consult-app
```

Verify: API `http://localhost:9002` · Swagger `http://localhost:9002/api/docs` · Mailpit `http://localhost:8025` · Seq `http://localhost:5341`.

## 5. Test Database

Dedicated `loa_consult_test` (never the app database). `tests/bootstrap.php` pins all test-critical env (phpunit `force` loses to container `$_SERVER` — same precedent as auth) including `TENANT_SLUG=loa-consultation`. Suites run sequentially only (shared test DB deadlocks on concurrent runs). Contract: `test-suite.md` Final v1.2.

## 6. Troubleshooting

- **Port collisions** — `9002`/`33060`/`5341` taken → another stack is up (`docker compose down` the other, or root vs assembly-local cert compose clashing on `33060`/`5341`; prefer the root stack).
- **MySQL not healthy** — `docker compose ps mysql`, `docker compose logs mysql`; migrations wait on `service_healthy`.
- **`.env` quoting** — values with spaces must be quoted (AI-GUIDE gotcha); compose `environment:` overrides `.env`, but unset keys fall back to it.
- **Missing `artisan` / broken bootstrap** — AI-GUIDE scaffolding checklist: `artisan`, `public/index.php`, no `providers` array in `bootstrap/app.php` or `config/app.php`, `routes/api.php` present.
- **401 on gated endpoints locally** — nginx (not Apache) passes `Authorization` fine; check `JWT_SECRET` parity with Auth instead.

---

## Related Resources

- [Root compose](../../docker-compose.yml) (canonical launcher — consult blocks land here, not in a local compose file)
- [Multi-app spec](../../docs/local-dev-multi-app-spec.md) (shared-infra rules + acceptance criteria)
- [MySQL init](../../docker/mysql/init.sql) (`loa_consult` lines added at wiring time)
- [Auth runbook](../loa-auth-platform/LOCAL-DEV-RUNBOOK.md) · [Cert runbook](../loa-cert-platform/LOCAL-DEV-RUNBOOK.md)
- [API Endpoints](api-endpoints.md) (Final v2.1) · [Auth Integration](auth-integration.md) (Final v1.5) · [Data Model](data-model.md) (Final v1.3)

---

## Document Control

- **Status:** Final v1.0 (user-approved 2026-09-24; wired + green 2026-09-23)
