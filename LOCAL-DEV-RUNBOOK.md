# Local development runbook

This guide shows where to run the shared Docker stack, how to migrate and seed each application, and how to start only one app when needed.

## 1. Start everything from the repository root

Run all services:

```bash
docker compose up -d --build
```

Check status:

```bash
docker compose ps
```

Stop everything:

```bash
docker compose down
```

Stop everything and remove volumes:

```bash
docker compose down -v
```

---

## 2. Run migrations for each app

### Auth app

```bash
docker compose exec auth-app php artisan migrate --force
```

### Cert app

```bash
docker compose exec cert-app php artisan migrate --force
```

For the local Docker environment only, the auth app seed step also provisions a local cert tenant and the local cert user groups automatically. The tenant uses the local cert URL as its redirect origin and the groups created are `cert-admin`, `cert-staff`, and `cert-user`.

---

## 3. Seed each app individually

### Auth app

```bash
docker compose exec auth-app php artisan db:seed --force
```

### Cert app

```bash
docker compose exec cert-app php artisan db:seed --force
```

---

## 4. Generate Swagger documentation

After migrations, generate the OpenAPI spec for the cert app:

```bash
docker compose exec cert-app php artisan l5-swagger:generate
```

Access Swagger UI at: `http://localhost:9001/api/docs`

---

## 5. Run only one app instead of all

If you want the full stack but only need one app at a time, start the specific service(s):

### Start only the auth services

```bash
docker compose up -d --build auth-app auth-nginx auth-scheduler
```

### Start only the cert services

```bash
docker compose up -d --build cert-app cert-nginx cert-scheduler
```

### Start only the shared infrastructure

```bash
docker compose up -d --build mysql mailpit
```

---

## 6. Standalone cert app (optional)

The cert app has its own `docker-compose.yml` for independent development:

```bash
cd assemblies/loa-cert-platform
docker compose up -d --build
docker compose exec app composer install --no-interaction --no-progress
docker compose exec app php artisan migrate --force
docker compose exec app php artisan l5-swagger:generate
docker compose logs -f app
```

This spins up: `app` (PHP-FPM), `nginx` (port 9001), `mysql`, `scheduler`, `mailpit`.

---

## 7. Debugging one app only

If you are debugging a single app, start only that app and keep the shared services running.

### Debug auth only

```bash
docker compose up -d --build mysql mailpit auth-app auth-nginx auth-scheduler
docker compose exec -T auth-app composer install --no-interaction --no-progress
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose logs -f auth-app
```

### Debug cert only

```bash
docker compose up -d --build mysql mailpit cert-app cert-nginx cert-scheduler
docker compose exec -T cert-app composer install --no-interaction --no-progress
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
docker compose exec cert-app php artisan l5-swagger:generate
docker compose logs -f cert-app
```

This is the best fit when you want the shared MySQL/Mailpit services available but do not need to run the full multi-app stack.

---

## 8. Common workflow examples

### First time setup (everything)

```bash
docker compose down -v
docker compose up -d --build
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose exec auth-app php artisan l5-swagger:generate
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
docker compose exec cert-app php artisan l5-swagger:generate
```

### Start everything (already set up)

```bash
docker compose up -d --build
```

### Auth only

```bash
docker compose up -d --build auth-app auth-nginx auth-scheduler
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose exec auth-app php artisan l5-swagger:generate
```

### Cert only

```bash
docker compose up -d --build cert-app cert-nginx cert-scheduler
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
docker compose exec cert-app php artisan l5-swagger:generate
```

---

## 9. Useful troubleshooting commands

View logs for one app:

```bash
docker compose logs -f auth-app
```

```bash
docker compose logs -f cert-app
```

Open a shell inside an app container:

```bash
docker compose exec auth-app bash
```

```bash
docker compose exec cert-app bash
```

Run tests inside an app container:

```bash
docker compose exec auth-app php artisan test
```

```bash
docker compose exec cert-app php artisan test
```

Regenerate Swagger docs:

```bash
docker compose exec cert-app php artisan l5-swagger:generate
```

---

## 10. Test structure

Tests are located in each app's `tests/` directory:

```
cert-app/
├── phpunit.xml.dist
└── tests/
    ├── TestCase.php
    ├── CreatesApplication.php
    ├── Feature/
    │   └── Api/
    │       ├── CertificateTest.php
    │       └── CertificateTemplateTest.php
    └── Unit/
        └── PlaceholderResolverTest.php
```

Run the full test suite:

```bash
docker compose exec cert-app php artisan test
```

Run a specific test file:

```bash
docker compose exec cert-app php artisan test tests/Feature/Api/CertificateTest.php
```

---

## 11. Known Issues & Gotchas

### Missing `artisan` file

If artisan commands fail with "Command not defined" or similar, the `artisan` file may be missing from the Laravel app root. Create it:

```php
#!/usr/bin/env php
<?php

use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$status = (require_once __DIR__.'/bootstrap/app.php')
    ->handleCommand(new ArgvInput);

exit($status);
```

Also ensure `public/index.php` exists:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
```

### `.env` parsing errors (APP_NAME)

Laravel's dotenv parser is strict about quoting. Values with spaces **must** be quoted:

```
# Wrong — causes "unexpected whitespace" error
APP_NAME=LOA Cert Platform
ADMIN_NAME=Super Admin

# Correct
APP_NAME="LOA Cert Platform"
ADMIN_NAME="Super Admin"
```

The container's `environment:` block in docker-compose overrides `.env` values, but any key **not** set there falls back to the `.env` file. If the `.env` has unquoted spaces, the app crashes on bootstrap.

**When copying `.env` between directories**, verify all values with spaces are quoted.

### `config/app.php` must not list providers manually

Laravel 11+ auto-registers service providers via `Application::configure()`. If `config/app.php` contains a `providers` array, it overrides auto-registration and breaks artisan commands (migrate, db:seed, etc.). The `providers` key should **not** exist in `config/app.php`.

---

## 12. Notes

- The root compose file in [docker-compose.yml](docker-compose.yml) is the canonical local-development entry point.
- The cert app also has a standalone [docker-compose.yml](assemblies/loa-cert-platform/docker-compose.yml) for independent development.
- Migrations and seeds are run inside the application container, not from the host shell.
- Swagger docs must be regenerated after adding/modifying `#[OA\...]` attributes in controllers.
- If you want to reset the environment completely, use:

```bash
docker compose down -v
docker compose up -d --build
```
