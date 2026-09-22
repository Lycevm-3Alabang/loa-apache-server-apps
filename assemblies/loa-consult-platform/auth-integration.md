# LOA Consult Platform — Auth Integration
## Product Assembly Component Specification

**Version:** 1.4
**Status:** Final
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Mirrors the cert pattern (`authenticated-endpoints-spec.md`, `auth-proxy.md`, `FRONTEND-INTEGRATION.md`).
> Frontend untouched until cutover — this spec defines the Laravel target only.

---

# 1. Purpose

Answers:

> **"How does the Consult backend authenticate callers, and how does the Next.js frontend obtain and use tokens?"**

The Consult Platform **never issues tokens, stores passwords, or manages sessions**. It validates Auth-issued JWTs locally and proxies refresh/logout server-side.

---

# 2. SSO Flow (split-origin, cert pattern)

```
1. User visits https://aces.lyceumalabang.edu.ph (Next.js, Vercel)
2. No valid session → frontend redirects to:
   https://auth.lyceumalabang.edu.ph/sso/login?redirect=https://aces.lyceumalabang.edu.ph
3. User authenticates on Auth Platform (portal session, smart routing per unified-auth-flow.md)
4. Auth encrypts payload (AES-256-GCM) → redirects to:
   https://aces.lyceumalabang.edu.ph#payload=<encrypted_base64url>
5. Frontend JS extracts fragment → POST /api/v1/auth/callback { payload }
6. Laravel decrypts, validates JWT locally, sets httpOnly loa_connect_refresh cookie
7. Returns access token → frontend holds in memory only (never localStorage)
```

Browser-to-Laravel wiring (cutover decision — no frontend change now):

- Option A (recommended, cert-docs pattern): Vercel rewrite keeps the refresh cookie same-origin:
  ```json
  { "rewrites": [{ "source": "/api/v1/:path*", "destination": "https://aces-api.lyceumalabang.edu.ph/api/v1/:path*" }] }
  ```
- Option B (what e-cert actually does): no rewrite — e-cert's `vercel.json` is `{}` and its `next.config.ts` has no `rewrites()`; the frontend calls the API host directly cross-origin with CORS. The cert docs describe Option A, but e-cert code uses Option B.

The frontend team chooses at cutover; the Laravel contract (callback sets httpOnly cookie, refresh reads it) is unchanged either way. Cookie `Secure` + `SameSite` settings must match the chosen topology.

---

# 3. Auth Endpoints (public, throttled 10/min)

## `POST /api/v1/auth/callback`

| Field | Value |
|-------|-------|
| Request | `{ "payload": "<base64url AES-256-GCM blob>" }` |
| Steps | decode → decrypt (`ENCRYPTION_KEY`, rotation via `_PREVIOUS`) → reject stale `exp` → validate JWT (`JWT_SECRET`, HS256, `type=access`, `tenant.slug=loa`) → require `refresh_token` in payload → upsert local user by email → set httpOnly cookie (path `/api/v1/auth`, `SameSite=Lax`, `Secure` env-driven) → audit `auth.sso_callback` |
| Response 200 | `{ "status": "success", "data": { "access_token", "refresh_token", "token_type": "Bearer", "expires_in", "user": {id,email,name}, "tenant": {id,slug} } }` — user carries NO groups/permissions; those come from JWT claims + `GET /api/v1/auth/access` |
| Errors | 400 missing/tampered/stale payload or missing tokens · 401 invalid access token · 403 `{message: Forbidden, reason: tenant_mismatch}` |

## `POST /api/v1/auth/refresh`

Token from body (`refresh_token`) or `loa_connect_refresh` cookie (body preferred) → proxies `POST {AUTH_BASE_URL}/api/v1/auth/refresh` (5s timeout) → rotates cookie → returns `{ "status": "success", "data": { "access_token", "refresh_token", "token_type", "expires_in" } }`. Missing → 401; upstream failure → clear cookie + 401; upstream unreachable/incomplete → clear cookie + 502. (Cert `AuthRefreshController` behavior verbatim.)

## `POST /api/v1/auth/logout`

Token from body or cookie → best-effort `POST {AUTH_BASE_URL}/api/v1/auth/logout` (failures ignored) → clear cookie (path `/api/v1/auth`) → 204 always.

---

# 4. JWT Validation (local, no HTTP per request)

- `jwt.auth`: HS256 signature (`JWT_SECRET`), `type=access`, `exp`, `tenant.slug === "loa"` (shared `loa` tenant — distinct from cert's `loa-e-cert`).
- `jwt.endpoint`: match `(method, path)` against local catalog mirror (`config/consult-endpoints.php`, `{param}`-aware, closed-by-default) → compare JWT `permissions` `<level>:<path>` ordinal ≥ required.
- Claims: `{ sub, email, name, groups[], permissions[], tenant:{id,slug}, type, iat, exp }` (15-min access, 7-day refresh).

---

# 5. Middleware & Config (build time)

- `app/Http/Middleware/JwtMiddleware.php` + `EndpointPolicyMiddleware.php` (copy cert, re-point tenant config).
- `config/jwt.php` (secret, TTLs), `config/auth-platform.php` (base_url, timeout, encryption key + previous for rotation, api key), `config/consult-platform.php` (`tenant_slug=loa`, `refresh_cookie=loa_connect_refresh`, `refresh_cookie_secure=true`, `refresh_cookie_ttl=10080`).
- `config/consult-endpoints.php` — `public[]` + `catalog[]` generated from `api-endpoints.md` §5 (the Auth bulk-import payload).
- `routes/api.php` — `auth/*` public; everything else in `['jwt.auth','jwt.endpoint']`.
- `/service/*` Auth proxy (users/groups/members) deferred: need decided in the admin module spec; if needed, mirrors cert `auth-proxy.md` with `AUTH_API_KEY` (`X-Api-Key`).

---

# 6. Shared Secrets & Env

| Variable | Requirement |
|----------|-------------|
| `JWT_SECRET` | Byte-identical Auth ↔ Consult (HMAC). Never commit; verify via tinker post-deploy. |
| `ENCRYPTION_KEY` (+ `_PREVIOUS` for rotation) | Byte-identical (AES-256-GCM payload). |
| `AUTH_BASE_URL` | Auth origin for refresh/logout proxy. |
| `AUTH_API_KEY` | Server-side only; only if `/service/*` proxy is built (admin module spec decides). |
| `TENANT_SLUG=loa` | Must match Auth `tenants.slug`. Distinct from cert's `loa-e-cert` — do not copy cert `.env` verbatim. |
| `REFRESH_COOKIE=loa_connect_refresh`, `REFRESH_COOKIE_TTL=10080` | Cookie name/TTL (httpOnly, `SameSite=Lax`, path `/api/v1/auth`; `Secure` env-driven — off for plain-http local dev). |

---

# 7. User Data Sync

On callback, upsert first-class domain entities by `email` from JWT claims per `data-model.md` §3.1.1 (`students`) and §3.1.2 (`employees`) — **no `app_users` cache**. Auth Platform is the sole identity authority (concepts: Identity Kernel); consult MUST NOT mirror or duplicate it (`data-model.md` §1.3, §4). Name from claims refreshes the local first-class cache; domain attributes (`student_number`, `course_id`, `employee_number`, `department_id`, `is_active`) stay local. Drop at data migration: legacy `app_users` (entire table — `passwordHash`/`tokenVersion`/`hasLoggedInBefore` go with it), plus `group_access`, `user_permissions`, `role`, `userrole`, `password_reset_tokens` (+ NextAuth tables). Detail in `data-model.md`.

---

# 8. Auth Platform Provisioning (deploy-time, manual — cert-readiness pattern)

1. Tenant `loa` exists; `redirect_origins` includes `https://aces.lyceumalabang.edu.ph`.
2. Groups `ADMIN`, `DEAN`, `FACULTY`, `STUDENT` — created and assigned in Auth only (tenant-scoped; pipe-roles → multi-group). Consult stores no membership.
3. Catalog import from §5 payload (118 gated rows).
4. Grants per `api-endpoints.md` §4.4 (+ e-consultation `endpoint-catalog.md` §5 levels).
5. Secrets shared out-of-band.

---

# 9. Testing Checklist (cutover)

SSO redirect · decrypt · local JWT validate · tenant-slug gate · refresh rotation · logout clear+revoke · group scenarios (ADMIN-group full · DEAN-group read-no-destroy · FACULTY-group availability+consultations · STUDENT-group book+evaluate; groups Auth-owned) · closed-by-default 403 · override precedence · `.htaccess` Authorization live-check · secret parity check.

---

# 10. Implementation Inventory (port verbatim from cert)

Auth surface only. Controllers/services copy 1:1; only config keys, cookie names, and the tenant slug change. The single functional delta is #3 (consult upserts first-class `students`/`employees` by email; cert has no local user table).

| # | Cert source | Consult target | Delta |
|---|-------------|----------------|-------|
| 1 | `app/Http/Middleware/JwtMiddleware.php` | same path | `tenant_slug` → `consult-platform`/`loa`; `cert_user` attr → `consult_user`; error shapes verbatim (401 missing/invalid, 403 tenant_mismatch) |
| 2 | `app/Http/Middleware/EndpointPolicyMiddleware.php` | same path | catalog `cert-endpoints.php` → `consult-endpoints.php`; confirm request-attr names (`jwt_claims`) at port time; else verbatim |
| 3 | `app/Http/Controllers/AuthCallbackController.php` | same path | config re-point; **ADD first-class upsert by email** (`students`/`employees` per `data-model.md` §3.1.1–§3.1.2; no `app_users`); audit event under consult naming |
| 4 | `app/Http/Controllers/AuthRefreshController.php` | same path | cookie/config names only |
| 5 | `app/Http/Controllers/AuthLogoutController.php` | same path | cookie/config names only |
| 6 | `app/Services/JWTService.php` | same path | verbatim (HS256 validate-only) |
| 7 | `app/Services/EncryptionService.php` | same path | verbatim (AES-256-GCM + `_PREVIOUS` rotation) |
| 8 | `app/Services/AuditLogger.php` | same path | verbatim (writes consult `audit_logs`) |
| 9 | `config/jwt.php` | same path | verbatim |
| 10 | `config/auth-platform.php` | same path | verbatim (base_url, timeout, encryption keys, api_key) |
| 11 | `config/cert-platform.php` | `config/consult-platform.php` | `tenant_slug=loa`, `refresh_cookie=loa_connect_refresh` (+secure/TTL); DROP `organization_id`, `log_viewer_secret`, `use_metadata_serving` (cert-only) |
| 12 | `routes/api.php` auth group | same | `auth/*` public, `throttle:10,1` on callback/refresh |
| 13 | `bootstrap/app.php` aliases | same | `jwt.auth` + `jwt.endpoint` |
| 14 | `public/.htaccess` | same | Authorization-forward rule (AI-GUIDE cPanel gotcha) |

DO NOT PORT: `AuthProxyController.php` (deferred with the `/service/*` decision, §5); `CertUserChecker.php` (cert-domain activation emails, hardcodes `cert-user` — consult activation is Auth-owned); all domain controllers/services/mail/storage (certificates, events, attendees, PDF/QR).

---

# 11. Port Plan (sequenced landing)

> Plan only — no code lands until this spec returns to Final (Rule 0).

**Preconditions (verified 2026-09-22):** cert sources exist for all §10 items · consult stubs + `jwt.auth`/`jwt.endpoint` aliases present · `public/.htaccess` Authorization rule present · `Student`/`Employee` models exist (callback upsert target) · tests = `TestCase` + `HealthTest` only; `app/Services/` empty; no auth config files.

| Step | Files | Delta | Tests (user-run, `php artisan test`) |
|------|-------|-------|--------------------------------------|
| 1. Config trio | `config/jwt.php`, `config/auth-platform.php` (verbatim) · `config/consult-platform.php` (from cert's: `tenant_slug=loa`, `loa_connect_refresh`, drop cert-only keys) | None — no behavior change | `HealthTest` green |
| 2. Services trio | `app/Services/{JWTService,EncryptionService,AuditLogger}.php` | Verbatim; Audit writes consult `audit_logs` | Indirect (exercised via steps 3–6) |
| 3. `JwtMiddleware` | Replace stub | Tenant/attr re-point; error shapes verbatim | 401 missing · 401 invalid/expired/wrong-type · 403 tenant mismatch · valid passes |
| 4. Catalog | `config/consult-endpoints.php` | Generated from `api-endpoints.md` §5: `public[]` + 118 gated rows | Validated via step 5 |
| 5. `EndpointPolicyMiddleware` | Replace stub | `consult-endpoints.php`; confirm `jwt_claims` attr at port time | Public passes tokenless · unknown path 403 (closed-by-default) · level-denied 403 · level-sufficient passes |
| 6. Auth trio | `AuthCallback/Refresh/LogoutController` | Cookie/config names; callback ADDS first-class `students`/`employees` upsert (§7, no `app_users`) | Callback 400/401/403/200+cookie+upsert+audit · Refresh 200/401/502 · Logout 204 always + cookie cleared |
| 7. Gate routes | `routes/api.php`: `auth/*` public + throttle 10/min; everything else under `['jwt.auth','jwt.endpoint']` (health stays public) | Current open routes become gated | Health public · semesters/admin 401 tokenless · full suite green |

**Test rules:** one behavior per test · `RefreshDatabase` · JWT claims helper, never hardcoded tokens · request-level over mocks · suites sequential only · `HealthTest` green after every step.

**Risks:** `DatabaseSeeder` is BROKEN (refs missing `User`) — tests must avoid seeding or fix seeder first (out of scope unless it blocks; flag at step 6) · test secrets (`JWT_SECRET`/`ENCRYPTION_KEY`) must exist in `phpunit.xml.dist`/`.env.testing` — verify at step 1.

---

## Document Control

- **Status:** Final v1.4 (history: v1.0 promoted 2026-09-18; v1.1 §10 port inventory; v1.2 group-wording; v1.3 §7/§10 #3 no-`app_users`; v1.4 §11 port plan — user-approved Final 2026-09-22, gates Step 1+)
- **Created:** 2026-09-18
- **Next:** auth-layer port per §10–§11 (Step 1 config ✓ HealthTest green; Step 2 services trio ✓ HealthTest green; Step 3 `JwtMiddleware` next — awaiting user yes)
