# LOA SSO Setup — Auth, Cert, and Frontend Configuration

**Version:** 1.0
**Status:** Final
**Audience:** Developers, DevOps, Platform Admins

---

## 1. Architecture Overview

Three platforms cooperate for SSO authentication:

```
┌─────────────────────┐     ┌─────────────────────┐     ┌─────────────────────┐
│   Auth Platform     │     │   Cert Platform      │     │   e-cert SPA        │
│   (PHP/Laravel)     │     │   (PHP/Laravel)      │     │   (Next.js/Vercel)  │
│                     │     │                       │     │                     │
│  auth.lyceumalabang │     │  cert-api.lyceumalabang│    │  e-cert.vercel.app  │
│  .edu.ph            │     │  .edu.ph              │     │                     │
└─────────┬───────────┘     └─────────┬─────────────┘     └─────────┬───────────┘
          │                           │                             │
          │   1. User visits SSO      │                             │
          │   2. Auth encrypts JWT    │                             │
          │   3. Redirect →#payload=  │                             │
          │                           │                             │
          │   4. SPA forwards payload │                             │
          │   5. Cert decrypts JWT    │                             │
          │   6. Cert sets cookie     │                             │
          │   7. Returns access_token │                             │
          │                           │                             │
          │   Shared secrets:         │                             │
          │   JWT_SECRET (HS256)      │                             │
          │   ENCRYPTION_KEY (AES)    │                             │
```

---

## 2. SSO Login Flow (step by step)

1. **User clicks "Login"** in e-cert SPA → browser navigates to:
   ```
   https://auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app
   ```

2. **Auth Platform authenticates** the user (username/password form).

3. **Auth encrypts** the JWT payload using AES-256-GCM with the shared `ENCRYPTION_KEY`.

4. **Auth redirects** back to the e-cert origin with an encrypted blob in the URL fragment:
   ```
   https://e-cert.vercel.app#payload=<base64url-encoded-AES-256-GCM-blob>
   ```

5. **e-cert SPA extracts** `#payload=` from the URL hash and POSTs it to:
   ```
   POST https://e-cert.vercel.app/api/v1/auth/callback
   Content-Type: application/json

   { "payload": "<base64url encoded blob>" }
   ```
   (Vercel rewrites this to `cert-api.lyceumalabang.edu.ph/api/v1/auth/callback`)

6. **Cert Platform decrypts** the payload using the same `ENCRYPTION_KEY`, validates the JWT, checks `tenant.slug === CERT_TENANT_SLUG`.

7. **Cert Platform sets** an httpOnly refresh cookie (`loa_cert_refresh`) and returns an access token.

8. **SPA stores** the access token in memory (never localStorage). Subsequent API calls include `Authorization: Bearer <token>`.

---

## 3. Tenant Slug — The One Rule

The tenant slug **`loa-e-cert`** must be identical in **all four** places:

| # | Layer | Where to set it | Failure if wrong |
|---|-------|----------------|------------------|
| 1 | **Auth DB** `tenants.slug` | `cpanel-auth-db-install.sql` or Admin UI | SSO login: "Access denied" (tenant not resolved from `?redirect=`) |
| 2 | **Cert Platform** backend | `CERT_TENANT_SLUG` env | **403 Forbidden** on `POST /api/v1/auth/callback` |
| 3 | **Auth Platform** middleware | `TENANT_SLUG` env | 403/500 on guarded auth-api routes |
| 4 | **e-cert SPA** (frontend) | `NEXT_PUBLIC_CERT_TENANT_SLUG` env | Token silently rejected → login redirect loop |

**Rule: change the slug in all four places, then re-login** (access tokens live 15 min).

---

## 4. Shared Secrets

Two secrets must match between Auth and Cert platforms:

### 4.1 JWT_SECRET (HS256 signing)

| Platform | Env var | Config file |
|----------|---------|-------------|
| Auth | `JWT_SECRET` | `config/jwt.php` |
| Cert | `JWT_SECRET` | `config/jwt.php` |

Generate once: `openssl rand -hex 32`. Use the **same value** on both platforms.

### 4.2 ENCRYPTION_KEY (AES-256-GCM SSO payload)

| Platform | Env var | Config file |
|----------|---------|-------------|
| Auth | `ENCRYPTION_KEY` | `config/auth-web.php` |
| Cert | `ENCRYPTION_KEY` | `config/auth-platform.php` |

Generate once: `openssl rand -hex 32`. Use the **same value** on both platforms.

**Key rotation:** set `ENCRYPTION_KEY_PREVIOUS` on both platforms during rotation grace period. Decryption tries current key first, then previous key.

> **e-cert SPA does NOT need these secrets.** The SPA only parses the JWT payload (base64url decode) — it never verifies signatures or decrypts.

---

## 5. Environment Variables

### 5.1 Auth Platform (`assemblies/loa-auth-platform/.env`)

| Variable | Production value | Required |
|----------|-----------------|----------|
| `APP_URL` | `https://auth.lyceumalabang.edu.ph` | Yes |
| `JWT_SECRET` | shared HMAC key | Yes |
| `JWT_ACCESS_TTL` | `15` | No (default) |
| `JWT_REFRESH_TTL` | `10080` | No (default) |
| `ENCRYPTION_KEY` | shared AES key (64 hex chars) | Yes |
| `ENCRYPTION_KEY_PREVIOUS` | — | Optional |
| `TENANT_SLUG` | `loa-e-cert` | Yes |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://aces-api.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |
| `CACHE_STORE` | `file` | Yes |
| `SESSION_DRIVER` | `file` | Yes |
| `SESSION_SECURE` | `true` | Yes |
| `ENCRYPTION_KEY` | same as Cert | Yes |

### 5.2 Cert Platform (`assemblies/loa-cert-platform/.env`)

| Variable | Production value | Required |
|----------|-----------------|----------|
| `APP_URL` | `https://cert-api.lyceumalabang.edu.ph` | Yes |
| `JWT_SECRET` | **same as Auth** | Yes |
| `ENCRYPTION_KEY` | **same as Auth** | Yes |
| `ENCRYPTION_KEY_PREVIOUS` | — | Optional |
| `CERT_TENANT_SLUG` | `loa-e-cert` | Yes |
| `CERT_ORGANIZATION_ID` | `00000000-0000-0000-0000-000000000001` | Yes |
| `CERT_REFRESH_COOKIE` | `loa_cert_refresh` | Yes |
| `CERT_REFRESH_COOKIE_SECURE` | `true` | Yes |
| `CERT_REFRESH_COOKIE_TTL` | `10080` | Yes |
| `AUTH_BASE_URL` | `https://auth.lyceumalabang.edu.ph` | Yes |
| `CORS_ALLOWED_ORIGINS` | `https://auth.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Yes |

### 5.3 e-cert SPA (Vercel env or `.env.local`)

| Variable | Value | Required |
|----------|-------|----------|
| `NEXT_PUBLIC_CERT_API_URL` | `https://cert-api.lyceumalabang.edu.ph` | Yes |
| `NEXT_PUBLIC_AUTH_URL` | `https://auth.lyceumalabang.edu.ph` | Yes |
| `NEXT_PUBLIC_CERT_TENANT_SLUG` | `loa-e-cert` | Yes |

> **e-cert does NOT need** `JWT_SECRET`, `ENCRYPTION_KEY`, or any server-side secrets.

---

## 6. Local Development

### 6.1 Docker stack

```bash
# From workspace root
docker compose up -d --build

# Auth: http://localhost:8080
# Cert: http://localhost:9001
# Mailpit: http://localhost:8025
```

### 6.2 Local env differences

| Setting | Production | Local |
|---------|-----------|-------|
| `APP_URL` (auth) | `https://auth.lyceumalabang.edu.ph` | `http://localhost:8080` |
| `APP_URL` (cert) | `https://cert-api.lyceumalabang.edu.ph` | `http://localhost:9001` |
| `SESSION_SECURE` | `true` | `false` |
| `CERT_REFRESH_COOKIE_SECURE` | `true` | `false` |
| `NEXT_PUBLIC_CERT_API_URL` | `https://cert-api.lyceumalabang.edu.ph` | `http://localhost:9001` |

### 6.3 Local SSO flow test

1. Visit `http://localhost:8080/sso/login?redirect=http://localhost:9001`
2. Enter credentials → redirects to `http://localhost:9001#payload=<encrypted>`
3. Extract `#payload=` from hash, POST to `http://localhost:9001/api/v1/auth/callback`
4. Response gives access token + sets refresh cookie
5. `POST http://localhost:9001/api/v1/auth/refresh` — returns new access token
6. `POST http://localhost:9001/api/v1/auth/logout` — 204, clears cookie

---

## 7. Auth DB Installer SQL

The file `assemblies/loa-auth-platform/database/sql/cpanel-auth-db-install.sql` is a generated one-file installer for `lyceumalabang_auth_db`. It provisions:

- Full schema (all migrations)
- `auth` tenant (the auth platform itself)
- `loa-e-cert` tenant (production origins: `https://e-cert.vercel.app`)
- 56-endpoint catalog (48 Cert + 8 Auth)
- `cert-admin`, `cert-staff`, `cert-user` groups
- 99-row grant matrix
- JWT permission-key claims
- Default admin account (`Admin123!` — change after first login)

**Import via phpMyAdmin** instead of `migrate` + `seed`. After import, run cert-side steps only (organization row).

---

## 8. Common Failure Modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| **403 on `POST /api/v1/auth/callback`** | Tenant slug mismatch: DB has `e-cert`, config expects `loa-e-cert` | Update `tenants.slug` to `loa-e-cert` in DB; or update `CERT_TENANT_SLUG` env |
| **"Access denied" on SSO login** | `?redirect=` origin not in tenant `redirect_origins` | Add the origin to the tenant's `redirect_origins` in Auth DB |
| **Token silently rejected in SPA** | `NEXT_PUBLIC_CERT_TENANT_SLUG` doesn't match JWT `tenant.slug` | Set `NEXT_PUBLIC_CERT_TENANT_SLUG=loa-e-cert` in Vercel env |
| **`ENCRYPTION_KEY` crash on login** | Placeholder text instead of hex key | Generate: `openssl rand -hex 32`, set in both Auth + Cert `.env` |
| **419 Page Expired on login** | Session cookie not persisting between GET/POST | Set `SESSION_SECURE=false` locally, `true` in production |
| **Refresh cookie not sent** | `CERT_REFRESH_COOKIE_SECURE=true` over HTTP | Set `CERT_REFRESH_COOKIE_SECURE=false` locally |
| **CORS error** | Origin not in `CORS_ALLOWED_ORIGINS` | Add the origin to the env var on the **target** platform |
| **`addToGroup` 422 error** | No `user_tenants` pivot for the user | Auto-pivot fix committed (branch `fix/addToGroup-auto-pivot`) |

---

## 9. Deployment Checklist

### Auth Platform
1. [ ] `JWT_SECRET` set (same value on Cert)
2. [ ] `ENCRYPTION_KEY` set (same value on Cert)
3. [ ] `TENANT_SLUG=loa-e-cert`
4. [ ] `CORS_ALLOWED_ORIGINS` includes `https://e-cert.vercel.app`
5. [ ] `tenants.slug = 'loa-e-cert'` in DB (not `e-cert`)
6. [ ] Cert endpoint catalog imported (48 endpoints)
7. [ ] Cert groups created (`cert-admin`, `cert-staff`, `cert-user`)
8. [ ] Grants applied per `cert-readiness.md` §7.4

### Cert Platform
1. [ ] `JWT_SECRET` matches Auth
2. [ ] `ENCRYPTION_KEY` matches Auth
3. [ ] `CERT_TENANT_SLUG=loa-e-cert`
4. [ ] `AUTH_BASE_URL=https://auth.lyceumalabang.edu.ph`
5. [ ] `CERT_REFRESH_COOKIE_SECURE=true`
6. [ ] Organization row seeded (`CERT_ORGANIZATION_ID`)

### e-cert SPA (Vercel)
1. [ ] `NEXT_PUBLIC_CERT_TENANT_SLUG=loa-e-cert`
2. [ ] `NEXT_PUBLIC_AUTH_URL=https://auth.lyceumalabang.edu.ph`
3. [ ] Vercel rewrite: `/api/v1/:path*` → `https://cert-api.lyceumalabang.edu.ph/api/v1/:path*`

---

## 10. References

| Doc | Location | What it covers |
|-----|----------|----------------|
| Auth DEPLOY.md | `assemblies/loa-auth-platform/DEPLOY.md` | Auth platform deploy steps |
| Cert DEPLOY.md | `assemblies/loa-cert-platform/DEPLOY.md` | Cert platform deploy steps |
| cert-readiness.md | `assemblies/loa-auth-platform/cert-readiness.md` | Cert tenant provisioning runbook |
| FRONTEND-INTEGRATION.md | `assemblies/loa-cert-platform/FRONTEND-INTEGRATION.md` | SPA integration guide |
| environment.md | `assemblies/loa-auth-platform/environment.md` | Auth local + production env |
| legacy-e-cert-integration.md | `assemblies/loa-cert-platform/legacy-e-cert-integration.md` | Full retrofit spec |
| api-endpoints.md | `assemblies/loa-cert-platform/api-endpoints.md` | Cert API surface + catalog |
