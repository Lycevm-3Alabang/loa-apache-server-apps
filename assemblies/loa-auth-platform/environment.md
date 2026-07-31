# LOA Auth Platform — Local & Deployed Environment
## Product Assembly — Tooling Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly
**Audience:** Developers, DevOps, AI Agents

---

# 1. Purpose

Defines the tooling required to run the LOA Auth Platform in two environments:

| Environment | Tooling | Purpose |
|-------------|---------|---------|
| Local development | Docker Compose stack | Run, test, lint, migrate, and email-capture locally |
| Production | cPanel hosting | Deploy the same code to `auth.loa.edu.ph` |

The same application code runs in both environments. Only configuration differs (env vars).

---

# 2. Local Development (Docker)

## 2.1 Stack

The local stack is defined in `docker-compose.yml` at the assembly root.

| Service | Image | Purpose |
|---------|-------|---------|
| `app` | custom `php:8.3-fpm` build (`docker/php/Dockerfile`) | PHP-FPM runtime + Composer |
| `nginx` | `nginx:1.27-alpine` | Web server, serves `public/` on port 8080 |
| `mysql` | `mysql:8.0` | Database `loa_auth` (matches production MySQL 8) |
| `scheduler` | same image as `app` | Runs `php artisan schedule:work` (daily prune job) |
| `mailpit` | `axllent/mailpit` | SMTP capture on 1025, web UI on 8025 (dev email) |

## 2.2 Ports

| Port | Service | Purpose |
|------|---------|---------|
| 8080 | nginx | Application (http://localhost:8080) |
| 33060 | mysql | MySQL host port (avoids local 3306 clashes) |
| 8025 | mailpit | Email web UI |
| 1025 | mailpit | SMTP sink |

## 2.3 Project Mount

The assembly directory is bind-mounted into every service at `/var/www/html`.

Changing files on the host is reflected immediately in the container. No rebuild required for PHP changes.

## 2.4 Common Commands

Run from the assembly root (`assemblies/loa-auth-platform/`):

```
docker compose up -d --build         # start the stack
docker compose exec app bash         # shell into the app container
docker compose exec app composer install
docker compose exec app php -l app/Services/IdentityService.php
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose logs -f app           # app logs
docker compose down                  # stop the stack (keeps DB volume)
docker compose down -v               # stop + delete DB volume (fresh start)
```

## 2.5 Database

- Name: `loa_auth`
- Credentials: defined in `docker-compose.yml` (`loa` / `loa-secret`, root password `root-secret`)
- Data persists in the `mysql_data` named volume across `down`/`up`
- `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` must match `.env` `DB_*` values

---

# 3. Deployed Environment (cPanel)

| Item | Value |
|------|-------|
| Hosting | cPanel |
| PHP | 8.3+ (native PHP, no Docker) |
| Database | MySQL 8 |
| Subdomain | auth.loa.edu.ph |
| Document root | `public/` |
| Scheduler | cron entry: `* * * * * php /home/<user>/loa-auth-platform/artisan schedule:run` |

## 3.1 Pre-Deploy Checks

1. Run `php -l` on every changed PHP file (no syntax errors)
2. Run `php artisan migrate` against the production DB
3. Configure all required env vars (see below)
4. Set `APP_ENV=production` and `APP_DEBUG=false`

---

# 4. Environment Variables

| Variable | Local (Docker) | Production (cPanel) | Required |
|----------|----------------|---------------------|----------|
| `APP_KEY` | `php artisan key:generate` | generate once | Yes |
| `APP_ENV` | `local` | `production` | Yes |
| `APP_URL` | `http://localhost:8080` | `https://auth.loa.edu.ph` | Yes |
| `DB_HOST` | `mysql` | MySQL host | Yes |
| `DB_PORT` | `3306` | `3306` | Yes |
| `DB_DATABASE` | `loa_auth` | `loa_auth` | Yes |
| `DB_USERNAME` | `loa` | cPanel DB user | Yes |
| `DB_PASSWORD` | `loa-secret` | cPanel DB password | Yes |
| `JWT_SECRET` | dev value (32+ chars) | random 32+ chars | **Yes** |
| `JWT_ACCESS_TTL` | `15` | `15` | No |
| `JWT_REFRESH_TTL` | `10080` | `10080` | No |
| `CORS_ALLOWED_ORIGINS` | LOA origins | `https://auth.loa.edu.ph,https://consult.loa.edu.ph,https://cert.loa.edu.ph` | Yes |
| `L5_SWAGGER_OPEN_API_SPEC_VERSION` | `3.1.0` | `3.1.0` | No |
| `CACHE_STORE` | `file` | `file` | Yes |
| `MAIL_MAILER` | `smtp` | `smtp` | No |
| `MAIL_HOST` | `mailpit` | SMTP host | No |
| `MAIL_PORT` | `1025` | SMTP port | No |
| `MAIL_FROM_ADDRESS` | `noreply@loa.edu.ph` | `noreply@loa.edu.ph` | Yes |

`JWT_SECRET` must be the **same value in every LOA app** (shared HMAC-SHA256 signing). It must never be committed.

`CORS_ALLOWED_ORIGINS` must be a comma-separated string of origins, not a nested array or JSON value. After changing it, run `php artisan config:clear` followed by `php artisan config:cache`.

---

# 5. Scheduled Jobs

## 5.1 Refresh Token Pruning

Per `kernels/identity/entities/refresh-token.md` rule 8, expired or revoked refresh-token records are purged after 30 days.

| Environment | Mechanism |
|-------------|-----------|
| Local | `scheduler` service runs `php artisan schedule:work` |
| Production | cron runs `php artisan schedule:run` every minute |

Command: `php artisan refresh-tokens:prune`

Schedule definition: `routes/console.php`

---

# 6. Anti-Patterns

- Do not commit `.env` or any secrets
- Do not use the Docker stack in production (cPanel runs native PHP)
- Do not run with `APP_ENV=production` locally unless explicitly testing production behavior
- Do not rely on the Sail `laravelphp/sail` image — it is stale (PHP 7.4) and incompatible with Laravel 12
- Do not bind port 3306 on the host if a local MySQL already uses it (stack uses 33060)
