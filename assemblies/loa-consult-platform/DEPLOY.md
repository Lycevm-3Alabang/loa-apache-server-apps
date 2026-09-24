# LOA Consult Platform — Deployment Guide (cPanel)

**Version:** 1.0
**Status:** Final (user-approved 2026-09-24; backend built + green — deploys on approval)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/DEPLOY.md` as pattern. Backend green 2026-09-23 (120 passed, migrations 000001–000014).

---

## 1. Target

- cPanel hosting · PHP 8.3 (platform-pinned — verify against `docker/php/Dockerfile`) · MySQL 8 · Laravel 12
- Subdomain: `aces-api.lyceumalabang.edu.ph` → document root `public/`
- Database: `loa_consult` (`utf8mb4_unicode_ci`; own cPanel user, credentials deploy-time only, never committed)
- Tenant: `loa-consultation` (own-tenant cert pattern; Auth re-provisioning required — see `consult-readiness.md` Final v1.6)

## 2. Prerequisites

- [ ] Server PHP 8.3 + extensions parity check (compare with `docker/php/Dockerfile`)
- [ ] MySQL database `loa_consult` + user provisioned (all privileges on that DB)
- [ ] `public/.htaccess` Authorization-forward rule present (else all gated routes 401 in prod while passing locally)
- [ ] `storage/` + `bootstrap/cache/` writable
- [ ] Auth side ready: tenant `loa-consultation` active, `aces-*` groups + 104+5 catalog/grants imported, secrets shared out-of-band (`consult-readiness.md` D-1–D-5)

## 3. Environment

```env
APP_NAME="LOA Consult Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aces-api.lyceumalabang.edu.ph

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=loa_consult
DB_USERNAME=[cpanel-user]
DB_PASSWORD=[deploy-time-only]

JWT_SECRET=[byte-identical-with-auth]
JWT_ACCESS_TTL=15
ENCRYPTION_KEY=[byte-identical-with-auth]
ENCRYPTION_KEY_PREVIOUS=[rotation-or-empty]
TENANT_SLUG=loa-consultation
AUTH_BASE_URL=https://auth.lyceumalabang.edu.ph
REFRESH_COOKIE=loa_connect_refresh
REFRESH_COOKIE_SECURE=true
REFRESH_COOKIE_TTL=10080

MAIL_*=[deploy-time mail values; local uses mailpit per `.env.example`]
```

- Secret-parity check (both sides, never commit values): in `php artisan tinker` (or Auth equivalent) compare `config('consult-platform.tenant_slug')` vs Auth `tenants.slug`, and confirm `JWT_SECRET`/`ENCRYPTION_KEY` decrypt + validate round-trip via one SSO callback before opening traffic.
- `.env` / `.env.cpanel` stay gitignored (Laravel default); template above only.

## 4. Dist packaging

- [x] 2026-09-23 — `generate-dist.ps1` landed (cert twin + `_stage` exclusion); wired into `scripts/build-all.ps1`, `dump.ps1 -Target consult`, `mega.ps1` Step 5.

Build (cert pattern, consult paths):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\loa\loa-apache-server-apps\assemblies\loa-consult-platform\generate-dist.ps1" -Path "D:\builds"
```

- Output: `D:\builds\loa-consult-platform-dist\` + zip, ready for cPanel File Manager upload.
- `vendor/` strategy: prebuilt upload (pure-PHP deps) per cert no-terminal-deploy notes; re-run `composer install --no-dev --optimize-autoloader` on-server only if terminal is available.

## 5. Database deploy

- Fresh install: create DB/user → `php artisan migrate --force` → NO seed (consult ships no seeders per `docker-compose-spec.md` §7) → `php artisan config:cache`.
- Updates: `php artisan migrate --force` only; rollback = redeploy previous dist (migrations carry `down()` per spec CON-5; destructive rollbacks need a recorded plan first).
- Collation: `utf8mb4_unicode_ci` (matches `init.sql`).

## 6. Post-deploy verification

- [ ] `GET /api/v1/health` → 200 `{status:ok, service:loa-consult-platform}`
- [ ] Gated route tokenless → 401 (proves `.htaccess` forwards `Authorization`); wrong-tenant token → 403 `tenant_mismatch`; valid token → 200/403 by level
- [ ] SSO callback → refresh rotation → logout E2E against Auth (proves `ENCRYPTION_KEY` + `TENANT_SLUG=loa-consultation` match)
- [ ] Cron: `* * * * * php /home/<user>/loa-consult-platform/artisan schedule:run >> /dev/null 2>&1`

### Slug consistency checklist (mirrors cert DEPLOY §2)

| # | Layer | Env / Config | Value |
|---|-------|-------------|-------|
| 1 | Auth DB `tenants.slug` | Auth provisioning (`consult-readiness.md` D-1) | `loa-consultation` |
| 2 | Consult backend | `TENANT_SLUG` env | `loa-consultation` |
| 3 | Consult frontend (cutover) | `NEXT_PUBLIC_CONSULT_TENANT_SLUG` env | `loa-consultation` |
| 4 | Auth `redirect_origins` | tenant record | includes `https://aces.lyceumalabang.edu.ph` |

## 7. Anti-patterns

- Do not commit `.env` or secrets to version control.
- Do not run `composer install` without `--no-dev` in production.
- Do not seed production (no seeders exist; any prod rows come from import endpoints or Auth provisioning).
- Do not use the Docker stack for production.
- Do not skip the §6 verification matrix before opening traffic.

---

## Document Control

- **Status:** Final v1.0 (user-approved 2026-09-24; cert-patterned, consult values)
