# Tenant Group Endpoint Grants

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface
**Audience:** Architects, Engineers, AI Development Agents

> Companion to `tenant-endpoint-catalog.md` and `group-permission-management.md`.
>
> Where `tenant-endpoint-catalog.md` declares *what endpoints exist per tenant and what level each requires*, this spec declares *which groups (and individual users) are granted which level on which cataloged endpoints*. Together they produce the resolved permission set published to `GET /api/auth/access` and enforced by `ClaimPolicyMiddleware` / `jwt.claim-policy` at the tenant-app edge.

---

## 1. Purpose

It answers:

> **"Given a user and a tenant, which level (read / write / admin) has each group and user been granted on each cataloged endpoint?"**

A platform admin (or tenant app via `permissions.json` import) registers a tenant app's guarded endpoints once in the catalog (`tenant_app_endpoint`). This spec then lets admins grant levels to groups against those cataloged endpoints, and apply per-user overrides. The resolved set — computed at token-issuance time — is embedded in the JWT `permissions` claim as `<level>:<path>` and published to the session store.

---

## 2. Ownership

### Owns

- `tenant_endpoint_grant` table — group → endpoint level grant (tenant-scoped).
- `tenant_endpoint_override` table — user → endpoint level override (tenant-scoped).
- Level vocabulary resolution (`read` < `write` == `admin`; `deny` wins within a group; user overrides apply last).
- Admin UI + API for managing group grants and user overrides against cataloged endpoints.
- The "resolve effective level" computation consumed at login/refresh to build the JWT `permissions` claim payload.

### Does Not Own

- The endpoint catalog itself (`tenant_app_endpoint` — see `tenant-endpoint-catalog.md`).
- JWT token issuance (handled by `IdentityService`/`JWTService`).
- Claim-based enforcement at the tenant-app edge (handled by the app's `ClaimPolicyMiddleware`).
- User-group membership (handled by `group-permission-management.md` §5.3 / `AuthorizationService::addToGroup`).
- The claims-based permission model for Auth Platform routes (handled by `data-driven-permission-policy.md`).

---

## 3. Concepts

### 3.1 Level Vocabulary

| Level | Ordinal | Meaning | Covers |
|-------|---------|---------|--------|
| `none` | 0 | No access (default for uncategorized endpoints) | — |
| `read` | 1 | Safe `GET` view operations | list / view |
| `write` | 2 | Create / update / delete | `POST`, `PUT`, `PATCH`, `DELETE` |
| `admin` | 2 | Same as `write`; reserved label for destructive/admin endpoints | create / update / delete / admin ops |
| `deny` | — | Explicit denial; overrides any group-level grant for this endpoint | blocks all access |

**Semantics:**
- `read` < `write` == `admin`. `admin` is a label that enforces at the same level as `write`.
- `deny` is a signal, not a level. If **any** of the user's groups has `deny` for an endpoint, the group-resolution result is `deny` — regardless of what other groups granted.
- A user-level override **replaces** the group-resolution result entirely for that endpoint. A user override of `deny` can re-enable an endpoint that groups denied.

### 3.2 Scope of a Grant

A grant is scoped by:
1. **Group scope** — the group's tenant (`user_groups.tenant_id`): platform-global (`NULL`) or tenant-specific.
2. **Endpoint scope** — the catalog entry's tenant (`tenant_app_endpoint.tenant_id`): platform-wide (`NULL`) or tenant-specific.
3. **Grant tenant** — `tenant_endpoint_grant.tenant_id`: which tenant context the grant applies in.

A grant applies to a user only when:
- The user belongs to the group (via `user_user_group`).
- The user is a member of the grant's tenant (or the grant is platform-wide, `tenant_id NULL`).
- The catalog entry for the endpoint exists under the same tenant scope.

Platform-global groups (`tenant_id NULL`) with platform-wide endpoint grants (`tenant_id NULL`) apply in **every** tenant.

---

## 4. Resolution Algorithm

Given `(userId, tenantId, method, path)`:

```
1. catalogEntry = tenant_app_endpoint[tenantId, method, paramMatch(path)]
   if catalogEntry is null:
      return 403  (closed-by-default — see tenant-endpoint-catalog.md §8)

2. required_level = catalogEntry.required_level

3. // Collect group grants
   groups = user_groups where
     (group.tenant_id IS NULL)                  -- platform-global groups
     OR (group.tenant_id = tenantId)            -- tenant groups
     AND user is member (user_user_group)

4. effective_level = 'none'
   for each group in groups:
      grant = tenant_endpoint_grant[group.id, method, paramMatch(path), grantTenantId]
         where grantTenantId IN (NULL, tenantId)  -- platform-wide + tenant grants
      if grant.level == 'deny':
         effective_level = 'deny'
         break  (deny wins within group resolution)
      if levelOrdinal(grant.level) > levelOrdinal(effective_level):
         effective_level = grant.level

5. // Apply user override
   override = tenant_endpoint_override[userId, method, paramMatch(path), overrideTenantId]
      where overrideTenantId IN (NULL, tenantId)
   if override is not null:
      effective_level = override.level

6. return ALLOW iff levelOrdinal(effective_level) >= levelOrdinal(required_level)
                  AND effective_level != 'deny'
```

**Notes:**
- `paramMatch(path)` uses `{param}`-aware matching (see `tenant-endpoint-catalog.md` §8: `/api/v1/appointments/{id}` matches `/api/v1/appointments/123`).
- `levelOrdinal`: `none`=0, `read`=1, `write`=`admin`=2.
- `deny` is checked before ordinal comparison — a single `deny` grant short-circuits to denial.
- A user-level override of `deny` can re-enable; a user-level override of any level replaces the group result.

### 4.1 JWT `permissions` Claim Payload

At login / token refresh, the Auth Platform resolves the user's effective level for **every** cataloged endpoint in the login tenant (and platform-wide endpoints), producing the session payload:

```jsonc
{
  "groups": ["loa-auth-admin", "Faculty"],
  "permissions": [
    "read:/api/v1/appointments",
    "write:/api/v1/appointments/{id}",
    "admin:/api/v1/appointments/settings",
    "read:/api/v1/certificates"
  ]
}
```

Only endpoints where the user's effective level > `none` appear. Each entry is `<level>:<path>` where `level` ∈ {`read`, `write`, `admin`} (never `deny` or `none`).

This payload is:
- Embedded in the JWT `permissions` claim (consumed by tenant apps for local `ClaimPolicyMiddleware` checks).
- Published to `GET /api/auth/access` (consumed by the frontend session store to lock/unlock UI elements).

See `tenant-endpoint-catalog.md` §8 for the enforcement hook contract.

---

## 5. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `tenant-endpoint-catalog.md` (Final v3.1) | Declares the catalog (`tenant_app_endpoint`). This spec grants levels *against* cataloged endpoints. Composite key `(tenant_id, method, path)` is the reference target. |
| `kernels/identity/entities/data-driven-permission-policy.md` (Final v1.0) | Claims-based model for Auth Platform's own routes (`route_policies`, `claims`, `group_claims`). The endpoint-grant model is the **level-based** analogue for tenant apps (cert, consult). They coexist: claims for auth-platform admin API, levels for tenant-app RBAC. |
| `kernels/identity/tenancy.md` (Final v3.0) | Provides `tenants`, `user_tenants`, tenant-scoped `user_groups`. Membership enforcement gate (§3.2) reuses `tenantService::isMember()`. |
| `kernels/identity/rules/permission-resolution.md` | Existing resolution order (union of groups, deny-wins, user overrides last). This spec applies the **same ordering** but at the endpoint-level granularity with a 3-level ordinal instead of boolean grant/deny. |
| `assemblies/loa-auth-platform/group-permission-management.md` | `GroupController`, `UserGroupController` manage claims-based grants. This spec adds endpoint-level grant management for the catalog model. Both are consumed by the same admin session (`web.admin`). |
| `kernels/identity/entities/user-group.md` | `user_groups` table is the grant target. `tenant_id` on groups scopes membership. |
| `kernels/identity/README.md` | IdentityService / JWTService contracts; this spec extends the JWT `permissions` payload at token issuance. |

### 5.1 Coexistence with Claims-Based Permissions

The platform now has two authorization models:

| Model | Scope | Table | Enforcement | Consumer |
|-------|-------|-------|-------------|----------|
| Claims (`data-driven-permission-policy.md`) | Auth Platform admin API | `claims`, `route_policies`, `group_claims`, `user_claim_overrides` | `ClaimPolicyMiddleware` (existing, `jwt.claim-policy`) | Auth Platform `/api/v1/admin/*` |
| Levels (`tenant-endpoint-catalog.md` + this) | Tenant app endpoints | `tenant_app_endpoint` + `tenant_endpoint_grant` + `tenant_endpoint_override` | `ClaimPolicyMiddleware` (extended) / frontend session store | Cert, Consult apps |

The `ClaimPolicyMiddleware` is extended to consult the level-based model for tenant scopes and the claims-based model for platform-admin scopes. See §8 (Implementation Inventory).

---

## 6. Model

### Table: `tenant_endpoint_grant`

```sql
tenant_endpoint_grant (
  group_id       uuid     FK -> user_groups(id)        ON DELETE CASCADE,
  method         VARCHAR(10)  NOT NULL,                -- GET | POST | PUT | PATCH | DELETE | *
  path           VARCHAR(512) NOT NULL,                -- '/api/v1/appointments/{id}'
  tenant_id      uuid     FK -> tenants(id)            ON DELETE CASCADE,  -- NULL = platform-wide grant
  level          ENUM('read','write','admin','deny')  NOT NULL DEFAULT 'read',
  created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (group_id, tenant_id, method, path)
)
```

**Invariants:**
1. `group_id` + `tenant_id` (grant scope) + `method` + `path` is unique.
2. `tenant_id` NULL means the grant applies in every tenant (platform-wide group on a platform-wide endpoint, or a platform-global group on a tenant endpoint that exists in all tenants).
3. If `tenant_id` is set, it must reference a tenant whose catalog contains an entry for `(method, path)` in that tenant's scope — enforced at write-time.
4. `level` ∈ {`read`, `write`, `admin`, `deny`}.

### Table: `tenant_endpoint_override`

```sql
tenant_endpoint_override (
  user_id        uuid     FK -> users(id)              ON DELETE CASCADE,
  method         VARCHAR(10)  NOT NULL,
  path           VARCHAR(512) NOT NULL,
  tenant_id      uuid     FK -> tenants(id)            ON DELETE CASCADE,  -- NULL = platform-wide override
  level          ENUM('read','write','admin','deny')  NOT NULL,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (user_id, tenant_id, method, path)
)
```

**Invariants:**
1. `user_id` + `tenant_id` + `method` + `path` is unique.
2. `tenant_id` NULL means the override applies in every tenant.
3. Overrides are tenant-scoped — a user can be overridden per-tenant (`tenancy.md` §3.4).
4. `level` ∈ {`read`, `write`, `admin`, `deny`}.

### Table: `tenant_app_endpoint` (from `tenant-endpoint-catalog.md`)

This spec references but does not own the catalog table. The grant/override tables use `(method, path, tenant_id)` as the foreign key into the catalog.

---

## 7. Admin Operations

All routes require `auth` (web guard) + `web.admin` + `users.manage`, scoped to a tenant. Platform-wide overrides require platform-admin (`loa-auth-admin`).

### 7.1 Group Endpoint Grants

Route group (added to `routes/web.php` under `admin-dashboard.md` §3.8):

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/tenants/{tenant}/groups/{group}/endpoints` | list grants for a group | `admin.tenants.groups.endpoints.index` |
| `POST` | `/admin/tenants/{tenant}/groups/{group}/endpoints` | grant/revoke a level | `admin.tenants.groups.endpoints.grant` |
| `DELETE` | `/admin/tenants/{tenant}/groups/{group}/endpoints` | remove a grant | `admin.tenants.groups.endpoints.revoke` |

**List response (`GET`):**

```json
{
  "group": { "id": "grp_abc", "name": "Faculty", "tenant_id": "tenant_loa" },
  "tenant": { "id": "tenant_loa", "slug": "loa" },
  "grants": [
    {
      "method": "GET",
      "path": "/api/v1/appointments",
      "label": "List appointments",
      "required_level": "read",
      "granted_level": "read"
    },
    {
      "method": "POST",
      "path": "/api/v1/appointments",
      "label": "Create appointment",
      "required_level": "write",
      "granted_level": "write"
    },
    {
      "method": "DELETE",
      "path": "/api/v1/appointments/{id}",
      "label": "Delete appointment",
      "required_level": "admin",
      "granted_level": "deny"
    }
  ]
}
```

Entries with no grant show `"granted_level": null` (i.e., `none`).

**Grant (`POST`):**

```json
{
  "method": "DELETE",
  "path": "/api/v1/appointments/{id}",
  "level": "admin"
}
```

- Upserts into `tenant_endpoint_grant` for the group, scoped to the tenant from the route.
- Validates the endpoint exists in the catalog for the tenant (or is platform-wide).
- `level` ∈ {`read`, `write`, `admin`, `deny`}.
- `201` on success. `404` if group or catalog endpoint not found. `422` on invalid level or path.

**Revoke (`DELETE`):**

```json
{
  "method": "DELETE",
  "path": "/api/v1/appointments/{id}"
}
```

- Deletes the grant row, reverting to group-resolution default (`none`).
- `204` on success. `404` if grant not found.

### 7.2 User Endpoint Overrides

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/users/{id}/endpoint-overrides` | list overrides for a user | `admin.users.endpoint-overrides.index` |
| `POST` | `/admin/users/{id}/endpoint-overrides` | upsert an override | `admin.users.endpoint-overrides.upsert` |
| `DELETE` | `/admin/users/{id}/endpoint-overrides` | remove an override | `admin.users.endpoint-overrides.delete` |

**Upsert (`POST`):**

```json
{
  "method": "GET",
  "path": "/api/v1/appointments",
  "level": "read",
  "tenant_id": "tenant_loa"
}
```

- Omit `tenant_id` → inferred from the active admin session tenant (or platform-wide for `loa-auth-admin`).
- `200` on success. `404` if user not found. `422` on invalid level or path.

### 7.3 Bulk Import (from `permissions.json` — future)

The `ImportPermissions` command (`permissions:import {app}`) already populates `route_policies` for the claims-based model. A parallel `permissions:import-endpoints {app}` command (or extension) will import tenant endpoints + default group grants from the app's `permissions.json` into `tenant_app_endpoint` + `tenant_endpoint_grant`.

- Claims → `required_level` mapping (from `tenant-endpoint-catalog.md` §6.3):
  - `read` / `read-authored` / `read-scoped` → `read`
  - `write` / `write-authored` / `write-scoped` → `write`
  - `admin` (if present) → `admin`
- Default group grants are assigned from the `groups` array in the import JSON (group name → resolved to `group_id`, tenant-scoped).

---

## 8. API Contracts

All API routes require JWT authentication + `users.manage` permission (for admin consumers). Tenant apps call `GET /api/auth/access` with their own JWT (no admin permission required — it resolves the caller's own effective permissions).

### 8.1 List a Group's Endpoint Grants

`GET /api/v1/admin/tenants/{tenant}/groups/{group}/endpoints`

**Response (200):**

```json
{
  "group_id": "grp_abc",
  "group_name": "Faculty",
  "tenant_id": "tenant_loa",
  "grants": [
    {
      "method": "GET",
      "path": "/api/v1/appointments",
      "required_level": "read",
      "granted_level": "read"
    },
    {
      "method": "DELETE",
      "path": "/api/v1/appointments/{id}",
      "required_level": "admin",
      "granted_level": "deny"
    }
  ]
}
```

### 8.2 Grant a Level to a Group

`POST /api/v1/admin/tenants/{tenant}/groups/{group}/endpoints`

```json
{
  "method": "POST",
  "path": "/api/v1/appointments",
  "level": "write"
}
```

**Response (201):**

```json
{
  "status": "success",
  "group_id": "grp_abc",
  "method": "POST",
  "path": "/api/v1/appointments",
  "level": "write",
  "tenant_id": "tenant_loa"
}
```

### 8.3 Revoke a Group's Grant

`DELETE /api/v1/admin/tenants/{tenant}/groups/{group}/endpoints`

```json
{ "method": "POST", "path": "/api/v1/appointments" }
```

**Response (204):** No content.

### 8.4 User Overrides (API)

`GET /api/v1/admin/users/{id}/endpoint-overrides`

`POST /api/v1/admin/users/{id}/endpoint-overrides`

`DELETE /api/v1/admin/users/{id}/endpoint-overrides`

Same payload shapes as §7.2.

### 8.5 Session Store — `GET /api/auth/access`

**No admin permission required.** Any authenticated user calls this to retrieve their resolved permissions for the frontend session store.

`GET /api/v1/auth/access`

**Response (200):**

```json
{
  "user": { "id": "usr_abc", "email": "faculty@loa.edu.ph" },
  "tenant": { "id": "tenant_loa", "slug": "loa" },
  "groups": ["loa-auth-admin", "Faculty"],
  "permissions": [
    "read:/api/v1/appointments",
    "write:/api/v1/appointments/{id}",
    "read:/api/v1/certificates"
  ]
}
```

This endpoint:
1. Resolves the JWT bearer token to `userId` + `tenant.slug`.
2. Looks up the tenant (validates `active` status).
3. Iterates **all** cataloged endpoints for the tenant (and platform-wide).
4. For each, runs the resolution algorithm from §4.
5. Returns only endpoints where effective level > `none`, as `<level>:<path>`.

The frontend uses `permissions` to gate UI elements (`read:/api/v1/appointments` present → show the appointments page). Tenant apps use the JWT `permissions` claim + `ClaimPolicyMiddleware` for server-side enforcement.

---

## 9. Enforcement Integration

The `ClaimPolicyMiddleware` (registered as `jwt.claim-policy` in `bootstrap/app.php`) is extended to consult the level-based model:

```
for an inbound request (method, path) with resolved tenant T:
  1. If the JWT has claims (data-driven-permission-policy model):
     → check claims against route_policies (existing behavior)
  2. If the JWT has permissions (level-based model):
     → match (method, path) against tenant_app_endpoint[T, method, path]
     → if no catalog entry: 403 (closed-by-default) or pass-through (UI lock)
     → required_level = catalogEntry.required_level
     → granted_level = resolve from JWT permissions: find "<level>:<path>" matching
     → ALLOW iff levelOrdinal(granted_level) >= levelOrdinal(required_level)
```

The JWT `permissions` claim (produced at login via §4.1) carries the resolved set, so runtime enforcement is a simple membership check — no DB query per request. The `GET /api/auth/access` endpoint provides the same data for the frontend session store.

**Note:** Per `data-driven-permission-policy.md` Caveat §JWT Claims, claims in the JWT are valid for the token lifetime. Group/override changes take effect at next token issuance (login/refresh). For real-time enforcement, the middleware can call `GET /api/auth/access` as a server-side re-validation — optional, per-app decision.

---

## 10. Invariants (Consolidated)

1. Every grant/override references a cataloged endpoint that exists in the catalog for the same tenant scope (or platform-wide, `tenant_id NULL`) — enforced at write-time.
2. Group grants are implicitly scoped by both the group's tenant and the grant's `tenant_id`; a tenant group cannot be granted levels on endpoints outside its tenant (except platform-wide `tenant_id NULL` endpoints).
3. `deny` in any applicable group grant → `deny` for group resolution (short-circuits ordinal comparison).
4. User-level override **replaces** (does not merge with) the group-resolution result for that endpoint.
5. User overrides are tenant-scoped — a `tenant_id NULL` override applies in every tenant; a set `tenant_id` override applies only in that tenant.
6. Platform-wide grants/overrides (`tenant_id NULL`) are creatable/modifiable by platform-admin (`loa-auth-admin`) only.
7. Deleting a catalog endpoint with existing grants/overrides returns `409` (no silent breakage — per `tenant-endpoint-catalog.md` §6.5).
8. The resolved JWT `permissions` payload contains only `<level>:<path>` entries where `level >= read` (never `deny` or `none`).
9. Users must be tenant members before their tenant-scoped group grants take effect (enforced via `tenancy.md` §5.2 / `tenantService::isMember()` — see §11 Known Gap).

---

## 11. Security Checklist

- [ ] All admin routes behind `auth` (web guard) + `web.admin` + `users.manage`
- [ ] Platform-wide (`tenant_id NULL`) grant/override creation limited to `loa-auth-admin`
- [ ] CSRF on every `POST`/`DELETE` admin form
- [ ] Param-aware path matching (`{param}` syntax, not literal-only)
- [ ] Deny wins: any `deny` grant short-circuits group resolution
- [ ] User override replaces group resolution (not merges)
- [ ] Tenant membership enforced before tenant-scoped grants apply (see Known Gaps)
- [ ] Closed-by-default: catalog entry with no grant → `none` → 403/JSON or UI lock
- [ ] Deleting a catalog endpoint with existing grants → `409` (opt-in `force`)
- [ ] `GET /api/auth/access` validates tenant `status = 'active'` before resolving
- [ ] Override cannot grant access to an endpoint the user's groups were explicitly denied (unless the override itself grants it) — overrides are an explicit admin action, auditable

---

## 12. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|---------------|------------------|
| Granting levels by editing DB directly | Bypasses audit trail, no tenant-membership check, no catalog validation | Use admin UI or API routes |
| Granting a `deny` to work around a wrong grant | Over-complicated; `deny` is for exceptions | Use correct `level`; reserve `deny` for exceptions only |
| Caching resolved permissions beyond token lifetime | Stale permissions after group/grant change | Resolve at login/refresh; re-validate via `/api/auth/access` if needed |
| Allowing non-admins to manage grants | Privilege escalation | `users.manage` + `web.admin` required |
| Granting platform-wide levels to tenant-scoped groups | Cross-tenant leakage | Platform-wide grants only for `tenant_id NULL` groups |
| Skipping tenant-membership check before tenant-scoped grants | Scope escalation — non-member gets tenant group permissions | Enforce `tenantService::isMember()` in resolution (§4 step 3) |

---

## 13. Known Gaps / Follow-Ups

1. **Tenant membership enforcement**: The current `AuthorizationService::addToGroup()` does not verify the user is a member of the group's tenant before adding them to a tenant-scoped group. This must be patched in `AuthorizationService` per `tenant-endpoint-catalog.md` §3.2 invariant and `tenancy.md` §9.4 (carried over from SESSION-PROMPT "In Progress").
2. **Real-time revocation**: JWT `permissions` are valid for the token lifetime. For real-time grant/override changes, add an optional `GET /api/auth/access` call in the middleware or a token-revocation mechanism.
3. **Bulk import command extension**: `permissions:import {app}` currently populates `route_policies` (claims model). Extend or add `permissions:import-endpoints {app}` to populate `tenant_app_endpoint` + default `tenant_endpoint_grant` rows from the app's `permissions.json`.

---

## 14. Implementation Inventory

| Layer | Item | Status |
|-------|------|--------|
| Kernel | `kernels/identity/tenancy.md` §3 (tenants, `user_tenants`, tenant-scoped groups) | Final, implemented |
| Kernel | `kernels/identity/entities/data-driven-permission-policy.md` (claims model) | Final, implemented (parallel model) |
| Kernel | `kernels/identity/rules/permission-resolution.md` (deny-wins, override-last) | Final, implemented |
| Kernel | `kernels/identity/entities/user-group.md` (group entity + `tenant_id`) | Draft, implemented |
| Assembly (spec) | `tenant-endpoint-catalog.md` (catalog table + routes) | Final v3.1, **spec only — not implemented** |
| Assembly (spec) | `group-permission-management.md` (group CRUD + claims grants) | Draft, **implemented** |
| Assembly (this) | `tenant-group-endpoint-grants.md` | **New spec** |
| Assembly (code) | Migration: `tenant_endpoint_grant`, `tenant_endpoint_override` tables | To implement |
| Assembly (code) | Model: `TenantEndpointGrant`, `TenantEndpointOverride` | To implement |
| Assembly (code) | Controller: extends `PermissionPolicyController` or new `EndpointGrantController` | To implement |
| Assembly (code) | Middleware: extend `ClaimPolicyMiddleware` for level-based enforcement | To implement |
| Assembly (code) | Controller: `AuthController::access()` — `GET /api/v1/auth/access` | To implement |
| Assembly (routes) | `routes/web.php` — group endpoint grant routes under `/admin/tenants/{tenant}/groups/{group}/endpoints` | To add |
| Assembly (routes) | `routes/api.php` — `GET /api/v1/auth/access` + admin API for grants/overrides | To add |

---

## 15. Dependency References

| Spec | Role |
|------|------|
| `tenant-endpoint-catalog.md` (Final v3.1) | Defines the catalog table (`tenant_app_endpoint`), admin routes, enforcement hook, session payload format |
| `kernels/identity/entities/data-driven-permission-policy.md` (Final v1.0) | Claims-based model (parallel); `permissions.json` import format; JWT `permissions` + `scopes` claims |
| `kernels/identity/tenancy.md` (Final v3.0) | `tenants`, `user_tenants`, tenant-scoped groups/grants, `jwt.tenant` middleware, membership model |
| `kernels/identity/rules/permission-resolution.md` | Resolution order: union of groups, deny-wins, user overrides last |
| `kernels/identity/entities/user-group.md` | Group entity with `tenant_id` scoping |
| `assemblies/loa-auth-platform/group-permission-management.md` | Existing group/user-permission admin UI + API patterns to extend |
| `assemblies/loa-auth-platform/admin-dashboard.md` §3.8 | Route group `/admin/tenants/*` — this spec adds `/groups/{group}/endpoints` under it |
| `assemblies/loa-auth-platform/web-ui.md` | Admin session lifecycle (`web.admin` gate); login destination resolution |
| `assemblies/loa-cert-platform/web-ui.md` §5 | Cert Platform permission-to-role mapping (consumes `permissions` claim) |
| `assemblies/loa-cert-platform/README.md` §11 | SSO callback contract; tenant apps validate JWT from Auth Platform |
