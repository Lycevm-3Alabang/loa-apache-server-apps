# Local development runbook

This guide covers running the full stack (both apps), running a single app in isolation, and common setup tasks.

---

## 1. Full stack (both apps)

### Start everything

```bash
docker compose up -d --build
```

### Install dependencies

```bash
docker compose exec -T auth-app composer install --no-interaction --no-progress
docker compose exec -T cert-app composer install --no-interaction --no-progress
```

### Generate application keys

Required on first run, or after `docker compose down -v`:

```bash
docker compose exec auth-app php artisan key:generate
docker compose exec cert-app php artisan key:generate
```

### Run migrations and seed

```bash
docker compose exec auth-app php artisan migrate --force
docker compose exec cert-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose exec cert-app php artisan db:seed --force
```

The auth seed also provisions a local cert tenant and user groups (`cert-admin`, `cert-staff`, `cert-user`) automatically for the local Docker environment.

### Verify

- Auth: <http://localhost:8080>
- Cert: <http://localhost:9001>

---

## 2. Auth platform (isolated)

Only the auth app and its shared dependencies. The cert app is not started.

### Start services

```bash
docker compose up -d --build mysql mailpit auth-app auth-nginx auth-scheduler
```

### Install dependencies

```bash
docker compose exec -T auth-app composer install --no-interaction --no-progress
```

### Generate application key

```bash
docker compose exec auth-app php artisan key:generate
```

### Run migrations and seed

```bash
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
```

### Verify

Open <http://localhost:8080>.

---

## 3. Cert platform (isolated)

Only the cert app and its shared dependencies. The auth app is not started.

### Start services

```bash
docker compose up -d --build mysql mailpit cert-app cert-nginx cert-scheduler
```

### Install dependencies

```bash
docker compose exec -T cert-app composer install --no-interaction --no-progress
```

### Generate application key

```bash
docker compose exec cert-app php artisan key:generate
```

### Run migrations and seed

```bash
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
```

### Verify

Open <http://localhost:9001>.

---

## 4. Stopping and resetting

Stop all services:

```bash
docker compose down
```

Stop and remove volumes (full reset):

```bash
docker compose down -v
```

Full reset and re-setup (both apps):

```bash
docker compose down -v
docker compose up -d --build
docker compose exec -T auth-app composer install --no-interaction --no-progress
docker compose exec -T cert-app composer install --no-interaction --no-progress
docker compose exec auth-app php artisan key:generate
docker compose exec cert-app php artisan key:generate
docker compose exec auth-app php artisan migrate --force
docker compose exec cert-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose exec cert-app php artisan db:seed --force
```

---

## 5. Common issues

### 500 error on first run

The most common cause is a missing `APP_KEY`. Laravel requires this to encrypt sessions and cookies.

```bash
docker compose exec auth-app php artisan key:generate   # for auth
docker compose exec cert-app php artisan key:generate   # for cert
```

Then restart the affected service:

```bash
docker compose restart auth-app
```

### Vendor directory missing or stale

```bash
docker compose exec -T auth-app composer install --no-interaction --no-progress
docker compose exec -T cert-app composer install --no-interaction --no-progress
```

---

## 6. Useful commands

View logs:

```bash
docker compose logs -f auth-app
docker compose logs -f cert-app
```

Open a shell:

```bash
docker compose exec auth-app bash
docker compose exec cert-app bash
```

Run artisan commands:

```bash
docker compose exec auth-app php artisan <command>
docker compose exec cert-app php artisan <command>
```

Run tests:

```bash
docker compose exec auth-app php artisan test
docker compose exec cert-app php artisan test
```

---

## 7. Notes

- The root compose file in [docker-compose.yml](docker-compose.yml) is the canonical local-development entry point.
- Migrations and seeds run inside the application container, not from the host shell.
- The auth and cert apps use separate databases (`loa_auth` and `loa_cert`) on the same MySQL instance.
