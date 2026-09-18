# LOA Consult Platform — Docker Compose Wiring Spec

**Version:** 0.1
**Status:** Draft
**Layer:** Product Assembly (`loa-consult-platform`)

> Adds `consult-*` services to the canonical root stack (`docker-compose.yml`, project `loa-platform`). No assembly-local compose file — ever (`AGENT.md`, `docs/local-dev-multi-app-spec.md`).

---

# 1. Non-Goals

- No `assemblies/loa-consult-platform/docker-compose.yml` (duplicate-stack port conflicts).
- No new shared infrastructure (reuse `mysql`, `mailpit`, `seq`, network `loa`).
- No shared database (own `loa_consult` DB on the shared server).
- No new host ports beyond `9002` (consult-nginx).
- No `consult-queue` worker yet (no queued workloads; mail sends sync — add with Phase E email work if needed).

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
      TENANT_SLUG: loa
      AUTH_BASE_URL: http://auth-nginx
      SEQ_URL: "http://seq:5341"
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

Notes: `AUTH_BASE_URL=http://auth-nginx` (in-network name; host browsers use `:8080`, containers use the service name). `JWT_SECRET` dev-only until Auth deploy-time sharing. `docker/` build files already live in the assembly (mirroring cert/auth per-assembly `docker/` convention — build files are per-assembly, the RUNNING stack is root-only).

# 3. MySQL Init Addition (`docker/mysql/init.sql`)

```sql
CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%';
FLUSH PRIVILEGES;
```

Existing volumes do NOT re-run init: after merging, either recreate the volume (`down -v`, wipes all local data) or create the DB manually:

```sql
-- via root: docker compose exec mysql mysql -uroot -proot-secret -e "CREATE DATABASE IF NOT EXISTS loa_consult CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON loa_consult.* TO 'loa'@'%'; FLUSH PRIVILEGES;"
```

# 4. Rollout (USER runs, one step at a time)

1. Merge §2 blocks + §3 init lines.
2. `docker compose up -d --build consult-app consult-nginx consult-scheduler` (subset start, multi-app spec §4).
3. `docker compose exec consult-app php artisan migrate --force` (once migrations exist).
4. `docker compose exec consult-app php artisan test` (must stay green).
5. `curl http://localhost:9002/api/v1/health` → `{"status":"ok",...}`.
6. `scripts/reset-all.ps1` update — separate decision (script provisions auth+cert today; consult provisioning joins when it has migrations/seeds).

# 5. Acceptance (multi-app spec criteria)

1. Starts from root compose; 2. migrates via `exec consult-app`; 3. seeds via `exec consult-app`; 4. starts independently (`up consult-*` without auth/cert); 5. no new ports (only 9002), no new infra.

---

## Document Control

- **Status:** Draft v0.1 (2026-09-18)
- **Blocked on:** first migration (runbook wiring gate) — compose edits land together with migratable code, not before
- **Next:** promote alongside first migration; then wire + verify §4
