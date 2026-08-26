# Cert Readiness (Deploy-Time Provisioning)

## Product Assembly Component Specification

**Version:** 0.5
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — operational provisioning runbook
**Audience:** Architects, Engineers, AI Development Agents, Platform Admins

> Companion to `tenant-endpoint-catalog.md`, `tenant-group-endpoint-grants.md`, `web-ui.md`, `access-config-import-export.md`, and `DEPLOY.md`.
>
> **Decision (2026-08-06):** Cert readiness data is **not** baked into the **production** Auth seed path (`DatabaseSeeder` production runs only `AdminSeeder`; `database/seeders/database.sql` is untouched). In production it is provisioned **manually at deploy-time** via the admin UI / JSON API. For the **local Docker stack only**, `DatabaseSeeder` additionally runs `LocalCertReadinessSeeder` (guarded by `!app()->environment('production')`) — see §8. This runbook is the authoritative checklist and payload for that provisioning.

---

## 1. Purpose

It answers:

> **"What exactly must be provisioned in the Auth Platform so the Certificate Platform (`e-cert`) can authenticate users and enforce its endpoint grants?"**

After the Auth Platform is deployed (`DEPLOY.md`), a platform admin provisions the `loa-e-cert` tenant and the Cert access model once. Until this runbook is completed, `e-cert` SSO and Cert endpoint enforcement will not work.

---

## 2. Scope & Ownership

### Owns
- The `loa-e-cert` tenant record (`slug`, `app_url`, `redirect_origins`).
- The Cert endpoint catalog import (48 endpoints, Appendix A of `api-endpoints.md`).
- The `cert-admin` / `cert-staff` / `cert-user` groups and their endpoint grants.

### Does Not Own
- The Cert API surface itself (declared in `assemblies/loa-cert-platform/api-endpoints.md`).
- The local Cert catalog mirror `config/cert-endpoints.php` (a Cert-side deployment artifact, §9.5 of `api-endpoints.md`).
- Assigning actual users to the Cert groups (operational, ongoing).

---

## 3. Prerequisites

1. Auth Platform deployed and reachable at `https://auth.lyceumalabang.edu.ph` (see `DEPLOY.md`).
2. All migrations applied (`000001`–`000023`), including `tenant_app_endpoints`, `tenant_endpoint_grants`, `tenant_endpoint_overrides`, `group_claims`, `user_claim_overrides`, `claims`, `route_policies`.
3. A platform admin account (`loa-auth-admin` group) exists and is signed in to `/admin`.
4. The `e-cert` front-end is reachable at `https://e-cert.vercel.app` (its origin is the SSO redirect target and the tenant `redirect_origin`).

> **Local development?** Skip §3–§7 against production and follow §8 (Docker Compose) instead — same tenant/catalog/groups/grants, run locally.

> **Shortcut (fresh-database deploys):** `database/sql/cpanel-auth-db-install.sql` pre-provisions the schema, the `loa-e-cert` tenant (production origins), the 56-endpoint catalog, all four groups, the 99-row grant matrix, and the JWT permission-key claims in one phpMyAdmin import. If you use it, steps §4–§7 are already done — skip to §9 verification. See `docs/cpanel-db-migration-runbook.md` for the full fresh-database path.

---

## 4. Step 1 — Create the `loa-e-cert` tenant

**Admin UI:** `Admin Dashboard → Tenants → Create Tenant` (`/admin/tenants/create`).

`redirect_origins` is a **comma-separated** field on the form (parsed by `WebAdminController::tenantsStore`).

| Field | Value |
|-------|-------|
| `slug` | `loa-e-cert` (immutable after creation) |
| `name` | `LOA Certificate Platform` |
| `app_url` | `https://e-cert.vercel.app` |
| `redirect_origins` | `https://e-cert.vercel.app` |
| `status` | `active` (default) |

> **Why this matters:** `web-ui.md` §3 resolves the tenant context from `?redirect=` against the tenant's `redirect_origins`. Tenant rows are the only redirect allowlist — `AUTH_ALLOWED_REDIRECTS` was retired in `unified-auth-flow.md` §0 D7 (P3). The Cert SSO flow is:
> `https://auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app` → `https://e-cert.vercel.app#payload=...` → `POST /api/v1/auth/callback`.

### 4.1 Slug distribution checklist (all four must match)

The tenant slug is validated **independently at every layer** — a mismatch at any one of them causes hard failures (403 at SSO callback, token rejection in the SPA, or `Tenant not configured` 500s). When provisioning a tenant (or renaming, which is only possible before first issuance), set the slug in **all four** places:

| # | Layer | Where | Failure mode if stale |
|---|-------|-------|------------------------|
| 1 | Tenants table | `tenants.slug` (Admin UI §4 or installer SQL) | SSO login: "Access denied" (tenant not resolved from `?redirect=`) |
| 2 | Tenant app backend validator | Cert Platform `config/cert-platform.php` → `CERT_TENANT_SLUG` env (`AuthCallbackController` payload check) | **403 Forbidden** on `POST /api/v1/auth/callback` |
| 3 | Auth API middleware (optional per-app gate) | `TENANT_SLUG` env wherever `jwt.tenant` middleware is applied | 403 / 500 on guarded auth-api routes |
| 4 | SPA | e-cert `NEXT_PUBLIC_CERT_TENANT_SLUG` (client-side JWT parse check in `src/lib/auth/jwt.ts`) | Token silently rejected → login redirect loop |

> Rule of thumb: **one slug, four homes.** Change them together, then re-login to refresh any tokens minted under the old slug (access TTL 15 min).

---

## 5. Step 2 — Import the Cert endpoint catalog

Import the 48 guarded Cert endpoints (the full payload below) for the `loa-e-cert` tenant. This populates `tenant_app_endpoints`.

### 5.1 Admin UI
`Admin Dashboard → Tenants → loa-e-cert → Endpoints → Import` (`/admin/tenants/{tenant}/endpoints/import`): paste the JSON below with **Replace** checked.

### 5.2 JSON API
`POST /api/v1/admin/tenants/{tenant}/endpoints/bulk` (`jwt.auth` + `users.manage`), `{ "replace": true, "endpoints": [...] }`.

### 5.3 Payload (Appendix A of `api-endpoints.md`)

```json
{
  "replace": true,
  "endpoints": [
    { "method": "GET",    "path": "/api/v1/events",                       "label": "List events",                       "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events",                       "label": "Create event",                      "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}",                  "label": "Get event",                         "required_level": "read" },
    { "method": "PATCH",  "path": "/api/v1/events/{id}",                  "label": "Update event",                      "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/events/{id}",                  "label": "Delete event",                      "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}/stats",            "label": "Event statistics",                 "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/clone-template",   "label": "Clone certificate template",        "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/clone-email-template", "label": "Clone email template",        "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/bulk-issue",       "label": "Bulk issue certificates",           "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/reissue",          "label": "Reissue certificates for event",    "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/events/{id}/revoke-expired",   "label": "Count expired certificates",        "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/revoke-expired",   "label": "Revoke expired certificates",       "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/events/{id}/issue-completed",  "label": "Issue certificates for completed",  "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}/attendees",        "label": "List event attendees",              "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/attendees",        "label": "Add attendee",                      "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/attendees/import", "label": "Import attendees",                "required_level": "write" },
    { "method": "PATCH",  "path": "/api/v1/attendees/{id}",               "label": "Update attendee",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/attendees/{id}",               "label": "Delete attendee",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/attendees/{id}/with-cert",     "label": "Delete attendee with certificate",   "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/attendees/{id}/delete-preview","label": "Attendee delete preview",           "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/attendees/{id}/file-data",     "label": "Attendee certificate source file",   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/templates",                    "label": "List templates",                    "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/templates",                    "label": "Create template",                   "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/templates/{id}",               "label": "Get template",                      "required_level": "read" },
    { "method": "PATCH",  "path": "/api/v1/templates/{id}",               "label": "Update template",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/templates/{id}",               "label": "Delete template",                   "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates",                 "label": "Issue certificate",                 "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates/bulk",            "label": "Bulk issue certificates",           "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates/upload",          "label": "Upload certificate file",           "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/certificates",                 "label": "List certificates",                 "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}",            "label": "Get certificate",                   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/pdf",        "label": "Certificate PDF (inline)",          "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/download",   "label": "Certificate PDF (download)",         "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/revoke",     "label": "Revoke certificate",                "required_level": "admin" },
    { "method": "DELETE", "path": "/api/v1/certificates/{id}",            "label": "Delete certificate",                "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/email",      "label": "Send certificate email",            "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/email-logs", "label": "Certificate email logs",            "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/reissue",    "label": "Reissue certificate",               "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/certificates/expire",          "label": "Expire certificates",               "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/certificates/qr",              "label": "Certificate QR code",               "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/certificates",              "label": "My certificates",                   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/certificates/{id}",         "label": "My certificate",                    "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/events",                    "label": "My authored events",                "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/templates",                 "label": "My authored templates",             "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/dashboard/stats",              "label": "Dashboard statistics",              "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/dashboard/activity",           "label": "Dashboard activity feed",           "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/admin/audit-logs",             "label": "Query audit logs",                  "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/admin/audit-logs/export",      "label": "Export audit logs",                 "required_level": "admin" }
  ]
}
```

> `verify/{certificate_number}` and `view/{id}` are **public** Cert routes — no catalog entry, no grant, no JWT. The `auth/*` proxy routes are also public and not cataloged.

---

## 6. Step 3 — Create the Cert groups

Create three groups, each **scoped to the `loa-e-cert` tenant** (`tenant_id` = the tenant's UUID). Groups are created tenant-scoped via the admin UI under the tenant (`/admin/tenants/{tenant}/groups`).

| Group | Description | Priority |
|-------|-------------|----------|
| `cert-admin` | Certificate platform administrator | 2 |
| `cert-staff` | Certificate platform staff | 3 |
| `cert-user` | Certificate platform participant | 4 |

> **Priority semantics** (`tenant-group-endpoint-grants.md` §3): lower number = higher precedence. `loa-auth-admin` is priority `1`. When a user is in multiple groups, the highest-precedence group's grant wins on each endpoint. The three Cert groups must not be re-used for any other LOA app (Q-4 resolution).

---

## 7. Step 4 — Grant levels to the groups

Grant each group its level on the cataloged endpoints (populates `tenant_endpoint_grants`).

### 7.1 Admin UI
`Admin Dashboard → Tenants → loa-e-cert → Groups → {group} → Endpoints` (`/admin/tenants/{tenant}/groups/{group}/endpoints/manage`).

### 7.2 JSON API
`POST /api/v1/admin/tenants/{tenant}/groups/{group}/endpoints` (`jwt.auth` + `users.manage`), one call per (method, path):

```json
{ "method": "GET", "path": "/api/v1/events", "level": "read" }
```

### 7.3 Grant rules

- **cert-admin** — `admin` on **every** cataloged path (bypasses the Cert owner rule, `api-endpoints.md` §9.6).
- **cert-staff** — each endpoint at its **`required_level`** (`read` → `read`, `write` → `write`); **no grants** on `admin`-level paths.
- **cert-user** — `read` on the **7 participant paths only**, all **GET**:
  `/api/v1/me/certificates`, `/api/v1/me/certificates/{id}`, `/api/v1/certificates/{id}`, `/api/v1/certificates/{id}/pdf`, `/api/v1/certificates/{id}/download`, `/api/v1/events/{id}`, `/api/v1/certificates/qr`.
  **Explicitly excluded:** `/api/v1/dashboard/stats` and `/api/v1/dashboard/activity` (org-wide, unscoped — ownership note, `api-endpoints.md` §5.7).

### 7.4 Grant matrix (48 rows)

| Method | Path | Required level | cert-admin | cert-staff | cert-user |
|--------|------|----------------|------------|------------|-----------|
| GET | /api/v1/events | read | admin | read | — |
| POST | /api/v1/events | write | admin | write | — |
| GET | /api/v1/events/{id} | read | admin | read | read |
| PATCH | /api/v1/events/{id} | write | admin | write | — |
| DELETE | /api/v1/events/{id} | write | admin | write | — |
| GET | /api/v1/events/{id}/stats | read | admin | read | — |
| POST | /api/v1/events/{id}/clone-template | write | admin | write | — |
| POST | /api/v1/events/{id}/clone-email-template | write | admin | write | — |
| POST | /api/v1/events/{id}/bulk-issue | write | admin | write | — |
| POST | /api/v1/events/{id}/reissue | admin | admin | — | — |
| GET | /api/v1/events/{id}/revoke-expired | read | admin | read | — |
| POST | /api/v1/events/{id}/revoke-expired | admin | admin | — | — |
| POST | /api/v1/events/{id}/issue-completed | write | admin | write | — |
| GET | /api/v1/events/{id}/attendees | read | admin | read | — |
| POST | /api/v1/events/{id}/attendees | write | admin | write | — |
| POST | /api/v1/events/{id}/attendees/import | write | admin | write | — |
| PATCH | /api/v1/attendees/{id} | write | admin | write | — |
| DELETE | /api/v1/attendees/{id} | write | admin | write | — |
| DELETE | /api/v1/attendees/{id}/with-cert | admin | admin | — | — |
| GET | /api/v1/attendees/{id}/delete-preview | read | admin | read | — |
| GET | /api/v1/attendees/{id}/file-data | read | admin | read | — |
| GET | /api/v1/templates | read | admin | read | — |
| POST | /api/v1/templates | write | admin | write | — |
| GET | /api/v1/templates/{id} | read | admin | read | — |
| PATCH | /api/v1/templates/{id} | write | admin | write | — |
| DELETE | /api/v1/templates/{id} | write | admin | write | — |
| POST | /api/v1/certificates | write | admin | write | — |
| POST | /api/v1/certificates/bulk | write | admin | write | — |
| POST | /api/v1/certificates/upload | write | admin | write | — |
| GET | /api/v1/certificates | read | admin | read | — |
| GET | /api/v1/certificates/{id} | read | admin | read | read |
| GET | /api/v1/certificates/{id}/pdf | read | admin | read | read |
| GET | /api/v1/certificates/{id}/download | read | admin | read | read |
| POST | /api/v1/certificates/{id}/revoke | admin | admin | — | — |
| DELETE | /api/v1/certificates/{id} | admin | admin | — | — |
| POST | /api/v1/certificates/{id}/email | write | admin | write | — |
| GET | /api/v1/certificates/{id}/email-logs | read | admin | read | — |
| POST | /api/v1/certificates/{id}/reissue | admin | admin | — | — |
| POST | /api/v1/certificates/expire | admin | admin | — | — |
| GET | /api/v1/certificates/qr | read | admin | read | read |
| GET | /api/v1/me/certificates | read | admin | read | read |
| GET | /api/v1/me/certificates/{id} | read | admin | read | read |
| GET | /api/v1/me/events | read | admin | read | — |
| GET | /api/v1/me/templates | read | admin | read | — |
| GET | /api/v1/dashboard/stats | read | admin | read | — |
| GET | /api/v1/dashboard/activity | read | admin | read | — |
| GET | /api/v1/admin/audit-logs | admin | admin | — | — |
| GET | /api/v1/admin/audit-logs/export | admin | admin | — | — |

**Totals:** cert-admin **48**, cert-staff **39**, cert-user **7**.

> `—` = no grant row for that group on that endpoint (access denied via the closed-by-default catalog matching in `ClaimPolicyMiddleware`).

---

## 8. Local Development (Docker Compose)

This section covers running the same provisioning **locally** against the Docker Compose stack (`docker-compose.yml`, `environment.md` §2) — e.g. to develop the `e-cert` Cert app against a local Auth before the production deploy. The logical steps are identical to §4–§7; only the base URL, the origins, and the tooling differ.

### 8.1 Bring up the stack

```powershell
docker compose up -d --build
docker compose exec auth-app composer install          # first run only
docker compose exec auth-app php artisan migrate
docker compose exec auth-app php artisan db:seed       # admin user (env ADMIN_*) + `loa-auth-admin` group; local-only: also runs `LocalCertReadinessSeeder` (see §8.3)
```

- App: `http://localhost:8080` (nginx on `:8080`; the app container itself has no host port).
- MySQL: DB `loa_auth`, user `loa`/`loa-secret`; host port `33060` (do **not** connect to `3306` from the host).
- Mailpit: SMTP `:1025` (from the app), web UI `http://localhost:8025`.
- Artisan runs inside the app container — **no host PHP/Composer** is required (`environment.md` §2).

### 8.2 Local env differences that matter

| Setting | Production | Local |
|---------|-----------|-------|
| `APP_URL` | `https://auth.lyceumalabang.edu.ph` | `http://localhost:8080` |
| `SESSION_SECURE` | `true` | `false` (plain HTTP) |
| `CORS_ALLOWED_ORIGINS` | includes `https://e-cert.vercel.app` | **must also include** the e-cert origin you are testing against (see below) |
| `AUTH_ALLOWED_REDIRECTS` | *(removed)* | **Retired** (`unified-auth-flow.md` §0 D7) — redirect origins live on tenant rows only |

The tenant-level `redirect_origins` (set in §8.3 / §4) must always list the **actual e-cert UI origin** used for SSO:

- Testing against the deployed Vercel e-cert: `https://e-cert.vercel.app` — no change from production.
- Testing against a **local e-cert dev server** (e.g. Next.js on `http://localhost:3000`): add that origin to the tenant `redirect_origins` and to `CORS_ALLOWED_ORIGINS`. Unlisted origins are rejected (Origin-middleware + SSO redirect validation).

After editing `.env` (CORS/redirects), refresh the config cache inside the container:

```powershell
docker compose exec auth-app php artisan config:clear
docker compose exec auth-app php artisan config:cache
```

### 8.3 Provision the Cert readiness data locally

**Automatic fast path — `LocalCertReadinessSeeder` (recommended).** When you run `php artisan db:seed` in the local Docker stack (`APP_ENV != production`), `DatabaseSeeder` automatically runs `LocalCertReadinessSeeder` (`assemblies/loa-auth-platform/database/seeders/LocalCertReadinessSeeder.php`). It creates the **`loa-e-cert`** tenant pinned to UUID `91128f0a-df85-47a9-ae1d-5298904dacd5` (`app_url` = `http://localhost:9001` matching the `cert-nginx` host port; `redirect_origins` = `http://localhost:3000` for e-cert SSO — preserved on re-runs, never clobbered), the groups **`cert-admin`** (priority 2), **`cert-staff`** (3), **`cert-user`** (4), and the JWT permission-key claims `users.view` + `users.manage` on `cert-admin`. Idempotent (`updateOrCreate`) — safe to re-run. Covered by `tests/Feature/Seeders/LocalCertReadinessSeederTest.php`.

> **Note:** the tenant slug is **`loa-e-cert`** everywhere — local and production use the same slug, and it is immutable after issuance. It must match e-cert's `NEXT_PUBLIC_CERT_TENANT_SLUG`. The seeder does **not** create the endpoint catalog or grants (steps 2 and 4 below) — those still require the admin UI (or the §5.3 import).

The production steps §4–§7 apply unchanged, run against the **local** admin UI at `http://localhost:8080/admin` (sign in with the seeded admin: `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`):

1. **Tenant** (§4): `Tenants → Create Tenant`; `slug = loa-e-cert`, `name` = `Local Cert App`, `app_url` = `http://localhost:9001`, `redirect_origins` = `http://localhost:3000` (the e-cert dev origin). Skip this step if you ran `db:seed` — `LocalCertReadinessSeeder` already created it.
2. **Catalog** (§5): `Tenants → loa-e-cert → Endpoints → Import` and paste the 48-endpoint Appendix A JSON (same payload as §5.3).
3. **Groups** (§6): `Tenants → loa-e-cert → Groups`; create `cert-admin` (priority 2), `cert-staff` (3), `cert-user` (4). Skip this step if you ran `db:seed` — the seeder already created them.
4. **Grants** (§7): `Tenants → loa-e-cert → Groups → {group} → Endpoints`; apply §7.4.
5. **JWT claims**: ensure `group_claims` rows exist for `cert-admin` (`users.view`, `users.manage`) — without them tokens never carry permission keys and `jwt.permission:*` always returns 403. Skip if you ran `db:seed` or imported `cpanel-auth-db-install.sql`; both seed them.

**Manual tinker fast path (alternative to the seeder)** — an operator creates the tenant + groups by pasting a one-liner into an interactive `php artisan tinker` session:

```php
$t = App\Models\Tenant::updateOrCreate(
    ['id' => '91128f0a-df85-47a9-ae1d-5298904dacd5'],
    ['slug' => 'loa-e-cert', 'name' => 'Local Cert App', 'status' => 'active',
     'app_url' => 'http://localhost:9001',
     'redirect_origins' => ['http://localhost:3000']]
);
foreach (['cert-admin' => 2, 'cert-staff' => 3, 'cert-user' => 4] as $n => $p) {
    App\Models\UserGroup::updateOrCreate(['tenant_id' => $t->id, 'name' => $n],
        ['description' => 'Certificate platform ' . ($n === 'cert-user' ? 'participant' : ($n === 'cert-staff' ? 'staff' : 'administrator')), 'priority' => $p]);
}
$admin = App\Models\UserGroup::where('tenant_id', $t->id)->where('name', 'cert-admin')->first();
foreach (['users.view', 'users.manage'] as $k) {
    App\Models\GroupClaim::updateOrCreate(['group_id' => $admin->id, 'claim_key' => $k], ['scope_type' => 'none']);
}
```

**What the snippet does and does not do:**

| | Tinker snippet | `LocalCertReadinessSeeder` (via `db:seed`) | Admin UI (steps 1–4) |
|---|---|---|---|
| Creates the `loa-e-cert` tenant | ✅ | ✅ | ✅ |
| Creates `cert-admin` / `cert-staff` / `cert-user` (empty groups) | ✅ | ✅ | ✅ |
| Seeds `cert-admin` JWT claims (`users.*`) | ✅ | ✅ | ❌ — still step 5 |
| Imports the 48-endpoint catalog (§5.3 payload) | ❌ — still step 2 | ❌ — still step 2 | ✅ step 2 |
| Applies the grant matrix (§7.4) | ❌ — still step 4 | ❌ — still step 4 | ✅ step 4 |
| Runs automatically? | ❌ — operator pastes once, interactively | ✅ — on every local `db:seed` (non-prod only) | ❌ — operator clicks once |

The tinker snippet is a **manual, one-time operator action** that merely types the same data the tenant/group forms would POST. The **seeder** is the sanctioned automatic local path (non-prod only, idempotent). Neither is part of the **production** seed pipeline: `DatabaseSeeder` runs `AdminSeeder` everywhere and `LocalCertReadinessSeeder` only when `APP_ENV != production`; `database/seeders/database.sql` is untouched. That separation is the 2026-08-06 decision — in production, Cert readiness data is provisioned by an operator, never baked into the seed pipeline.

### 8.4 Local verification

Repeat §9 against the local base URL:

1. **Catalog validate:** `http://localhost:8080/admin/tenants/{tenant}/endpoints/validate` → `valid: true`, no "no group grants" warnings.
2. **Access config export** must list the 48 endpoints and the three groups.
3. **Per-user check:** `GET http://localhost:8080/api/v1/auth/access` (JWT) per group.
4. **SSO redirect test:** `http://localhost:8080/sso/login?redirect=<e-cert origin>` must authenticate and redirect to the origin (not reject). Cert-side callback consumption is out of scope here (Cert app, Phase C).

---

## 9. Step 5 — Verify

1. **Catalog validate:** `Admin Dashboard → Tenants → loa-e-cert → Endpoints → Validate` (`/admin/tenants/{tenant}/endpoints/validate`). Expect `valid: true`; **no** "no group grants" warnings (all 48 endpoints have at least the `cert-admin` grant).
2. **Access config export:** `Admin Dashboard → Tenants → loa-e-cert → Access Config → Export` should list the 48 cataloged endpoints and the three groups with their grants (idempotent check against §5.3 + §7.4).
3. **Per-user check:** for a sample user in each group, call `GET /api/v1/auth/access` (JWT) and confirm the `permissions` claim matches the §7.4 row set for that group.
4. **SSO redirect test:** from a clean browser hit `https://auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app` — must authenticate and redirect to the e-cert origin (not reject for unknown origin). The Cert-side callback (`POST /api/v1/auth/callback`) and JWT claim (`tenant.slug = loa-e-cert`) must validate (see §4.1 slug checklist; `assemblies/loa-cert-platform/api-endpoints.md` §9).

---

## 10. References

| Spec | Relationship |
|------|--------------|
| `assemblies/loa-cert-platform/api-endpoints.md` | Authoritative Cert API surface; Appendix A = the catalog payload; §4.4 = role → grant guidance; §9 = SSO/auth flow. |
| `assemblies/loa-auth-platform/tenant-endpoint-catalog.md` | Catalog vocabulary + bulk import semantics. |
| `assemblies/loa-auth-platform/tenant-group-endpoint-grants.md` | Grant levels + group-priority resolution. |
| `assemblies/loa-auth-platform/web-ui.md` | Tenant `redirect_origins` resolution; SSO flow. |
| `assemblies/loa-auth-platform/access-config-import-export.md` | Export/import used for verification. |
| `assemblies/loa-auth-platform/environment.md` | Local environment (§2) — the Docker Compose stack used by §8. |
| `assemblies/loa-auth-platform/DEPLOY.md` | Deployment; this runbook runs after deploy. |
| `assemblies/loa-auth-platform/SESSION-PROMPT.md` | Tracker; Phase B (Cert readiness) = this runbook. |

---

## 11. Doc Control

| Version | Date | Change |
|---------|------|--------|
| 0.1 | 2026-08-06 | Initial runbook (Draft). Decision: no baked-in seeder; manual deploy-time provisioning. Grant matrix derived from `api-endpoints.md` §4.4 + Appendix A. |
| 0.1 | 2026-08-06 | **Promoted to Final** (payload + grant matrix parity-checked against `api-endpoints.md` Appendix A: 48/48). |
| 0.2 | 2026-08-06 | Added §8 **Local Development** (Docker Compose provisioning via the local admin UI at `localhost:8080` + optional tinker fast path; local origin/CORS/redirect table; local verification). References + Doc Control renumbered to §9–§11. |
| 0.2 | 2026-08-06 | §8.3 tinker fast path clarified: explicit "what it does / does not do" table (tenant+groups only; catalog+grants still via admin UI; operator-pasted one-liner, no seeder file, not in `DatabaseSeeder`/`database.sql`). |
| 0.3 | 2026-08-07 | §8.3 now documents the **`LocalCertReadinessSeeder`** as the automatic local path (runs on local `db:seed` via `DatabaseSeeder`, non-prod guard only; creates `cert-app` tenant @ `localhost:9001` + groups). Decision note + §8.1 comment + "does/does not" table + closing paragraph updated. Production provisioning remains manual-only. |
| 0.4 | 2026-08-07 | §8.3 local steps use the **`cert-app`** tenant slug consistently (admin UI steps 1–4 + tinker snippet now target `cert-app` @ `http://localhost:9001`, matching `LocalCertReadinessSeeder`). |
| 0.5 | 2026-08-24 | **Tenant slug unified to `loa-e-cert`** (immutable; single slug for local + production, matches e-cert `NEXT_PUBLIC_CERT_TENANT_SLUG`). Tenant pinned to UUID `91128f0a-df85-47a9-ae1d-5298904dacd5` in seeder/installer/SQL. §8.3 rewritten: seeder no longer invents a separate local slug and never clobbers existing `redirect_origins`; added step 5 — JWT permission-key claims (`group_claims`) seeding; `cpanel-auth-db-install.sql` now seeds `group_claims`. Supersedes the 0.4 split-slug approach. |

### Open Questions
- None.
