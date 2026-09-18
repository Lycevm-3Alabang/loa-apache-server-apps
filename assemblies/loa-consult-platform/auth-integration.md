# LOA Consult Platform — Auth Integration
## Product Assembly Component Specification

**Version:** 1.2
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

On callback, upsert local `users` by `email` (name from claims). Application fields (`departmentId`, `course`, `employeeNo`, `isDisabled`) stay local. Drop at data migration: `passwordHash`, `tokenVersion`, `hasLoggedInBefore`, `group_access`, `user_permissions`, `role`, `userrole`, `password_reset_tokens` (+ NextAuth tables). Detail in `data-model.md`.

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

Auth surface only. Controllers/services copy 1:1; only config keys, cookie names, and the tenant slug change. The single functional delta is #3 (consult keeps a slim local `users` table; cert has none).

| # | Cert source | Consult target | Delta |
|---|-------------|----------------|-------|
| 1 | `app/Http/Middleware/JwtMiddleware.php` | same path | `tenant_slug` → `consult-platform`/`loa`; `cert_user` attr → `consult_user`; error shapes verbatim (401 missing/invalid, 403 tenant_mismatch) |
| 2 | `app/Http/Middleware/EndpointPolicyMiddleware.php` | same path | catalog `cert-endpoints.php` → `consult-endpoints.php`; confirm request-attr names (`jwt_claims`) at port time; else verbatim |
| 3 | `app/Http/Controllers/AuthCallbackController.php` | same path | config re-point; **ADD local user upsert by email** (slim users table); audit event under consult naming |
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

## Document Control

- **Status:** Final v1.2 (v1.0 promoted 2026-09-18; v1.1 added §10 port inventory; v1.2 group-wording: Auth owns all groups)
- **Created:** 2026-09-18
- **Next:** `data-model.md`, endpoint modules #2–#6
