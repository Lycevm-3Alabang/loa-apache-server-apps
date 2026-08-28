# LOA Cert Platform — Deployment Guide

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
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
```

### Run the test suite locally

```bash
docker compose exec cert-app php artisan test
```

### Full reset (fresh database)

```bash
docker compose down -v
docker compose up -d --build
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
```

### Common commands

```bash
docker compose exec cert-app bash
docker compose exec cert-app composer dump-autoload
docker compose exec cert-app php artisan tinker
docker compose logs -f cert-app
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
5. Generate a fresh `JWT_SECRET` and keep it consistent with the Auth Platform.

### Building the distribution package

For cPanel deployments without terminal access, use the `generate-dist.ps1` script to create a deployable zip with pre-installed Linux-compatible vendor dependencies.

**Prerequisites:**

- Docker Desktop running with the shared stack started (`docker compose up -d` from workspace root)

**Build command (works from any directory):**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\loa\loa-apache-server-apps\assemblies\loa-cert-platform\generate-dist.ps1" -Path "D:\builds"
```

- Run it again after the first build to **re-zip only** (skips staging + composer if the dist folder exists)
- Add `-Force` for a clean rebuild
- Requires Docker running for the vendor install step

**Output:**

- `D:\builds\loa-cert-platform-dist\` — clean folder with production dependencies
- `D:\builds\loa-cert-platform-dist.zip` — zip ready for upload to cPanel
- The zip is **password-protected by default** (`password123`) so cPanel's upload virus scan cannot inspect archive contents; File Manager's Extract prompts for the password. Override with `-ZipPassword <pass>` or disable with `-NoEncrypt`.

**What the script does:**

1. Copies source files to a staging folder (excludes `.git`, `node_modules`, `vendor`, `docker`, tests, `.env*`)
2. Runs `composer install --no-dev --optimize-autoloader` **inside the Docker container** to install Linux-compatible vendor dependencies
3. Moves the staging folder to the dist output directory
4. Creates a zip file of the dist folder

**Skip the vendor step** (if you plan to run `composer install` on the server):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\loa\loa-apache-server-apps\assemblies\loa-cert-platform\generate-dist.ps1" -Path "D:\builds" -SkipVendor
```

**Then upload the zip to cPanel** and extract in `~/loa-cert-platform/`.

### Deploy steps

1. Upload the application files to `~/loa-cert-platform/` via SFTP or File Manager.
2. Install production dependencies:

```bash
cd ~/loa-cert-platform
composer install --no-dev --optimize-autoloader
```

3. Create or update the `.env` file with the production values.
4. Run the database work:

> **Fresh-database path (drop → create → seed):** supported — but `db:seed` here is a **no-op** (empty seeder). After migrating you MUST insert the organization row or every event/template write fails with FK 1452. Full procedure incl. that SQL: root **`docs/cpanel-db-migration-runbook.md`** §5–§6.

```bash
php artisan config:clear
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan l5-swagger:generate
```

5. Point the cPanel subdomain document root to `~/loa-cert-platform/public`.
6. Add the scheduler entry to Cron:

```text
* * * * * php /home/<user>/loa-cert-platform/artisan schedule:run >> /dev/null 2>&1
```

### Required environment variables

| Variable | Value | Required |
|---|---|---|
| `APP_ENV` | `production` | Yes |
| `APP_DEBUG` | `false` | Yes |
| `APP_URL` | `https://cert-api.lyceumalabang.edu.ph` | Yes |
| `DB_HOST` | `localhost` | Yes |
| `DB_PORT` | `3306` | Yes |
| `DB_DATABASE` | `lyceumalabang_e_cert` | Yes |
| `DB_USERNAME` | `lyceumalabang_auth_admin` (all privileges on `lyceumalabang_e_cert`) — password provided at deploy time, never committed | Yes |
| `DB_PASSWORD` | provided at deploy time — never committed | Yes |
| `JWT_SECRET` | random 32+ chars | Yes |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |

---

## 3. Post-deploy verification

1. Visit `https://cert-api.lyceumalabang.edu.ph` in a browser.
2. Confirm the routes are available with `php artisan route:list --path=api`.
3. Confirm the OpenAPI document can be generated with `php artisan l5-swagger:generate`.
4. Confirm the base seed data or required records are present.

---

## 4. Anti-patterns

- Do not commit `.env` or secrets to version control.
- Do not run `composer install` without `--no-dev` in production.
- Do not use the Docker stack for production.
- Do not assume local Docker behavior matches the server environment.
- Do not skip a review of the API contract before changing implementation.
