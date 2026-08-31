# LOA Cert Platform — Frontend Integration Guide

**Version:** 1.1.0 (2026-08-11) — SSO entry point is now live; full E2E SSO unblocked
**Status:** Ready for Phase D (e-cert auth swap)

This document is the handoff from the Cert Platform backend to the e-cert frontend. It tells you exactly what's implemented, what you need to build, and how to test.

---

## What's Done (Backend)

### Auth Endpoints (Public — No JWT Required)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/callback` | `POST` | SSO callback — decrypts payload, validates JWT, sets httpOnly refresh cookie, returns access token |
| `/api/v1/auth/refresh` | `POST` | Token refresh — reads httpOnly cookie, proxies to Auth platform, rotates cookie, returns new access token |
| `/api/v1/auth/logout` | `POST` | Logout — clears refresh cookie, proxies logout to Auth platform, returns 204 |

### Domain Endpoints (JWT-Gated)

**All 48 catalog endpoints are live** and enforce `jwt.auth` + `jwt.endpoint` middleware (verified against `routes/api.php`). See `authenticated-endpoints-spec.md` for the full list with required levels.

### Middleware

- `jwt.auth` — validates JWT signature, type (`access`), expiry, and tenant claim (`tenant.slug = loa-e-cert`)
- `jwt.endpoint` — checks `permissions` claim against local endpoint catalog (level-based, closed-by-default)

### Configuration

| Config | Key | Default |
|--------|-----|---------|
| `config/jwt.php` | `secret` | `dev-only-secret-change-before-production` |
| `config/jwt.php` | `access_ttl` | `15` (minutes; per `token-lifecycle.md` access tokens stay short-lived) |
| `config/jwt.php` | `algo` | `HS256` |
| `config/cert-platform.php` | `tenant_slug` | `loa-e-cert` |
| `config/cert-platform.php` | `refresh_cookie` | `loa_cert_refresh` |
| `config/cert-platform.php` | `refresh_cookie_ttl` | `10080` (minutes = 7 days) |
| `config/auth-platform.php` | `base_url` | `https://auth.lyceumalabang.edu.ph` |
| `config/auth-platform.php` | `http_timeout` | `5` (seconds) |

---

## What You Need to Build (Phase D)

### 1. SSO Fragment Handler

```
auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app
  → redirects to
https://e-cert.vercel.app#payload=<AES-256-GCM encrypted blob>
```

> **Live (2026-08-11):** `GET /sso/login`, `POST /sso/login`, `GET /sso/register`, `POST /sso/register`, `GET /redirect` all implemented and tested in the auth platform. The encrypted payload uses AES-256-GCM; the fragment handler extracts `#payload=` from the URL hash.

**Your job:** Extract `#payload=` from the URL hash, send it to `POST /api/v1/auth/callback`.

### 2. Auth Callback Flow

```
POST /api/v1/auth/callback
Content-Type: application/json

{
  "payload": "<base64url encoded AES-256-GCM blob>"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": { "id": "...", "email": "...", "name": "..." },
    "tenant": { "id": "...", "slug": "loa-e-cert" }
  }
}
```

**Your job:** Store `access_token` in memory (never localStorage). The `loa_cert_refresh` cookie is set httpOnly — you can't read it, but the browser sends it automatically.

### 3. In-Memory Token Store

- Hold `access_token` in a React context / Zustand / similar
- Attach `Authorization: Bearer <token>` to every API request
- Never persist to localStorage/sessionStorage/cookies (XSS risk)
- Token expires in 15 minutes — use silent refresh before expiry

### 4. Silent Refresh

On app load (or before first API call if token is expired):

```
POST /api/v1/auth/refresh
```

No body needed — the browser sends the `loa_cert_refresh` cookie automatically.

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

**Response (401):** Refresh failed — redirect to SSO login.

### 5. Logout

```
POST /api/v1/auth/logout
```

No body needed. Returns 204. Clear the in-memory token and redirect to SSO.

### 6. Client Auth Guard

- Check if in-memory token exists and is not expired
- If expired, attempt silent refresh
- If refresh fails, redirect to SSO login
- Route guard is client-side only (no server-side rendering)

### 7. Permission-Based UI Gating

Parse the JWT `permissions` claim to determine UI role:

```typescript
// Decode JWT payload (no verification needed — backend validates)
const payload = JSON.parse(atob(token.split('.')[1]));
const permissions: string[] = payload.permissions || [];

// Role mapping (from web-ui.md §5)
const isAdmin = permissions.some(p => p.startsWith('admin:'));
const isStaff = permissions.some(p => p.startsWith('write:') || p.startsWith('read:'));
const isUser = permissions.some(p => p.startsWith('read:'));

// Or check specific paths
const canCreateEvents = permissions.some(p => p === 'write:/api/v1/events');
```

---

## Environment Variables

```
NEXT_PUBLIC_CERT_API_URL=https://cert-api.lyceumalabang.edu.ph
NEXT_PUBLIC_AUTH_URL=https://auth.lyceumalabang.edu.ph
NEXT_PUBLIC_CERT_TENANT_SLUG=loa-e-cert
```

---

## Vercel Rewrite (keeps refresh cookie same-origin)

```json
{
  "rewrites": [
    {
      "source": "/api/v1/:path*",
      "destination": "https://cert-api.lyceumalabang.edu.ph/api/v1/:path*"
    }
  ]
}
```

---

## Testing

### Local Development

```bash
# Start Cert API
docker compose up cert-app cert-nginx

# Cert API available at http://localhost:9001
# Auth API available at http://localhost:8080
```

### Test Auth Flow

> **SSO entry point is now live (2026-08-11).** You can exercise the full E2E SSO flow locally:

1. Visit `http://localhost:8080/sso/login?redirect=http://localhost:9001`
2. Enter LOA credentials → redirects to `http://localhost:9001#payload=<encrypted blob>`
3. Extract `#payload=` from the hash, send to `POST http://localhost:9001/api/v1/auth/callback`
4. Response gives access token + sets refresh cookie
5. `POST http://localhost:9001/api/v1/auth/refresh` (cookie auto-sent) — returns a new access token
6. `POST http://localhost:9001/api/v1/auth/logout` — 204, clears cookie
7. Use the token for all subsequent API calls

### Test Protected Endpoint

```bash
# Get a valid JWT (from callback response or generate one)
curl -H "Authorization: Bearer <token>" http://localhost:9001/api/v1/events
```

---

## Reference Docs

| Doc | What It Tells You |
|-----|-------------------|
| `api-endpoints.md` (v1.5) | Full endpoint list, auth contract, data models |
| `authenticated-endpoints-spec.md` (v1.1) | Endpoint list with required levels (quick reference) |
| `legacy-e-cert-integration.md` (v2.2) | Full retrofit spec — SSO flow, session model, decommission plan |
| `web-ui.md` | Frontend spec — SSO handling, permission→role mapping (superseded by `legacy-e-cert-integration.md` §7.4) |
| `FRONTEND-INTEGRATION.md` | This file |

---

## Status

- ✅ Backend auth endpoints live (callback, refresh, logout)
- ✅ `jwt.auth` + `jwt.endpoint` middleware enforced
- ✅ All 48 catalog endpoints live + 2 public endpoints
- ✅ 126 tests, 386 assertions, all green
- ✅ Auth platform SSO entry (`/sso/login`, `/sso/register`, `/redirect`) — **live and tested**
- 🚀 **Ready for Phase D — e-cert auth swap (CSR)**
