# CPANEL DATABASE MIGRATION RUNBOOK
## Fresh Database (Drop → Create → Seed) Path

**Version:** 0.1
**Status:** Final
**Scope:** Both Laravel assemblies — `loa-auth-platform` (`lyceumalabang_auth_db`) and `loa-cert-platform` (`lyceumalabang_e_cert`)
**Audience:** Engineers deploying to cPanel

> Governing context: each assembly's `DEPLOY.md`; auth provisioning runbook `cert-readiness.md` (Final v0.4); cross-boundary decisions in root `PROJECT_UPDATES.md`.

---

# 1. Question

> *"We might do a manual SQL drop and create, then seed. Can we do that?"*

**Yes — supported and often the cleanest path**, with one hard caveat: **seeding alone produces a non-functional platform.** Seeders create schema + the initial admin only. Everything that makes SSO and permissions work (tenants, redirect origins, endpoint catalog, groups, grant matrix) is intentionally *not* seeded in production (2026-08-06 decision) and must be provisioned afterwards per §6. The cert database additionally needs an `organizations` row before any event/template write succeeds (§6.3).

---

# 2. What Each Seeder Actually Creates (production)

| App | Command | Creates | Does NOT create |
|-----|---------|---------|-----------------|
| Auth | `php artisan migrate --force` + `db:seed --force` | Full schema; **admin user** from `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` (`AdminSeeder`). `LocalCertReadinessSeeder` is guarded to non-production | Tenants, `redirect_origins`, endpoint catalog, groups, grants, users |
| Cert | `php artisan migrate --force` + `db:seed --force` | Full schema only — `DatabaseSeeder::run()` is **empty** | Organizations (known FK gap), events, templates, certificates |

Consequence matrix if you stop right after seeding:

| Capability | Works? |
|------------|--------|
| Auth admin login at `/login` | ✅ |
| Any tenant-app SSO login | ❌ no tenant / `redirect_origins` |
| Cert API authenticated calls | ✅ (JWT validated against claims; no users table needed) |
| Creating events/templates in cert | ❌ FK 1452 — no organization row |

---

# 3. Prerequisites

1. **Backup whatever exists first** — even when you intend a fresh start. A drop is unrecoverable.
2. `.env` deployed and correct per each assembly's `DEPLOY.md` (DB names/users, `APP_KEY`, `JWT_SECRET`, `ENCRYPTION_KEY`, `ADMIN_*`).
3. Code + `vendor/` uploaded; PHP 8.3 CLI available (see DEPLOY.md "Server prerequisites").
4. Brief maintenance window — during the swap the apps must not accept writes.
5. For post-seed provisioning: access to the **local** environment to export an access-config JSON (§6.2 shortcut) and the `cert-readiness.md` runbook for the full manual path.

---

# 4. Procedure A — Drop & Create (cPanel)

1. cPanel → **MySQL Databases**: note existing DBs/users, then delete the databases `lyceumalabang_auth_db` and `lyceumalabang_e_cert` (deleting the DB does **not** delete the user).
2. Recreate both with the **exact previous names** (cPanel prefixes are already baked into these names).
3. Ensure **Add User To Database**: `lyceumalabang_auth_admin` → ALL PRIVILEGES on **both** databases.
4. Set collation on each new DB: `utf8mb4` / `utf8mb4_unicode_ci` (matches migrations; prevents mixed-collation errors later).

Equivalent keyboard-only variant (skips the panel entirely):

```bash
cd ~/loa-auth-platform && php artisan migrate:fresh --force --seed
cd ~/loa-cert-platform && php artisan migrate:fresh --force
```

`migrate:fresh` drops all tables in the configured database and re-runs migrations — same end state as the manual drop/create, minus the panel clicks. Use **one** of the two approaches, not both.

---

# 5. Procedure B — Migrate & Seed

```bash
# Auth
cd ~/loa-auth-platform
php artisan migrate --force
php artisan db:seed --force          # production guard: AdminSeeder only
php artisan config:cache

# Cert
cd ~/loa-cert-platform
php artisan migrate --force
php artisan db:seed --force          # no-op today (empty seeder)
php artisan config:cache
```

Verify with `php artisan migrate:status` — every migration shows **Ran**.

---

# 6. Post-Seed Provisioning Checklist

## 6.1 Auth — make SSO possible again

1. Log in at `/login` with the seeded admin.
2. **Create the tenant(s)** your consumer apps need (Tenants → Create): at minimum the `loa` tenant with `redirect_origins` including `https://e-cert.vercel.app` (full list in `cert-readiness.md`).
3. **Endpoint catalog**: import the 48-endpoint Appendix A payload (per `cert-readiness.md`).
4. **Groups + grants**: preferred — export the access-config JSON from your *local* admin UI (Access Config → Export, after `LocalCertReadinessSeeder` has run locally), then **Access Config → Import** with `confirm` on production. This reproduces `cert-admin`/`cert-staff`/`cert-user` + the 48-row grant matrix without hand-clicking. Manual click-through is the fallback per `cert-readiness.md`.
5. **Users**: none exist besides admin. Provision staff via admin-created activation emails, bulk import CSV, or self-registration is limited to LOA domains via `/sso/register`.

## 6.2 Why not raw-SQL the groups/grants?

The grant matrix spans four tables with composite keys and tenant scoping. The access-config export/import feature exists precisely to move it as validated JSON — round-tripping through hand-written INSERTs is an anti-pattern (§9).

## 6.3 Cert — organization row (mandatory)

Until a row exists matching the backend's configured organization, every event/template write fails with FK error 1452. Insert it once (phpMyAdmin → `lyceumalabang_e_cert` → SQL):

```sql
INSERT INTO organizations (id, name, slug, created_at, updated_at)
VALUES ('00000000-0000-0000-0000-000000000001', 'Lyceum of Alabang', 'loa', NOW(), NOW());
```

Adjust the UUID if `CERT_ORGANIZATION_ID` / `config('cert-platform.organization_id')` differs from the default. Long-term fix candidate: promote this insert into a production-safe seeder (tracked in `whats-next.md`).

Optionally import representative events/templates via the API/UI after this point — or leave empty and let staff build content.

---

# 7. Verification Checklist

| Check | Expected |
|-------|----------|
| `migrate:status` (both apps) | All **Ran**, none Pending |
| Auth `/login` with seeded admin | Dashboard loads |
| `/sso/login?redirect=<tenant origin>` with a real user | Redirects with payload (proves tenant + origin restored) |
| Cert: create event/template as `cert-staff` | 201 — proves organization row + grants |
| Forgot-password email arrives | Proves MAIL_* + fresh tokens fine |
| e-cert UI full loop | Login → dashboard → create template → issue certificate |

---

# 8. Rollback

Restore the pre-drop backup taken in §3:

```bash
mysql -u lyceumalabang_auth_admin -p lyceumalabang_auth_db < backup_auth_YYYYMMDD_HHMM.sql
mysql -u lyceumalabang_auth_admin -p lyceumalabang_e_cert < backup_cert_YYYYMMDD_HHMM.sql
```

(Export per-database backups before dropping — see auth `DEPLOY.md` "Backup & rollback".)

---

# 9. Anti-Patterns

| Anti-Pattern | Why It Violates |
|--------------|-----------------|
| Dropping without a prior backup | Irrecoverable — audit logs, certificates, users gone |
| Assuming `db:seed` restores tenants/groups/grants | Production seeders deliberately provision the admin only (2026-08-06 decision) |
| Hand-writing INSERTs for the grant matrix | Use access-config export/import — validated JSON round-trip |
| Creating the cert DB without `utf8mb4_unicode_ci` | Mixed collations break joins/comparisons later |
| Expecting cert writes to work before §6.3 | FK 1452 — organization row is mandatory |
| Running `LocalCertReadinessSeeder` expectations in prod | It is environment-guarded to non-production |
