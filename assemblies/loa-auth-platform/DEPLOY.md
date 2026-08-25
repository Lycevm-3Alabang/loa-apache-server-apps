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

### Building the distribution package

For cPanel deployments without terminal access, use the `generate-dist.ps1` script to create a deployable zip with pre-installed Linux-compatible vendor dependencies.

**Prerequisites:**

- Docker Desktop running with the shared stack started (`docker compose up -d` from workspace root)

**Build command (works from any directory):**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\loa\loa-apache-server-apps\assemblies\loa-auth-platform\generate-dist.ps1" -Path "D:\builds"
```

- Run it again after the first build to **re-zip only** (skips staging + composer if the dist folder exists)
- Add `-Force` for a clean rebuild
- Requires Docker running for the vendor install step

**Output:**

- `D:\builds\loa-auth-platform-dist\` — clean folder with production dependencies
- `D:\builds\loa-auth-platform-dist.zip` — zip ready for upload to cPanel
- The zip is **password-protected by default** (`password123`) so cPanel's upload virus scan cannot inspect archive contents; File Manager's Extract prompts for the password. Override with `-ZipPassword <pass>` or disable with `-NoEncrypt`.

**What the script does:**

1. Copies source files to a staging folder (excludes `.git`, `node_modules`, `vendor`, `docker`, tests, `.env*`)
2. Runs `composer install --no-dev --optimize-autoloader` **inside the Docker container** to install Linux-compatible vendor dependencies
3. Moves the staging folder to the dist output directory
4. Creates a zip file of the dist folder

**Skip the vendor step** (if you plan to run `composer install` on the server):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\loa\loa-apache-server-apps\assemblies\loa-auth-platform\generate-dist.ps1" -Path "D:\builds" -SkipVendor
```

**Then upload the zip to cPanel** and extract in `~/loa-auth-platform/`.

### Server prerequisites

| Requirement | Value | How to check |
|-------------|-------|--------------|
| PHP | **8.3+** (`composer.json`: `"php": "^8.3"`) | cPanel **MultiPHP Manager**, or `php -v` over SSH |
| PDO MySQL | `pdo_mysql` | `php -m | grep pdo_mysql` |
| Crypto | `openssl` | JWT HMAC signing + AES-256-GCM payload encryption |
| Strings | `mbstring`, `ctype` | Laravel framework requirements |
| HTTP/XML | `curl`, `dom`, `xml`, `fileinfo` | Laravel framework requirements |

Select the matching PHP version per-domain/vhost in cPanel's **MultiPHP Manager** (the CLI `php` binary follows the vhost selection on most hosts). If `composer install` fails with platform-requirement errors, the CLI PHP is not 8.3 — point Composer at the right binary (e.g., `/usr/local/bin/php83`) or use cPanel's **Terminal** with the domain's PHP selected.

### File permissions

Laravel needs write access to runtime directories as the cPanel user (not root, not nobody):

```bash
cd ~/loa-auth-platform
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
```

Symptoms of wrong ownership/permissions: blank pages, `failed to open stream` errors, sessions silently broken (`SESSION_DRIVER=file`). If uploads via File Manager created files as a different user, re-chown to your cPanel account via support ticket or SSH:

```bash
chown -R <cpanel_user>:<cpanel_user> ~/loa-auth-platform/storage ~/loa-auth-platform/bootstrap/cache
```

### Backup & rollback

Take a database snapshot **before every migration run** in production:

```bash
mysqldump -u lyceumalabang_auth_admin -p --single-transaction lyceumalabang_auth_db > backup_$(date +%Y%m%d_%H%M).sql
```

Rollback options, cheapest first:

1. **Application-level**: `php artisan migrate:rollback --step=1` (undo last batch) — only safe if migrations have proper `down()` methods.
2. **Data restore**: recreate the database and re-import the snapshot:

```bash
mysql -u lyceumalabang_auth_admin -p lyceumalabang_auth_db < backup_YYYYMMDD_HHMM.sql
```

   Then redeploy the previous code release (keep the prior release directory and swap the docroot, or restore from your VCS tag).
3. Check what ran before deciding: `php artisan migrate:status`.

> Never use `docker compose down -v` thinking it affects production — it only touches local Docker volumes.

### Mail delivery mode

Mail is sent **synchronously**: forgot-password and change-request endpoints block until SMTP accepts the message. With a slow relay this adds noticeable latency to those endpoints only.

- Acceptable default at current scale — no action needed.
- To make mail async later: implement `ShouldQueue` on `App\Mail\PasswordResetMail`, set `QUEUE_CONNECTION=database` in `.env`, run the `queue_jobs` migration if prompted, and drive the worker from cron every minute:
  ```text
  * * * * * cd /home/<user>/loa-auth-platform && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
  ```
- Test whichever mode you choose with the §3 verification step 5 (forgot-password email must arrive).

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
| `DB_DATABASE` | `lyceumalabang_auth_db` | Yes |
| `DB_USERNAME` | `lyceumalabang_auth_admin` (all privileges on that DB) | Yes |
| `DB_PASSWORD` | provided at deploy time — never committed | Yes |
| `JWT_SECRET` | random 32+ chars | Yes |
| `JWT_ACCESS_TTL` | `15` (per `kernels/identity/rules/token-lifecycle.md`) | No |
| `JWT_REFRESH_TTL` | `10080` | No |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://aces-api.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |
| `CACHE_STORE` | `file` | Yes |
| `ADMIN_EMAIL` | admin email | Yes |
| `ADMIN_PASSWORD` | admin password | Yes |
| `ADMIN_NAME` | `Super Admin` | Yes |

Additional variables required by the web UI layer (see [web-ui.md](web-ui.md) §8 and §16):

| Variable | Value | Purpose |
|----------|-------|---------|
| `AUTH_ADMIN_GROUP` | `loa-auth-admin` | Group whose members are platform admins |
| `ENCRYPTION_KEY` | hex-encoded 32-byte key (`openssl rand -hex 32`) | AES-256-GCM for SSO redirect payloads |
| `ENCRYPTION_KEY_PREVIOUS` | previous key during rotation | Optional grace-period decryption |
| `SESSION_LIFETIME` | `480` | Admin session lifetime (minutes) |
| `SESSION_SECURE` | `true` | HTTPS-only session cookie (required in prod) |
| `MAIL_MAILER` | `smtp` | Real SMTP instead of Mailpit |
| `MAIL_HOST` | cPanel SMTP host | Outgoing mail server |
| `MAIL_PORT` | `587` | Submission port |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | mailbox credentials | SMTP auth |
| `MAIL_SCHEME` | unset (STARTTLS auto on 587) | Set `smtps` only for port `465`; do **not** use legacy `MAIL_ENCRYPTION` — unsupported by the mailer scheme API |
| `MAIL_FROM_ADDRESS` | `noreply@lyceumalabang.edu.ph` | Sender identity for reset/activation links |

> Without correct `MAIL_*` values, password-reset and account-activation emails fail silently at runtime — test the forgot-password flow right after deploy (§3).

### Migrating existing data

Two supported paths — see **`docs/cpanel-db-migration-runbook.md`** (root) for the full side-by-side runbook:

- **Fresh database (drop → create → seed)** — cleanest when local data needn't survive. Seeding provisions schema + admin only; tenants/catalog/groups/grants must be re-provisioned afterwards (§6 of the runbook), and cert needs its organization row.
  - **Shortcut:** `database/sql/cpanel-auth-db-install.sql` is a generated one-file installer for `lyceumalabang_auth_db` — full schema + prod tenant + 56-endpoint catalog + groups + 99-grant matrix + default admin (`Admin123!`, change after first login). Import via phpMyAdmin instead of migrate+seed, then run cert-side steps only.
- **Carry existing data** — export from Docker MySQL, import via phpMyAdmin/SSH (below).

If you want to carry your locally provisioned users/tenants/groups to production instead of starting from seeds:

1. The production database and user are already provisioned in cPanel:

| Item | Value |
|------|-------|
| Database | `lyceumalabang_auth_db` |
| User | `lyceumalabang_auth_admin` (all privileges) |
| Password | provided at deploy time — never committed |

2. Export from the running Docker stack (from the repo root):

```bash
docker compose exec mysql sh -c "mysqldump -uloa -ploa-secret --single-transaction --routines loa_auth" > loa_auth.sql
```

3. Import via cPanel **phpMyAdmin** (select `lyceumalabang_auth_db` → Import → upload the `.sql`) or over SSH:

```bash
mysql -u lyceumalabang_auth_admin -p lyceumalabang_auth_db < loa_auth.sql
```

4. Review imported rows before going live — dev fixtures (e.g., `dev_app_url`, test tenants/users created by seeders) usually should not ship to production.
5. Alternatively skip migration entirely and let `php artisan db:seed --force` provision a clean production state (admin account comes from `ADMIN_*` env vars).

---

## 3. Post-deploy verification

1. Visit `https://auth.lyceumalabang.edu.ph/login` in a browser.
2. Confirm the API routes are available with `php artisan route:list --path=api`.
3. Confirm the OpenAPI document can be generated with `php artisan l5-swagger:generate`.
4. Confirm the admin account exists and is usable.
5. Submit `/forgot-password` for a real mailbox and confirm the reset email **arrives** (verifies `MAIL_*` + `APP_URL`, since emailed links are built from it).
6. If SSO is in use, verify a tenant login redirects with an encrypted payload (proves `ENCRYPTION_KEY` matches what the tenant app expects).

---

## 4. Anti-patterns

- Do not commit `.env` or secrets to version control.
- Do not run `composer install` without `--no-dev` in production.
- Do not use the Docker stack for production.
- Do not seed via HTTP routes.
- Do not hardcode credentials in seeds or code.
- Do not run migrations or seeds blindly in production without review.
