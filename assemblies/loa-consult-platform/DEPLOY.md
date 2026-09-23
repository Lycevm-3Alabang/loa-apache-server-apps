# LOA Consult Platform — Deployment Guide (cPanel)

**Version:** 0.1
**Status:** Draft (skeleton — details deferred until build)
**Layer:** Product Assembly (`loa-consult-platform`)

> Follows `../loa-cert-platform/DEPLOY.md` as pattern. Nothing here is executable yet — no code, no dist. Each section lands with the build.

---

## 1. Target (planned)

- cPanel hosting · PHP 8.2+ · MySQL 8 · Laravel 12
- Subdomain: `aces-api.lyceumalabang.edu.ph` → document root `public/`
- Database: `loa_consult` (own user; credentials deploy-time only, never committed)

## 2. Prerequisites (TODO at build)

- [ ] Server PHP version + extensions parity check (list TBD)
- [ ] MySQL database + user provisioned
- [ ] `.htaccess` Authorization-forward rule verified in `public/` (AI-GUIDE gotcha — else all gated routes 401)
- [ ] `storage/` + `bootstrap/cache/` writable permissions recipe (copy from cert DEPLOY at build)

## 3. Environment (template only)

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
ENCRYPTION_KEY=[byte-identical-with-auth]
ENCRYPTION_KEY_PREVIOUS=[rotation-or-empty]
TENANT_SLUG=loa
AUTH_BASE_URL=https://auth.lyceumalabang.edu.ph
REFRESH_COOKIE=loa_connect_refresh
REFRESH_COOKIE_TTL=10080

MAIL_*=[copy MAIL table from cert DEPLOY at build]
```

- [ ] Secret-parity verification procedure (tinker both sides — copy from AI-GUIDE/cert runbook at build)
- [ ] `.env.cpanel` gitignored template (generate at build)

## 4. Dist packaging (TODO)

- [x] 2026-09-23 — `generate-dist.ps1` twin landed (cert pattern + `_stage` exclusion); wired into `scripts/build-all.ps1`
- [ ] `vendor/` strategy: prebuilt upload (pure-PHP deps) per cert no-terminal-deploy notes

## 5. Database deploy (TODO)

- [ ] Fresh-install recipe: create → migrate `--force` → seed (seeds nothing in production per data-model §7)
- [ ] Migrate-only recipe for updates; rollback plan
- [ ] `loa_consult` collation: `utf8mb4_unicode_ci`

## 6. Post-deploy verification (TODO)

- [ ] `GET /api/v1/health` → 200
- [ ] `curl -H "Authorization: Bearer test-token"` on gated route → 401 shape (proves header passes), then real-token 403/200 matrix
- [ ] SSO callback → refresh rotation → logout E2E against Auth
- [ ] Backup/rollback recipe (copy cert structure at build)

---

## Document Control

- **Status:** Draft v0.1 skeleton (2026-09-18) — every section deferred to build; no executable content yet
