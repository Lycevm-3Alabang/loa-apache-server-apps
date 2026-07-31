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

- **Subdomain**: `auth.loa.edu.ph`
- **Document root**: `~/loa-auth-platform/public`

### 2.6 Environment Variables (Production)

Set these in cPanel → "Environment Variables" or in the `.env` file:

| Variable | Value | Required |
|----------|-------|----------|
| `APP_ENV` | `production` | Yes |
| `APP_DEBUG` | `false` | Yes |
| `APP_URL` | `https://auth.loa.edu.ph` | Yes |
| `DB_HOST` | `localhost` | Yes |
| `DB_PORT` | `3306` | Yes |
| `DB_DATABASE` | `loa_auth` | Yes |
| `DB_USERNAME` | cPanel DB user | Yes |
| `DB_PASSWORD` | cPanel DB password | Yes |
| `JWT_SECRET` | random 32+ chars | **Yes** |
| `JWT_ACCESS_TTL` | `15` | No |
| `JWT_REFRESH_TTL` | `10080` | No |
| `CORS_ALLOWED_ORIGINS` | `https://auth.loa.edu.ph,https://consult.loa.edu.ph,https://cert.loa.edu.ph` | Yes |
| `CACHE_STORE` | `file` | Yes |
| `ADMIN_EMAIL` | admin email | Yes |
| `ADMIN_PASSWORD` | admin password | Yes |
| `ADMIN_NAME` | `Super Admin` | Yes |

`JWT_SECRET` must match the value used in all other LOA apps. It must never be committed to version control.

`CORS_ALLOWED_ORIGINS` is a comma-separated origin list. Do not use nested brackets or JSON syntax. If the cache store is set to `database`, the `cache` and `cache_locks` tables must exist before running cache commands; `file` is the default production setting.

---

## 3. Post-Deploy Verification

1. Visit `https://auth.loa.edu.ph` in a browser
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
