# LOA Auth Platform — Deployment Guide

## 1. Local Docker (Development)

### Prerequisites

- Docker Desktop running
- Docker Compose stack started: `docker compose up -d` from `assemblies/loa-auth-platform/`

### Start the stack

```bash
docker compose up -d
```

### Run migrations locally

```bash
docker compose exec app php artisan migrate
```

### Seed the database locally

```bash
docker compose exec app php artisan db:seed
```

### Full reset (fresh DB)

```bash
docker compose down -v
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

### Rebuild the stack

After code changes (new migrations, model updates, view changes):

```bash
docker compose down
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
# Local dev (Docker)
docker compose exec -T app php artisan db:seed

# cPanel (no terminal)
# Import database/seeders/database.sql via phpMyAdmin — seeds are already embedded

# cPanel (with SSH)
php artisan db:seed --force
```

`--build` rebuilds the Docker image from the Dockerfile. The `down` + `up -d --build` cycle ensures a clean start with the latest code. Migrations run automatically on boot via the entrypoint, but the explicit `migrate --force` confirms nothing is pending.

### Common Docker commands

```bash
docker compose exec app bash              # shell into app container
docker compose exec app composer dump-autoload
docker compose exec app php artisan tinker
docker compose logs -f app                # view app logs
docker compose down                        # stop stack (keeps DB volume)
docker compose down -v                     # stop + delete DB volume (fresh start)
```

---

## 2. cPanel (Production)

### 2.1 Pre-Deploy Checklist

1. Run `php -l` on every changed PHP file locally (no syntax errors)
2. Run `composer install --no-dev --optimize-autoloader` locally or on the server
3. Ensure all required env vars are set (see [environment.md](environment.md))
4. Set `APP_ENV=production` and `APP_DEBUG=false`
5. Generate a fresh `JWT_SECRET` (use the same value across all LOA apps)

### 2.2 Create a Distro Folder

The distro folder contains only the files needed for production (no dev tools, no VCS history).

**Option A — Export from Docker container**

```bash
# From the project root (assemblies/loa-auth-platform/)
docker exec loa-auth-app-1 bash -c \
  "cd /var/www/html && tar -czf /tmp/distro.tar.gz \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='docker-compose.yml' \
  --exclude='.env*' \
  --exclude='*.md' \
  --exclude='environment.md' \
  --exclude='web-ui.md' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  ."

docker cp loa-auth-app-1:/tmp/distro.tar.gz ../../dist.tar.gz
tar -xzf ../../dist.tar.gz -C ../../dist/
```

**Option B — Upload source and install on the server**

Skip creating a distro folder entirely. Upload the full project (excluding `.git`, `node_modules`, `vendor`) and run `composer install --no-dev` directly on the server.

### 2.3 Upload to cPanel

Upload the distro folder (or full source if using Option B) to your cPanel home directory:

```
~/loa-auth-platform/
```

Use SFTP (e.g., FileZilla) or cPanel File Manager.

### 2.4 Post-Deploy Setup

SSH into your cPanel server and run these commands in order:

```bash
# Navigate to the project directory
cd ~/loa-auth-platform

# Install production dependencies
composer install --no-dev --optimize-autoloader

# Clear stale configuration before rebuilding it
php artisan config:clear

# Generate application key (only if APP_KEY is not set)
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed the database (first deploy only)
php artisan db:seed --force

# Rebuild the production configuration cache after .env is configured
php artisan config:cache

# Regenerate the OpenAPI document from the deployed controller attributes
php artisan l5-swagger:generate

# Set up the cron scheduler
crontab -e
```

Add this line to the crontab:

```
* * * * * php /home/<user>/loa-auth-platform/artisan schedule:run >> /dev/null 2>&1
```

Replace `<user>` with your cPanel username.

### 2.5 Configure the Web Server

In cPanel, point the domain/subdomain document root to the `public/` directory:

- **Subdomain**: `auth.lyceumalabang.edu.ph`
- **Document root**: `~/loa-auth-platform/public`

### 2.6 Environment Variables (Production)

Set these in cPanel → "Environment Variables" or in the `.env` file:

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
| `JWT_SECRET` | random 32+ chars | **Yes** |
| `JWT_ACCESS_TTL` | `15` | No |
| `JWT_REFRESH_TTL` | `10080` | No |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://aces-api.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |
| `CACHE_STORE` | `file` | Yes |
| `ADMIN_EMAIL` | admin email | Yes |
| `ADMIN_PASSWORD` | admin password | Yes |
| `ADMIN_NAME` | `Super Admin` | Yes |

`JWT_SECRET` must match the value used in all other LOA apps. It must never be committed to version control.

`CORS_ALLOWED_ORIGINS` is a comma-separated origin list. Do not use nested brackets or JSON syntax. If the cache store is set to `database`, the `cache` and `cache_locks` tables must exist before running cache commands; `file` is the default production setting.

### 2.7 No-Terminal Deployment (cPanel GUI only)

Use this path when SSH / Terminal is not available on the cPanel account. Everything is done through the cPanel web interfaces: MySQL, phpMyAdmin, File Manager, Subdomains, and Cron Jobs. No `artisan` or `composer` commands are run on the server.

**What replaces the terminal commands:**

| Terminal command (Section 2.4) | No-terminal equivalent |
|--------------------------------|------------------------|
| `composer install` | Upload a prebuilt `vendor/` from a local machine (auth dependencies are pure PHP; a Windows-built vendor runs on Linux). |
| `php artisan migrate` | Import `database/seeders/database.sql` via phpMyAdmin. |
| `php artisan db:seed` | Already included in `database/seeders/database.sql` (admin group, permissions, admin user). |
| `php artisan key:generate` | Set `APP_KEY` to a locally generated value of the form `base64:<32 random bytes>`. |
| `php artisan config:cache` | Skip — optional optimization only. |
| `php artisan l5-swagger:generate` | Skip — only regenerates the OpenAPI document. |

The SQL dump must be kept in sync with the migrations. It currently covers all 10 tables including `sessions` (web UI). If a migration is added, regenerate or extend the dump; verify every table and the admin user exist.

**Steps:**

1. **MySQL Databases** (cPanel → MySQL Databases): create the `loa_auth` database and a database user; grant the user all privileges on `loa_auth`.
2. **Import schema + seed** (cPanel → phpMyAdmin): select the `loa_auth` database → Import → choose `database/seeders/database.sql` from the distro folder. This creates all tables and seeds the `loa-auth-admin` group, all permissions, and the admin user. Admin credentials come from the dump (default `admin@lyceumalabang.edu.ph` / `Admin123!` — change after first login).
3. **Upload files** (FileZilla/SFTP — a GUI client, no terminal): upload the distro folder contents **plus** the prebuilt `vendor/` directory into `~/loa-auth-platform/`. The distro excludes `vendor`, `.env`, and `*.md` files by design, so those must be added manually.
4. **Create `.env`** (cPanel → File Manager → Edit): copy `assemblies/loa-auth-platform/.env.example` and fill in the production values from Section 2.6. Required at minimum: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_*`, `JWT_SECRET`, `ADMIN_*`. `APP_KEY` and `JWT_SECRET` can be generated locally with any tool (no PHP required), e.g. `openssl rand -base64 48` or Node.js `crypto.randomBytes()`.
5. **File permissions** (cPanel → File Manager → right-click → Change Permissions): `storage/` and `bootstrap/cache` must be writable by PHP (directories 755/775, files 644).
6. **Subdomain** (cPanel → Subdomains): point `auth.lyceumalabang.edu.ph` at document root `~/loa-auth-platform/public`.
7. **Scheduled jobs** (cPanel → Cron Jobs): add `* * * * * php /home/<user>/loa-auth-platform/artisan schedule:run >> /dev/null 2>&1` (replace `<user>` with the cPanel username).

**Verification (GUI only):**

- Visit `https://auth.lyceumalabang.edu.ph/login` — the login form must render without a 500 error.
- Log in with the admin credentials from the dump, then change the password via `POST /api/v1/auth/password/change-request` or the web reset flow.
- Optionally open phpMyAdmin and confirm all tables were created with rows (especially `user_groups`, `permissions`, `users`).

---

## 3. Post-Deploy Verification

1. Visit `https://auth.lyceumalabang.edu.ph` in a browser
2. Verify the API routes: `php artisan route:list --path=api`
3. Verify the generated OpenAPI document: `php artisan l5-swagger:generate`
4. Verify the admin user exists: `php artisan tinker` → `App\Models\User::where('email', env('ADMIN_EMAIL'))->first()`
5. Confirm the cron is working: wait 1 minute and check `php artisan schedule:list`

---

## 4. Anti-Patterns

- Do not commit `.env` or secrets to version control
- Do not run `composer install` without `--no-dev` in production
- Do not run the Docker stack in production
- Do not seed via HTTP route (security risk)
- Do not hardcode credentials in seeder code
- Do not skip `--force` flags on production (migrations and seeding are destructive)
- Do not run with `APP_DEBUG=true` in production
