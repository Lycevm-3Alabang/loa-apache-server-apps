# Identity Kernel — Tenancy Layer
## Platform Kernel Specification

**Version:** 3.0
**Status:** Final
**Layer:** Platform Kernel (Identity)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Extends the Identity Kernel from a single, platform-wide identity space to a **multi-tenant** model where each tenant is an external client organization with its own users, groups, and endpoint permissions — while keeping a single source of truth for identity.

It answers:

> **"Which tenant is this user acting within?"**
> **"Which groups exist for that tenant?"**
> **"May this user call this endpoint in this tenant?"**

It complements the Organization Kernel (`kernels/organization.md`), which defines the `Tenant` as an isolated deployment boundary containing organizations/branches. This spec owns the **identity and authorization** side of tenancy: tenant scoping of users, groups, and permission grants, plus tenant context in JWT tokens.

---

# 2. Scope

## Owns

- `Tenant` identity entity (slug, status, app URLs, redirect origins)
- `UserTenant` membership (which users operate in which tenants)
- Tenant-scoped `UserGroup` definitions
- Tenant-scoped permission grants (`UserGroupPermission`, `UserPermission`)
- Tenant context resolution at login
- Tenant claims in JWT tokens and their validation
- Tenant administration (platform admins) — see `assemblies/loa-auth-platform/admin-dashboard.md`

## Does Not Own

- Organization / branch hierarchy (Organization Kernel)
- Tenant business data (Consult, Cert contexts)
- Tenant provisioning of databases or infrastructure
- Global (platform) identity concerns not already in this kernel

---

# 3. Concepts

## 3.1 Tenant (identity entity)

A tenant is an external client organization operating on the platform. It maps 1:1 to the Organization Kernel's `Tenant` boundary.

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | uuid | PK | Unique identifier |
| slug | string | unique, required | URL-friendly identifier (e.g. `ccs`) |
| name | string | required | Display name (e.g. "CCS Consultancy") |
| status | enum | `active` / `suspended` | `suspended` revokes tenant-scoped access |
| app_url | string | nullable | Primary application URL |
| redirect_origins | json | default `[]` | Allowed login redirect origins (replaces per-tenant `AUTH_ALLOWED_REDIRECTS`) |
| created_at / updated_at | timestamp | auto | Audit timestamps |

**Invariants:**
1. `slug` is immutable once a tenant has issued tokens
2. `redirect_origins` is a strict-origin list (`scheme://host[:port]`)
3. A `suspended` tenant cannot be the target of a login redirect and rejects all tenant-scoped tokens

## 3.2 UserTenant (membership)

A many-to-many membership between users and tenants.

**Attributes:** user_id, tenant_id, created_at

**Invariants:**
1. A user can belong to multiple tenants
2. A user can only authenticate into a tenant context they belong to (except platform admins)
3. Membership is required for tenant-scoped permission resolution

## 3.3 Tenant-Scoped UserGroup

`user_groups.tenant_id` (nullable) is added:

| tenant_id | Meaning |
|-----------|---------|
| `NULL` | **Platform-global group** (e.g. `loa-auth-admin`) — applies in every tenant |
| set | **Tenant group** — exists only within that tenant |

**Invariants:**
1. Group name uniqueness is enforced per scope: `(tenant_id, name)`; platform groups keep a globally unique name
2. A tenant group cannot be granted to a user who is not a member of that tenant
3. Tenant deletion cascades to its groups and membership rows

## 3.4 Tenant-Scoped Permission Grants

Permission **definitions** stay global (the endpoint → key catalog, `permissions` table). Only **grants** are tenant-scoped:

- `user_group_permission.tenant_id` (nullable) — a grant of permission `P` to group `G` scoped to a tenant; `NULL` means platform-wide (applies in every tenant)
- `user_permission.tenant_id` (nullable) — a per-user override scoped to a tenant; `NULL` means platform-wide

**Invariants:**
1. A grant with `tenant_id` set only takes effect for groups/members of that tenant
2. A `NULL` grant applies in all tenants (including future ones)
3. Permission keys and `endpoint_pattern` remain the global catalog — per-endpoint enforcement per tenant is expressed through scoped grants

---

# 4. Data Model

```sql
tenants (
  id uuid PK,
  slug varchar UNIQUE NOT NULL,
  name varchar NOT NULL,
  status enum('active','suspended') DEFAULT 'active',
  app_url varchar NULL,
  redirect_origins json NULL,
  created_at, updated_at
)

user_tenants (
  user_id uuid FK -> users(id) CASCADE,
  tenant_id uuid FK -> tenants(id) CASCADE,
  created_at, updated_at,
  PRIMARY KEY (user_id, tenant_id)
)

user_groups (
  ... existing ...,
  tenant_id uuid NULL FK -> tenants(id) CASCADE,
  UNIQUE (tenant_id, name)   -- uniqueness also enforced in service layer
)

user_group_permission (
  user_group_id FK,
  permission_id FK,
  granted boolean DEFAULT 1,
  tenant_id uuid NULL FK -> tenants(id) CASCADE,   -- NEW
  created_at, updated_at,
  PRIMARY KEY (user_group_id, permission_id, tenant_id)
)

user_permission (
  user_id FK,
  permission_id FK,
  granted boolean DEFAULT 1,
  tenant_id uuid NULL FK -> tenants(id) CASCADE,   -- NEW
  created_at, updated_at,
  PRIMARY KEY (user_id, permission_id, tenant_id)
)
```

Note: MySQL treats `NULL` values in a composite unique key as distinct, so `(tenant_id, name)` uniqueness is **also enforced at the application layer** (`TenantService`/`UserGroupService`).

---

# 5. Tenant Context Resolution

## 5.1 At Login

The login context is the tenant whose `redirect_origins` matches the incoming `?redirect=` origin:

| Redirect origin | Matched tenant | Context |
|-----------------|----------------|---------|
| matches a tenant's `redirect_origins` | that tenant | tenant context |
| matches no tenant | — | direct access (admin-only, per `web-ui.md` §4.1) |

The tenant must be `active`. Membership is then verified: the user must belong to the matched tenant **or** be a platform admin.

## 5.2 At Token Validation (tenant apps)

Each tenant app configures its own identity via env (e.g. `TENANT_SLUG`). A new `jwt.tenant` middleware checks that the token's `tenant.slug` claim equals the app's configured tenant:

- mismatch → `403` (token from another tenant)
- tenant `suspended` → `403`

This guarantees tenant isolation even though tokens are validated locally with the shared secret.

---

# 6. Token Claims (v3.0)

```
{
  "sub":        "<user_id>",
  "tenant":     { "id": "<tenant_id>", "slug": "<tenant_slug>" },
  "groups":     [ "<global_group>", "<tenant_group>" ],
  "permissions":[ "<scoped_permission_key>" ],
  "type":       "access" | "refresh",
  "exp":        ...,
  "jti":        ...
}
```

- `tenant` is set only for tenant-context logins; platform-admin logins may omit it (dashboard session, not JWT).
- `groups` and `permissions` claims are computed for the login tenant context (see §7).
- The `jwt.permission:{key}` middleware continues to check claim membership; the `jwt.tenant` middleware (new) enforces scope.

---

# 7. Permission Resolution within a Tenant

```
permissions(user, tenant) =
  ∪ group grants where
      (group.tenant_id IS NULL OR group.tenant_id = tenant.id)      -- platform + tenant groups
      AND (grant.tenant_id IS NULL OR grant.tenant_id = tenant.id)  -- platform-wide + tenant grants
  ± user overrides where
      (override.tenant_id IS NULL OR override.tenant_id = tenant.id)

allowed(user, tenant, permissionKey) =
  key ∈ permissions(user, tenant)  AND  tenant.status = 'active'
```

**Deny wins:** a `granted = 0` for a key in any applicable grant denies the key; a user override can re-grant it.

**Order:** platform-global grants apply in every tenant; tenant grants add within that tenant; user overrides apply last.

---

# 8. Platform vs Tenant Scope

| Capability | Platform (NULL tenant) | Tenant (scoped) |
|------------|------------------------|-----------------|
| Groups | `loa-auth-admin` etc. | "Faculty", "Admissions" within one tenant |
| Permission grants | Applies in all tenants | Applies in that tenant only |
| Dashboard access | `web.admin` (session) | — |
| Login destination | `/admin/users` | tenant app fragment redirect |
| Tenant isolation | — | `jwt.tenant` middleware enforces |

---

# 9. Business Rules

1. A user authenticates with email + password; the tenant context is resolved from the redirect origin (§5.1)
2. Tenant context requires `user_tenants` membership or platform-admin status
3. Tokens embed the tenant scope; tenant apps enforce it via `jwt.tenant`
4. Suspending a tenant rejects its tenant-scoped access tokens and blocks refresh within that tenant (refresh-token rows for the tenant's users remain, but validation fails while `suspended`)
5. Deleting a tenant cascades to its groups, grants, and memberships
6. Platform admins (members of `loa-auth-admin`) are never scoped by tenant membership
7. Tenant-scoped permission checks never apply platform groups' tenant grants across tenants

---

# 10. Security Checklist

- [ ] Tenant context is never derived from user input alone — resolved from allowlisted redirect origin (or explicit verified claim)
- [ ] `jwt.tenant` middleware on every tenant API route (isolation across apps)
- [ ] Suspended tenants reject all tenant-scoped tokens
- [ ] Group/grants CRUD enforced by platform admins only
- [ ] No cross-tenant data via permission resolution (scope is always explicit)
- [ ] `redirect_origins` strict-origin validation
- [ ] Application-layer uniqueness for `(tenant_id, name)` and global group names

---

# 11. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Global permission grants leaking across tenants | Cross-tenant escalation | Scoped grants; `NULL` grants are explicitly platform-wide |
| Relying on JWT claims alone for tenant isolation | Claims can be stale | Enforce `tenant.slug` at the tenant app (`jwt.tenant`) |
| Deriving tenant from an unverified header/param | Tenant spoofing | Redirect-origin allowlist or verified claims |
| Per-tenant copies of the permission catalog | Duplication | Global catalog + scoped grants |
| Tenant groups without membership checks | Scope escalation | `user_tenants` gate before grants apply |

---

# 12. Public Contracts

### TenantService (new)

```
createTenant(data) → Tenant
updateTenant(tenantId, data) → Tenant
suspendTenant(tenantId) → void
activateTenant(tenantId) → void
getTenant(tenantId) → Tenant
getTenantBySlug(slug) → Tenant
resolveTenantByRedirectOrigin(origin) → ?Tenant
addUserToTenant(userId, tenantId) → void
removeUserFromTenant(userId, tenantId) → void
isMember(userId, tenantId) → boolean
```

### AuthorizationService (extended)

```
hasPermission(userId, tenantId, permissionKey) → boolean
getPermissions(userId, tenantId) → Permission[]
getGroups(userId, tenantId) → UserGroup[]
grantGroupPermission(groupId, permissionKey, tenantId = null) → void
revokeGroupPermission(groupId, permissionKey, tenantId = null) → void
```

### TokenService (extended)

```
generateTokenPair(user, tenant = null) → TokenPair
validateToken(token) → Claims        // includes tenant
```

---

# 13. Migration Path

Phase 1 — schema: add `tenants`, `user_tenants`, nullable `tenant_id` columns, and migration back-fill (existing global groups/grants keep `NULL` = platform-wide). No behavior change.

Phase 2 — services: introduce `TenantService`; extend `AuthorizationService`/`IdentityService` signatures with an optional `tenantId`; embed `tenant` claim.

Phase 3 — enforcement: add `jwt.tenant` middleware; tenant apps set `TENANT_SLUG`; login redirect resolution reads the `tenants` table.

Phase 4 — admin UI: tenant management in the admin dashboard (see `admin-dashboard.md`).

---

# 14. Dependency References

| Spec | Role |
|------|------|
| `kernels/organization.md` | `Tenant` boundary concept |
| `kernels/identity/README.md` | Identity Kernel v3.0 home |
| `kernels/identity/entities/user-group.md` | Group scoping |
| `kernels/identity/entities/permission.md` | Global permission catalog |
| `assemblies/loa-auth-platform/web-ui.md` | Login destination resolution |
| `assemblies/loa-auth-platform/admin-dashboard.md` | Tenant + group + grant administration |
