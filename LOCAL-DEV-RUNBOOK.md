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

## 4. Run only one app instead of all

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

## 5. Debugging one app only

If you are debugging a single app, start only that app and keep the shared services running.

### Debug auth only

```bash
docker compose up -d --build mysql mailpit auth-app auth-nginx auth-scheduler
docker compose exec auth-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose logs -f auth-app
```

### Debug cert only

```bash
docker compose up -d --build mysql mailpit cert-app cert-nginx cert-scheduler
docker compose exec cert-app php artisan migrate --force
docker compose exec cert-app php artisan db:seed --force
docker compose logs -f cert-app
```

This is the best fit when you want the shared MySQL/Mailpit services available but do not need to run the full multi-app stack.

---

## 6. Common workflow examples

### Example A: start everything, then migrate and seed both apps

```bash
docker compose up -d --build
docker compose exec auth-app php artisan migrate --force
docker compose exec cert-app php artisan migrate --force
docker compose exec auth-app php artisan db:seed --force
docker compose exec cert-app php artisan db:seed --force
```

### Example B: start only auth and run its migration/seed

```bash
docker compose up -d --build auth-app auth-nginx auth-scheduler
 docker compose exec auth-app php artisan migrate --force
 docker compose exec auth-app php artisan db:seed --force
```

### Example C: start only cert and run its migration/seed

```bash
docker compose up -d --build cert-app cert-nginx cert-scheduler
 docker compose exec cert-app php artisan migrate --force
 docker compose exec cert-app php artisan db:seed --force
```

---

## 7. Useful troubleshooting commands

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

---

## 8. Notes

- The root compose file in [docker-compose.yml](docker-compose.yml) is the canonical local-development entry point.
- Migrations and seeds are run inside the application container, not from the host shell.
- If you want to reset the environment completely, use:

```bash
docker compose down -v
docker compose up -d --build
```
