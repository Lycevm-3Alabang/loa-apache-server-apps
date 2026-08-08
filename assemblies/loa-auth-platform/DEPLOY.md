# LOA Auth Platform — Deployment Guide

## 1. Local Docker (development)

### Prerequisites

- Docker Desktop running
- The shared compose stack at the workspace root is available via [docker-compose.yml](../../docker-compose.yml)

### Start the stack

Run the following from the workspace root:

```bash
cd /path/to/loa-apache-server-apps
docker compose up -d --build
```

The shared stack exposes:

- Auth app: http://localhost:8080
- Cert app: http://localhost:9001
- Mailpit: http://localhost:8025

### Run migrations and seed locally

```bash
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
```

### Run the test suite locally

```bash
docker compose exec auth-app php artisan test
```

### Full reset (fresh database)

```bash
docker compose down -v
docker compose up -d --build
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
```

### Common commands

```bash
docker compose exec auth-app bash
docker compose exec auth-app composer dump-autoload
docker compose exec auth-app php artisan tinker
docker compose logs -f auth-app
docker compose down
docker compose down -v
```

> The compose file is intended for local development only. Do not use the Docker stack for production deployments.

---

## 2. cPanel (production)

### Pre-deploy checklist

1. Run the test suite locally.
2. Run `composer install --no-dev --optimize-autoloader` on the target server or in a release build.
3. Ensure the required environment variables are present (see [environment.md](environment.md)).
4. Set `APP_ENV=production` and `APP_DEBUG=false`.
5. Generate a fresh `JWT_SECRET` and keep it in sync across all LOA apps.

### Deploy steps

1. Upload the application files to `~/loa-auth-platform/` via SFTP or File Manager.
2. Install production dependencies:

```bash
cd ~/loa-auth-platform
composer install --no-dev --optimize-autoloader
```

3. Create or update the `.env` file with the production values.
4. Run the database work:

```bash
php artisan config:clear
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan l5-swagger:generate
```

5. Point the cPanel subdomain document root to `~/loa-auth-platform/public`.
6. Add the scheduler entry to Cron:

```text
* * * * * php /home/<user>/loa-auth-platform/artisan schedule:run >> /dev/null 2>&1
```

### Required environment variables

| Variable | Value | Required |
|----------|-------|----------|
| `APP_ENV` | `production` | Yes |
| `APP_DEBUG` | `false` | Yes |
| `APP_URL` | `https://auth.lyceumalabang.edu.ph` | Yes |
| `DB_HOST` | `localhost` | Yes |
| `DB_PORT` | `3306` | Yes |
| `DB_DATABASE` | `loa_auth` | Yes |
| `DB_USERNAME` | cPanel DB user | Yes |
| `DB_PASSWORD` | cPanel DB password | Yes |
| `JWT_SECRET` | random 32+ chars | Yes |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://aces-api.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |
| `CACHE_STORE` | `file` | Yes |
| `ADMIN_EMAIL` | admin email | Yes |
| `ADMIN_PASSWORD` | admin password | Yes |
| `ADMIN_NAME` | `Super Admin` | Yes |

---

## 3. Post-deploy verification

1. Visit `https://auth.lyceumalabang.edu.ph/login` in a browser.
2. Confirm the API routes are available with `php artisan route:list --path=api`.
3. Confirm the OpenAPI document can be generated with `php artisan l5-swagger:generate`.
4. Confirm the admin account exists and is usable.

---

## 4. Anti-patterns

- Do not commit `.env` or secrets to version control.
- Do not run `composer install` without `--no-dev` in production.
- Do not use the Docker stack for production.
- Do not seed via HTTP routes.
- Do not hardcode credentials in seeds or code.
- Do not run migrations or seeds blindly in production without review.
