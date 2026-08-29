# Auth Proxy — Server-Side Auth Platform Proxy

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-cert-platform`) — service proxy surface
**Audience:** Architects, Engineers, AI Development Agents

> Cert-api proxies user, group, and membership requests from the frontend
> to the Auth Platform server-side.  The frontend calls one backend (cert-api)
> for everything — no direct calls to the auth platform.

---

## 1. Purpose

Answers:

> **"How does the cert frontend access user management without calling
> auth-platform directly?"**

The frontend previously called auth-platform at `/auth-api/v1/*` via a
Next.js rewrite.  This exposed the auth platform directly to the browser,
requiring the frontend to manage two base URLs and two auth mechanisms.

The auth proxy moves all auth-platform communication server-side.
The frontend calls cert-api's `/api/v1/service/*` endpoints.  Cert-api
forwards the request to auth-platform using either the caller's JWT
or a configured API key.

---

## 2. Ownership

### Owns

- Proxy routing between cert-api and auth-platform.
- API key storage and configuration (`AUTH_API_KEY` env var).
- Request forwarding logic (JWT pass-through vs API key injection).
- Upstream response relay (status codes, JSON body).

### Does Not Own

- User entity — owned by auth-platform (`IdentityService`).
- Group entity — owned by auth-platform (`AuthorizationService`).
- Tenant member management — owned by auth-platform (`TenantMemberApiController`).
- JWT authentication — owned by auth-platform (`JwtMiddleware`).
- API key authentication — owned by auth-platform (`ApiKeyAuthMiddleware`).
- Frontend user management UI — owned by e-cert frontend.

---

## 3. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `tenant-app-api.md` (auth-platform) | Upstream endpoints consumed by proxy — `/tenant/members` CRUD |
| `FRONTEND-INTEGRATION.md` (cert-platform) | Existing frontend integration — proxy replaces `/auth-api/v1` rewrite |
| `legacy-e-cert-integration.md` (cert-platform) | §10 documents the `/auth-api/v1` calls being replaced |
| `authenticated-endpoints-spec.md` (cert-platform) | Proxy endpoints added to the catalog with appropriate levels |
| `api-endpoints.md` (cert-platform) | Endpoint catalog updated with `/service/*` routes |

---

## 4. Design Decisions

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| D1 | Where the proxy lives | **Inside cert-api** (thin proxy, not a separate service) | Avoids new deployment; cert-api is already the frontend's single backend. See `bff-layer.md` for future dedicated BFF. |
| D2 | Auth for `/users`, `/groups` | **JWT pass-through** — forward the caller's `Authorization` header | These endpoints require platform-admin JWT; cert-api doesn't have its own admin JWT. The caller's token is forwarded as-is. |
| D3 | Auth for `/tenant/members` | **API key injection** — cert-api attaches `X-Api-Key` from config | Tenant member endpoints use `api.key.auth` middleware on auth-platform. The API key is a server-side secret, never exposed to the browser. |
| D4 | Route protection | **`jwt.auth` + `jwt.endpoint`** (same as all cert-api domain routes) | Only authenticated cert-platform users can call the proxy. No new middleware. |
| D5 | Response handling | **Relay upstream response** — pass status code and JSON body through | Thin proxy doesn't transform data. If upstream returns 401/403/404, the proxy relays it. |
| D6 | Timeout | **Same as existing auth calls** — `config('auth-platform.http_timeout')` (default 5s) | Consistent with `AuthRefreshController` and `AuthLogoutController`. |
| D7 | Error handling | **502 for network/timeout errors; relay for upstream 4xx/5xx** | Distinguishes cert-api failures from auth-platform failures. |

---

## 5. Routes

All routes are under `/api/v1/service` and protected by `jwt.auth` + `jwt.endpoint` middleware.

### 5.1 Users (JWT pass-through to auth-platform)

| Method | Cert-API Route | Upstream (Auth) | Required Level | Description |
|--------|---------------|-----------------|----------------|-------------|
| `GET` | `/api/v1/service/users` | `GET /api/v1/users` | `read` | List users (tenant-scoped via JWT) |
| `PATCH` | `/api/v1/service/users/{id}/status` | `PATCH /api/v1/users/{id}/status` | `admin` | Enable/disable user |

### 5.2 Groups (JWT pass-through to auth-platform)

| Method | Cert-API Route | Upstream (Auth) | Required Level | Description |
|--------|---------------|-----------------|----------------|-------------|
| `GET` | `/api/v1/service/groups` | `GET /api/v1/groups` | `read` | List groups |

### 5.3 Tenant Members (API key to auth-platform)

| Method | Cert-API Route | Upstream (Auth) | Required Level | Description |
|--------|---------------|-----------------|----------------|-------------|
| `GET` | `/api/v1/service/members` | `GET /api/v1/tenant/members` | `read` | List tenant members |
| `POST` | `/api/v1/service/members` | `POST /api/v1/tenant/members` | `admin` | Add existing user to tenant |
| `DELETE` | `/api/v1/service/members/{userId}` | `DELETE /api/v1/tenant/members/{userId}` | `admin` | Revoke membership |
| `POST` | `/api/v1/service/members/invite` | `POST /api/v1/tenant/members/invite` | `admin` | Invite new user |

### 5.4 Query Parameters

For `GET` endpoints, the proxy forwards all query parameters from the
caller to the upstream endpoint.  No parameter mapping or transformation.

### 5.5 Request Body

For `POST` and `PATCH` endpoints, the proxy forwards the JSON request
body as-is to the upstream endpoint.

---

## 6. Configuration

### 6.1 Environment Variable

```env
# API key for server-to-server calls (tenant member management).
# Format: tk_...:tsk_...
# Generate via Auth admin dashboard: /admin/tenants/{tenant}/api-keys
AUTH_API_KEY=
```

### 6.2 Config File

Added to `config/auth-platform.php`:

```php
'api_key' => env('AUTH_API_KEY', ''),
```

### 6.3 Upstream Auth Mechanism Summary

| Upstream Endpoint | Auth Mechanism | Who Provides |
|-------------------|---------------|--------------|
| `GET /api/v1/users` | JWT (`Authorization: Bearer`) | Caller (forwarded by proxy) |
| `PATCH /api/v1/users/{id}/status` | JWT (`Authorization: Bearer`) | Caller (forwarded by proxy) |
| `GET /api/v1/groups` | JWT (`Authorization: Bearer`) | Caller (forwarded by proxy) |
| `GET /api/v1/tenant/members` | API Key (`X-Api-Key`) | Cert-api config |
| `POST /api/v1/tenant/members` | API Key (`X-Api-Key`) | Cert-api config |
| `DELETE /api/v1/tenant/members/{userId}` | API Key (`X-Api-Key`) | Cert-api config |
| `POST /api/v1/tenant/members/invite` | API Key (`X-Api-Key`) | Cert-api config |

---

## 7. Frontend Changes

### 7.1 `src/lib/api/users-admin.ts`

Replace the separate auth-platform client with calls through cert-api:

```typescript
// Before
const BASE_URL = "/auth-api/v1";

// After
const BASE_URL = "/api/v1/service";
```

Endpoint path changes:

| Before | After |
|--------|-------|
| `/users?search=&group_id=&limit=&offset=` | `/users?search=&group_id=&limit=&offset=` |
| `/groups?tenant_id=` | `/groups?tenant_id=` |
| `/users/{id}/status` | `/users/{id}/status` |

The paths are identical — only the base URL changes.  The cert-api
proxy handles upstream routing.

### 7.2 `next.config.ts`

Remove the `/auth-api/v1` rewrite rule:

```typescript
// Before
async rewrites() {
  return [
    { source: "/api/v1/:path*", destination: `${CERT_BASE}/api/v1/:path*` },
    { source: "/auth-api/v1/:path*", destination: `${AUTH_BASE}/api/v1/:path*` },
  ];
}

// After
async rewrites() {
  return [
    { source: "/api/v1/:path*", destination: `${CERT_BASE}/api/v1/:path*` },
  ];
}
```

### 7.3 Authentication

The frontend's existing JWT token (stored in localStorage, attached as
`Authorization: Bearer` by the cert-api client) is forwarded by the
proxy to auth-platform.  No additional authentication setup needed.

---

## 8. Error Handling

| Scenario | Cert-API Response | Notes |
|----------|------------------|-------|
| Missing `AUTH_API_KEY` config | `500 { "message": "Auth API key not configured" }` | Configuration error |
| Upstream returns 4xx/5xx | Relay upstream status + body | Proxy passes through |
| Network timeout / connection refused | `502 { "message": "Upstream request failed" }` | Cert-api distinguishes its own failures |
| Invalid JWT (upstream rejects) | `401` (relayed from auth-platform) | Caller needs to refresh token |

---

## 9. Implementation Checklist

- [x] Add `AUTH_API_KEY` to `config/auth-platform.php`
- [x] Add `AUTH_API_KEY` to `.env.example` and `.env.cpanel`
- [x] Create `AuthProxyController` with all 7 proxy methods
- [ ] Add routes to `routes/api.php` under `service` prefix
- [ ] Add endpoints to `config/cert-endpoints.php` catalog
- [ ] Update frontend `users-admin.ts` base URL
- [ ] Remove `/auth-api/v1` rewrite from `next.config.ts`
- [ ] Run cert-api test suite
- [ ] Test frontend user management flows end-to-end

---

## 10. Risks & Open Questions

| ID | Question | Status |
|----|----------|--------|
| Q1 | Should the proxy add rate limiting? | Deferred — auth-platform already throttles tenant-member endpoints at 60/min |
| Q2 | Should the proxy cache upstream responses? | No — thin proxy; caching would add complexity and stale-data risk |
| Q3 | What if auth-platform returns HTML instead of JSON? | Edge case — relay as-is; frontend handles non-JSON responses |

---

## 11. Change Log

| Version | Date | Change |
|---------|------|--------|
| 1.0 Draft | 2026-08-29 | Initial spec — thin proxy for user/group/membership endpoints |
