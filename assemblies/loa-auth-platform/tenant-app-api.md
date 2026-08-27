# Tenant App API — User Management Endpoints

## Product Assembly Component Specification

**Version:** 1.0 (Final)
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — API surface, tenant-scoped
**Audience:** Architects, Engineers, AI Development Agents

> Provides tenant applications with a programmatic way to manage their own
> members (add, list, revoke, invite) via API Key + Secret authentication.
> Complements the existing admin dashboard and bulk-import surfaces.

---

## 1. Purpose

Answers:

> **"How does a tenant app add/remove/list users in its own tenant —
> without requiring a platform-admin JWT session?"**

Tenant apps call these endpoints using an **API Key + Secret** issued by
platform-admin. The key scopes all operations to one tenant. No JWT login
required. No platform-admin access needed by the tenant app.

---

## 2. Ownership

### Owns

- API Key lifecycle (create, revoke, list) — platform-admin only.
- Tenant-scoped user management endpoints (add, list, revoke, invite).
- API Key authentication middleware.

### Does Not Own

- User entity (owned by `kernels/identity/`).
- Tenant entity (owned by `kernels/identity/tenancy.md`).
- Activation/set-password flow (owned by `user-account-activation.md`).
- Group management (owned by `group-permission-management.md`).
- JWT auth surface (owned by `IdentityService`).

---

## 3. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `kernels/identity/tenancy.md` | Tenant entity; `TenantService::addUserToTenant()` reused |
| `kernels/identity/entities/user.md` | User entity; `IdentityService::register()` reused for invite |
| `user-account-activation.md` | Set-password email flow reused for invite |
| `auth-tenant.md` §6 | "Create User" flow — API invite mirrors this |
| `bulk-user-import.md` | Bulk import — this spec is the single-user programmatic equivalent |
| `admin-dashboard.md` | Admin UI — platform-admin manages keys here |
| `web-ui.md` | Admin session gate — platform-admin key management uses existing admin surface |

---

## 4. Design Decisions

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| D1 | Auth mechanism | **`X-Api-Key` header** — value is `key:secret` | Single header; no Base64 encoding; works with any HTTP client; key identifies tenant |
| D2 | Key scope | **One key = one tenant** | Key identifies the tenant; no tenant_id parameter needed in requests |
| D3 | Secret format | **`tsk_` + 40 hex chars** (44 chars total) | Prefix makes secrets grep-able; 160 bits of randomness |
| D4 | Key format | **`tk_` + 12 hex chars** (15 chars total) | Short, readable; paired with secret in header |
| D5 | Secret storage | **SHA-256 hash only** (like activation tokens) | Raw secret never stored; shown once at creation |
| D11 | Max keys | **3 per tenant** | Limited surface area; platform-admin can revoke and rotate |
| D12 | CORS | **Tenant's `app_url` + `redirect_origins`** | Already on the tenant entity; no new config needed |
| D6 | Invite flow | **Same as `auth-tenant.md` §6** — create user (pending) + add to tenant + send set-password email | Consistent with admin UX |
| D7 | Group assignment on invite | **Optional** — invite accepts optional `groups` array | Convenience; groups are tenant-scoped |
| D8 | Remove vs revoke | **Revoke = detach membership** (user still exists, just removed from tenant + tenant-scoped groups) | Matches `TenantService::removeUserFromTenant()` |
| D9 | List pagination | **Cursor-based** — `?cursor=<id>&limit=20` | Consistent with existing API patterns |
| D10 | Key management visibility | **Platform-admin only** — keys visible in admin dashboard, NOT to tenant members | Keys are integration credentials, not user-facing |

---

## 5. Model

### 5.1 Table: `tenant_api_keys`

```sql
tenant_api_keys (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name          VARCHAR(255) NOT NULL,            -- human label e.g. "Production App"
  key_hash      VARCHAR(64) NOT NULL,             -- SHA-256 of the raw key
  secret_hash   VARCHAR(64) NOT NULL,             -- SHA-256 of the raw secret
  last_used_at  TIMESTAMPTZ NULL,
  expires_at    TIMESTAMPTZ NULL,                  -- NULL = never expires
  revoked_at    TIMESTAMPTZ NULL,                  -- NULL = active
  created_by    UUID NULL REFERENCES users(id),   -- platform-admin who created it
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (key_hash)
)
```

Invariants:
1. `key_hash` unique — no two keys share the same hash.
2. One key scoped to one tenant; `tenant_id` FK cascade on delete.
3. `revoked_at` non-null = key is dead; middleware must reject.
4. `expires_at` non-null + past = key is expired; middleware must reject.
5. `secret_hash` stored as SHA-256; raw secret shown only once at creation.
6. **Max 3 active keys per tenant** — enforced at creation time (controller checks `where('tenant_id', …)->whereNull('revoked_at')->count() < 3`).

### 5.2 Key Generation (at creation)

```
key    = "tk_" . bin2hex(random_bytes(6))     -- 15 chars
secret = "tsk_" . bin2hex(random_bytes(20))   -- 44 chars
```

Store:
- `key_hash = sha256(key)`
- `secret_hash = sha256(secret)`

Return to platform-admin **once**: `{ key, secret, tenant_slug, name }`.

### 5.3 Authentication Flow

```
Tenant App
  |
  | X-Api-Key: tk_abc123def456:tsk_0123456789abcdef...
  |
  v
ApiKeyAuthMiddleware
  |
  | 1. Extract X-Api-Key header
  | 2. Split on first ':' → key, secret
  | 3. hash(key) → lookup tenant_api_keys.key_hash
  | 4. If not found → 401
  | 5. hash(secret) → compare to tenant_api_keys.secret_hash (constant-time)
  | 6. If mismatch → 401
  | 7. Check not revoked (revoked_at IS NULL)
  | 8. Check not expired (expires_at IS NULL OR expires_at > NOW())
  | 9. Update last_used_at
  | 10. Set request attribute 'tenant_id' = tenant_api_keys.tenant_id
  |
  v
Controller
  |
  | $tenantId = $request->attributes->get('tenant_id')
  | All operations scoped to this tenant
```

### 5.4 CORS

CORS is resolved from the tenant's existing `app_url` and `redirect_origins` fields
(on the `tenants` table). The `ApiKeyAuthMiddleware` sets the `Access-Control-Allow-Origin`
header to the matching origin from the request's `Origin` header, if it appears in the
tenant's allowed origins.

For non-browser clients (server-to-server), CORS headers are not required but do not
hurt. The middleware always processes the request regardless of `Origin` presence.

---

## 6. Routes

### 6.1 Platform-Admin: API Key Management

All routes behind `auth` (web guard) + `web.admin` + `users.manage`.

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/admin/tenants/{tenant}/api-keys` | list keys for tenant | `admin.tenants.api-keys.index` |
| `POST` | `/admin/tenants/{tenant}/api-keys` | create key (show once) | `admin.tenants.api-keys.store` |
| `DELETE` | `/admin/tenants/{tenant}/api-keys/{key}` | revoke key | `admin.tenants.api-keys.destroy` |

### 6.2 Tenant App: User Management (API Key auth)

All routes behind `api.key.auth` middleware (`X-Api-Key: key:secret` header).

| Method | URI | Action | Description |
|--------|-----|--------|-------------|
| `GET` | `/api/v1/tenant/members` | `index` | List members in the tenant |
| `POST` | `/api/v1/tenant/members` | `store` | Add existing user to tenant |
| `DELETE` | `/api/v1/tenant/members/{userId}` | `destroy` | Revoke membership (remove from tenant) |
| `POST` | `/api/v1/tenant/members/invite` | `invite` | Create user + add to tenant + send set-password email |

**Note:** The tenant identity comes from the API key, not the URL. The URL is tenant-agnostic (`/api/v1/tenant/members`). This keeps the key as the single source of tenant scope.

---

## 7. API Contracts

### 7.1 List Members

`GET /api/v1/tenant/members`

**Query parameters:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| `status` | string | (all) | Filter by `pending`, `active`, `disabled` |
| `cursor` | uuid | (start) | Pagination cursor (last item's `id`) |
| `limit` | int | 20 | Max 100 |

**Response: `200`**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "status": "active",
      "joined_at": "2026-08-01T10:00:00Z"
    }
  ],
  "next_cursor": "uuid-or-null",
  "has_more": true
}
```

### 7.2 Add Existing User

`POST /api/v1/tenant/members`

**Request:**

```json
{
  "email": "existing-user@example.com"
}
```

**Response: `201`**

```json
{
  "message": "User added to tenant",
  "user": {
    "id": "uuid",
    "name": "Existing User",
    "email": "existing-user@example.com",
    "status": "active",
    "joined_at": "2026-08-27T12:00:00Z"
  }
}
```

**Errors:**

| Code | Body | Cause |
|------|------|-------|
| `404` | `{ "message": "User not found" }` | Email not in system |
| `409` | `{ "message": "User is already a member of this tenant" }` | Already a member |
| `422` | `{ "message": "Validation failed", "errors": { "email": [...] } }` | Invalid input |

### 7.3 Revoke Membership

`DELETE /api/v1/tenant/members/{userId}`

Removes user from tenant and all tenant-scoped groups. Does NOT delete the user.

**Response: `200`**

```json
{
  "message": "Membership revoked",
  "user": {
    "id": "uuid",
    "email": "user@example.com"
  }
}
```

**Errors:**

| Code | Body | Cause |
|------|------|-------|
| `404` | `{ "message": "User is not a member of this tenant" }` | Not a member |

### 7.4 Invite User

`POST /api/v1/tenant/members/invite`

Creates a new user (status: `pending`), adds to tenant, optionally assigns groups,
sends set-password email. Same as admin "Create User" flow.

**Request:**

```json
{
  "name": "New User",
  "email": "new@example.com",
  "groups": ["cert-admin", "cert-staff"]
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | Yes | Display name |
| `email` | Yes | Must be unique in system |
| `groups` | No | Array of group names within this tenant; ignored if group doesn't exist |

**Response: `201`**

```json
{
  "message": "Invitation sent",
  "user": {
    "id": "uuid",
    "name": "New User",
    "email": "new@example.com",
    "status": "pending",
    "joined_at": "2026-08-27T12:00:00Z"
  }
}
```

**Processing:**

1. Validate input (name required, email required + unique).
2. Create user via `IdentityService::register(email, '', name)`.
3. Override status to `pending`.
4. Attach to tenant via `TenantService::addUserToTenant()`.
5. If `groups` provided: resolve each group by name within tenant, assign via `AuthorizationService::addToGroup()`.
6. Generate set-password token (signed, expires 48h).
7. Send set-password email.
8. Audit log: `user.created` + `tenant.member_added` + `tenant.member_invited`.

**Errors:**

| Code | Body | Cause |
|------|------|-------|
| `409` | `{ "message": "A user with this email already exists" }` | Email taken |
| `422` | `{ "message": "Validation failed", "errors": {...} }` | Invalid input |

---

## 8. Middleware

### 8.1 `ApiKeyAuthMiddleware`

**File:** `app/Http/Middleware/ApiKeyAuthMiddleware.php`

Logic:
1. Extract `X-Api-Key` header.
2. If missing → `401 { "message": "Missing API key credentials" }`.
3. Split on first `:` → must yield exactly `[key, secret]`. If not → `401`.
4. `hash('sha256', $key)` → lookup `tenant_api_keys.key_hash`.
5. If not found → `401`.
6. `hash('sha256', $secret)` → compare to row's `secret_hash` (constant-time via `hash_equals`).
7. If mismatch → `401`.
8. If `revoked_at` is not null → `401`.
9. If `expires_at` is not null and in the past → `401`.
10. Update `last_used_at` = now (fire-and-forget, don't block response).
11. `$request->attributes->set('tenant_id', $row->tenant_id)`.
12. `$request->attributes->set('api_key_id', $row->id)`.
13. Set CORS header if `Origin` matches tenant's allowed origins.
14. Pass to next handler.

**Error response (all 401):**

```json
{
  "message": "Invalid API key credentials"
}
```

Single error message — no distinction between unknown key, bad secret, revoked, or expired.

### 8.2 Route Middleware Registration

**File:** `routes/api.php`

```php
Route::prefix('v1/tenant')->middleware('api.key.auth')->group(function () {
    Route::get('/members', ...);
    Route::post('/members', ...);
    Route::delete('/members/{userId}', ...);
    Route::post('/members/invite', ...);
});
```

---

## 9. Platform-Admin: Key Management UI

### 9.1 Tenant Show Page

On the existing `/admin/tenants/{tenant}` page, add a **"API Keys"** card:

| Column | Value |
|--------|-------|
| Name | Key label (e.g. "Production App") |
| Key | `tk_abc...` (first 8 chars + `****`) |
| Created | Date |
| Last Used | Date or "Never" |
| Status | Active / Expired / Revoked |
| Actions | Revoke button |

**"Generate API Key" button** opens a modal:
1. Enter name (required).
2. Optional: set expiry date.
3. Click "Generate".
4. **Show key + secret once** in a highlighted box with copy buttons.
5. Warning: "Save the secret now. It will not be shown again."
6. Close modal → key appears in list (secret never shown again).

### 9.2 Key Creation API (JSON)

For programmatic key creation (platform-admin JWT auth):

```
POST /api/v1/admin/tenants/{tenant}/api-keys
```

Middleware: `jwt.auth` + `jwt.permission:users.manage`

**Request:**

```json
{
  "name": "Production App",
  "expires_at": "2027-01-01T00:00:00Z"
}
```

**Response: `201`**

```json
{
  "id": "uuid",
  "name": "Production App",
  "key": "tk_abc123def456",
  "secret": "tsk_0123456789abcdef0123456789abcdef01234567",
  "tenant_id": "uuid",
  "expires_at": "2027-01-01T00:00:00Z",
  "created_at": "2026-08-27T12:00:00Z"
}
```

**THIS IS THE ONLY TIME `key` AND `secret` ARE RETURNED.**

---

## 10. Invariants

1. API keys are **tenant-scoped** — one key maps to exactly one tenant.
2. Keys are **platform-admin-managed only** — tenant members cannot see, create, or revoke keys.
3. Secret is shown **once** at creation — stored as SHA-256 hash only.
4. Authentication uses **`X-Api-Key` header** — value is `key:secret` (colon-separated).
5. **Max 3 active keys per tenant** — platform-admin must revoke before creating more.
6. All operations are scoped to the key's tenant — no cross-tenant access.
7. Revoking a key immediately invalidates it (no grace period).
8. Expired keys are rejected at middleware level.
9. `last_used_at` is updated on every successful auth (informational only, not enforced).
10. CORS resolved from tenant's `app_url` + `redirect_origins` (no new config).
11. The invite flow creates users with `status: pending` and sends a set-password email (consistent with admin UX).
12. Removing a membership detaches the user from the tenant and all tenant-scoped groups (matches `TenantService::removeUserFromTenant()`).

---

## 11. Security Checklist

- [ ] API key secret shown only once at creation
- [ ] Secrets stored as SHA-256 hashes, never plaintext
- [ ] Constant-time comparison for secret hash (`hash_equals`)
- [ ] Single error message on auth failure (no oracle)
- [ ] Rate limiting on auth endpoint (prevent brute-force)
- [ ] Max 3 keys per tenant enforced
- [ ] Keys can be revoked instantly
- [ ] Platform-admin-only visibility for key management
- [ ] CSRF protection on admin key management forms
- [ ] Audit log on key creation and revocation
- [ ] Tenant scope enforced — no cross-tenant access via key
- [ ] CORS derived from tenant's `app_url` + `redirect_origins`

---

## 12. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Returning secret after creation | Secret compromise window | Show once, store hash only |
| URL-based tenant identification | Spoofable | Tenant derived from key, not request |
| Distinguishing auth errors | Login oracle | Single "Invalid credentials" message |
| Exceeding 3-key limit | Unmanageable credential sprawl | Revoke old keys before creating new |
| Hardcoding CORS origins | Drift from tenant config | Derive from tenant's `app_url` + `redirect_origins` |

---

## 13. Testing Checklist

- [ ] Create key → secret shown once → never retrievable again
- [ ] Auth with valid `X-Api-Key: key:secret` → 200
- [ ] Auth with missing header → 401
- [ ] Auth with malformed header (no colon) → 401
- [ ] Auth with invalid key → 401
- [ ] Auth with valid key, wrong secret → 401
- [ ] Auth with revoked key → 401
- [ ] Auth with expired key → 401
- [ ] Max 3 keys enforced → 4th creation rejected
- [ ] List members → paginated, scoped to tenant
- [ ] Add existing user → 201, user in tenant
- [ ] Add already-member → 409
- [ ] Add nonexistent email → 404
- [ ] Revoke membership → user removed from tenant + groups
- [ ] Invite new user → user created (pending) + tenant attached + email sent
- [ ] Invite with groups → user in specified groups
- [ ] Invite with existing email → 409
- [ ] Non-admin cannot access key management → 403
- [ ] Cross-tenant key isolation → key for tenant A cannot access tenant B
- [ ] CORS → `Origin` in tenant's allowed origins → `Access-Control-Allow-Origin` header set
- [ ] CORS → `Origin` not in allowed origins → no CORS header

---

## 14. Implementation Inventory

| Layer | Item | Status |
|-------|------|--------|
| Migration | `tenant_api_keys` table | To create |
| Model | `TenantApiKey` | To create |
| Middleware | `ApiKeyAuthMiddleware` (X-Api-Key header) | To create |
| Controller | `TenantApiKeyController` (admin CRUD, max-3 enforcement) | To create |
| Controller | `TenantMemberApiController` (key-auth user mgmt) | To create |
| Routes | Web (admin key mgmt) + API (tenant app endpoints) | To add |
| Admin UI | API Keys card on tenant show page | To add |
| CORS | Resolve from tenant's `app_url` + `redirect_origins` in middleware | To add |
| Tests | Middleware, controller, auth flow, CORS, max-3 limit | To write |

---

## 15. Open Questions

| ID | Question | Resolution |
|----|----------|------------|
| Q1 | Auth mechanism? | **Resolved:** `X-Api-Key` header with `key:secret` value |
| Q2 | Max keys per tenant? | **Resolved:** 3 |
| Q3 | CORS support? | **Resolved:** Yes, from tenant's `app_url` + `redirect_origins` |
| Q4 | Should revoking a key audit-log the revocation? | **Resolved:** Yes |
| Q5 | Invite `group` string vs `groups` array? | **Resolved:** `groups` array only; no string alias |

---

## 16. Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 0.1 Draft | 2026-08-27 | AI | Initial draft — API key auth, user management endpoints |
| 0.2 Draft | 2026-08-27 | AI | Auth changed to `X-Api-Key` header (`key:secret`); max 3 keys/tenant; CORS from tenant `app_url`/`redirect_origins` |
| 1.0 Final | 2026-08-27 | AI | Promoted to Final — `groups` array-only format; all open questions resolved |
