# LOA Platform — CLI Commands Reference

All commands run from repo root `D:\loa\loa-apache-server-apps` unless noted.

---

## Full Reset

```powershell
.\scripts\reset-all.ps1                    # Teardown + rebuild + migrate + seed + Swagger
.\scripts\reset-all.ps1 -SkipProvision     # Infrastructure only (no migrate/seed)
.\scripts\reset-all.ps1 -SkipUp            # Teardown only (no restart)
```

---

## Start / Stop

```powershell
docker compose up -d --build               # Start all services
docker compose ps                          # Check status
docker compose down                        # Stop (preserves volumes)
docker compose down -v                     # Stop + remove volumes (loses data)
```

---

## Migrate

```powershell
docker compose exec auth-app php artisan migrate --force
docker compose exec cert-app php artisan migrate --force
```

---

## Seed

```powershell
docker compose exec auth-app php artisan db:seed --force
docker compose exec cert-app php artisan db:seed --force
```

---

## Fresh Reset (drop + re-create + seed)

```powershell
docker compose exec auth-app php artisan migrate:fresh --force --seed
docker compose exec cert-app php artisan migrate:fresh --force --seed
```

---

## Rollback

```powershell
docker compose exec auth-app php artisan migrate:rollback --step=1
docker compose exec cert-app php artisan migrate:rollback --step=1
```

---

## Check Migration Status

```powershell
docker compose exec auth-app php artisan migrate:status
docker compose exec cert-app php artisan migrate:status
```

---

## Run Tests

```powershell
docker compose exec auth-app php artisan test
docker compose exec cert-app php artisan test
```

---

## Clear Cache

```powershell
docker compose exec auth-app php artisan config:clear
docker compose exec auth-app php artisan view:clear
docker compose exec cert-app php artisan config:clear
docker compose exec cert-app php artisan view:clear
```

---

## Cache Config (production-like)

```powershell
docker compose exec auth-app php artisan config:cache
docker compose exec cert-app php artisan config:cache
```

---

## Generate Swagger

```powershell
docker compose exec auth-app php artisan l5-swagger:generate
docker compose exec cert-app php artisan l5-swagger:generate
```

---

## Build Deploy Packages

```powershell
.\dump.ps1 --target auth                  # Auth ZIP for cPanel
.\dump.ps1 --target cert                  # Cert ZIP for cPanel
.\scripts\build-all.ps1 -Path "D:\builds" # Both apps
```

---

## Auth Platform Custom Commands

```powershell
docker compose exec auth-app php artisan auth:test                      # End-to-end auth flow test
docker compose exec auth-app php artisan auth:repair-i1-violations      # Detect I1 invariant violations
docker compose exec auth-app php artisan permissions:import cert        # Import route policies (dry-run by default)
docker compose exec auth-app php artisan permissions:import cert --dry-run
docker compose exec auth-app php artisan refresh-tokens:prune           # Purge expired/revoked refresh tokens
```

---

## Troubleshooting

```powershell
docker compose logs -f auth-app                # Tail auth logs
docker compose logs -f cert-app                # Tail cert logs
docker compose restart auth-nginx cert-nginx   # Fix 502 after container recreate
docker compose exec auth-app bash              # Shell into auth container
docker compose exec cert-app bash              # Shell into cert container
```

---

## cPanel (No Terminal)

Since cPanel has no SSH terminal, use **phpMyAdmin** and **File Manager**:

1. **Migrate**: phpMyAdmin → Import → upload `assemblies/loa-auth-platform/database/sql/cpanel-auth-db-install.sql`
2. **Clear cache**: File Manager → delete `bootstrap/cache/config.php`
3. **Update .env**: File Manager → edit `.env` directly
