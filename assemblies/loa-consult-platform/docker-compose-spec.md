# LOA Consult Platform — Docker Compose Wiring Spec

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)

> Adds `consult-*` services to the canonical root stack (`docker-compose.yml`, project `loa-platform`). No assembly-local compose file — ever (`AGENTS.md`, `docs/local-dev-multi-app-spec.md`).
> Verified against root `docker-compose.yml` (2026-09-19: auth + cert already wired) and `docker/mysql/init.sql`.

---

# 1. Root Stack State (verified)

```
Project: loa-platform
Network: loa (shared)

Services:
  seq              5341:80     datalust/seq
  auth-app         —           loa-auth-app (php:8.3-fpm)
  auth-nginx       8080:80     nginx:1.27-alpine
  auth-scheduler   —           loa-auth-app (schedule:work)
  auth-queue       —           loa-auth-app (queue:work)
  cert-app         —           loa-cert-app (php:8.3-fpm)
  cert-nginx       9001:80     nginx:1.27-alpine
  cert-scheduler   —           loa-cert-app (schedule:work)
  mysql            33060:3306  mysql:8.0 (healthcheck)
  mailpit          8025,1025   axllent/mailpit

Volumes: mysql_data, seq_data
Databases (init.sql): loa_auth (MYSQL_DATABASE), loa_cert (init.sql)
```

Consult adds three services + one DB init line. No new infrastructure.

---

# 2. Service Blocks (append to root `docker-compose.yml`)

```yaml
  consult-app:
    build:
      context: ./assemblies/loa-consult-platform
      dockerfile: docker/php/Dockerfile
    image: loa-consult-app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./assemblies/loa-consult-platform:/var/www/html
    environment:
      APP_ENV: local
      APP_DEBUG: true
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: loa_consult
      DB_USERNAME: loa
      DB_PASSWORD: loa-secret
      JWT_SECRET: dev-only-secret-change-before-production
      JWT_ACCESS_TTL: 15
      TENANT_SLUG: loa-consultation
      AUTH_BASE_URL: http://auth-nginx
      ENCRYPTION_KEY: base64:aQ0GFg4Sb84QdlaGQc5wiS17VFPCWOvKQZJ+/bUCRYE=
      SEQ_URL: "http://seq:5341"
      MAIL_HOST: mailpit
      MAIL_PORT: 1025
    depends_on:
      mysql:
        condition: service_healthy
    networks:
      - loa

  consult-nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "9002:80"
    volumes:
      - ./assemblies/loa-consult-platform:/var/www/html
      - ./assemblies/loa-consult-platform/docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - consult-app
    networks:
      - loa

  consult-scheduler:
    image: loa-consult-app
    restart: unless-stopped
    working_dir: /var/www/html
    command: ["php", "artisan", "schedule:work"]
    volumes:
      - ./assemblies/loa-consult-platform:/var/www/html
    environment:
      APP_ENV: local
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: loa_consult
      DB_USERNAME: loa
      DB_PASSWORD: loa-secret
    depends_on:
      mysql:
        condition: service_healthy
    networks:
      - loa
```

**Notes:**
- `AUTH_BASE_URL=http://auth-nginx` — in-network name; host browsers use `:8080`, containers use the service name.
- `ENCRYPTION_KEY` — must be **byte-identical** with auth-platform `.env` (AES-256-GCM SSO payload decryption). Copied from auth `.env`; never commit production values.
- `JWT_SECRET` dev-only until Auth deploy-time sharing.
- `MAIL_HOST=mailpit` + `MAIL_PORT=1025` — same Mailpit instance as auth/cert.
- No `consult-queue` — no queued workloads yet; mail sends sync. Add with Phase E email work.
- Dockerfile is identical to cert's (GD, mbstring, intl) — no surprises at build time.

---

# 3. MySQL Init (`docker/mysql/init.sql`)

Append to the existing file (which already creates `loa_cert`):

```sql
-- Consult database (appended for consult-platform wiring)
CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%';
FLUSH PRIVILEGES;
```

**Full init.sql after merge:**

```sql
-- Creates the cert database and grants the loa user access.
-- The auth database (loa_auth) is created by MYSQL_DATABASE env var.

CREATE DATABASE IF NOT EXISTS loa_cert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_cert.* TO 'loa'@'%';
FLUSH PRIVILEGES;

-- Consult database
CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%';
FLUSH PRIVILEGES;
```

**Existing volumes do NOT re-run init.** After merging, either recreate the volume (`down -v`, wipes all local data) or create the DB manually:

```bash
docker compose exec mysql mysql -uroot -proot-secret -e \
  "CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
   GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%'; \
   FLUSH PRIVILEGES;"
```

---

# 4. Shared Secrets Sync

Three values must be **byte-identical** across auth + cert + consult `.env` files for SSO to work:

| Variable | Auth `.env` value | Consult action |
|----------|-------------------|----------------|
| `JWT_SECRET` | `dev-only-secret-change-before-production` | Already matching (dev). Production: share same secret. |
| `ENCRYPTION_KEY` | `base64:aQ0GFg4Sb84QdlaGQc5wiS17VFPCWOvKQZJ+/bUCRYE=` | **MUST be added** to consult `.env` — currently empty. |
| `TENANT_SLUG` | `loa-consultation` | Own tenant (cert pattern); update consult `.env`. |

The root compose `environment:` block overrides `.env` at runtime (§2 includes `ENCRYPTION_KEY`). The assembly-local `.env` is used only for local `php artisan` commands outside Docker.

---

# 5. Port Map (root stack after consult wiring)

| Service | Host Port | Container Port | Purpose |
|---------|-----------|---------------|---------|
| seq | 5341 | 80 | Seq log UI |
| auth-nginx | 8080 | 80 | Auth API + web UI |
| cert-nginx | 9001 | 80 | Cert API |
| **consult-nginx** | **9002** | **80** | **Consult API** |
| mysql | 33060 | 3306 | MySQL (shared) |
| mailpit | 8025 | 8025 | Mailpit UI |
| mailpit | 1025 | 1025 | SMTP trap |

No port conflicts. Each app gets its own nginx with a unique host port.

---

# 6. Rollout Procedure

**Prerequisites:** root `docker-compose.yml` updated with §2 blocks; `docker/mysql/init.sql` updated with §3 lines.

1. `docker compose up -d --build consult-app consult-nginx consult-scheduler` — subset start (multi-app spec §4).
2. `docker compose exec consult-app php artisan migrate --force` — once first migration exists.
3. `docker compose exec consult-app php artisan test` — must stay green.
4. `curl http://localhost:9002/api/v1/health` → `{"status":"ok",...}`.
5. Cross-app smoke: `curl -H "Authorization: Bearer <token>" http://localhost:9002/api/v1/semesters` — JWT from auth-platform must validate locally (shared `JWT_SECRET`).

**Full reset (all three apps):**

```powershell
.\scripts\reset-all.ps1
```

---

# 7. Scripts Update Required

`scripts/reset-all.ps1` currently provisions auth + cert only. Must add consult:

| Step | Current | After consult wiring |
|------|---------|---------------------|
| Teardown | `docker compose down -v` (root + auth + cert) | Already covers root (consult is in root stack) |
| Rebuild | `docker compose up -d --build` | Starts all services including consult |
| Cache dirs | `auth-app` + `cert-app` | Add `consult-app` mkdir + chown |
| Migrate | auth + cert | Add `consult-app php artisan migrate --force` |
| Seed | auth + cert | **Skip** — consult has no seeds (§7 of data-model.md) |
| Swagger | auth + cert | Add `consult-app php artisan l5-swagger:generate` |
| Done message | Auth UI + Cert UI | Add `Consult API: http://localhost:9002` |

`scripts/build-all.ps1` — add consult to `$apps` array when consult has a `generate-dist.ps1`.

---

# 8. Acceptance Criteria

| # | Check | How |
|---|-------|-----|
| 1 | Starts from root compose | `docker compose up -d --build` — all services healthy |
| 2 | Migrates via exec | `docker compose exec consult-app php artisan migrate --force` |
| 3 | Starts independently | `docker compose up -d consult-app consult-nginx consult-scheduler` (without auth/cert) |
| 4 | Health check | `curl localhost:9002/api/v1/health` → 200 |
| 5 | JWT cross-validates | Auth-issued token accepted by consult (shared `JWT_SECRET`) |
| 6 | SSO callback works | Auth encrypts → consult decrypts (shared `ENCRYPTION_KEY`) |
| 7 | No new infra | Only consult-nginx port 9002 added; shared mysql/mailpit/seq |
| 8 | No assembly-local compose | No `assemblies/loa-consult-platform/docker-compose.yml` |

---

## Document Control

- **Status:** Final v1.1 (tenant rename `loa` → `loa-consultation`, user-approved 2026-09-24)
- **Created:** 2026-09-18
- **Updated:** 2026-09-19 — Promoted to Final: verified root stack (auth+cert wired), corrected init.sql, added §4 shared secrets sync, §5 port map, §7 scripts update. Dockerfile identical to cert (GD/mbstring/intl). nginx identical to cert (50MB upload, fastcgi_pass consult-app:9000).
- **Blocked on:** first migration (compose edits land together with migratable code)
- **Next:** first migration → wire §2+§3 → verify §8 acceptance
