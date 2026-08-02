# LOA Auth Platform — Group & Permission Management
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

API and admin UI for managing user-group assignments and permission grants.

It answers:

> **"Who belongs to which groups, and what permissions do they have?"**

This spec extends the existing `admin-dashboard.md` (v3) and `web-ui.md` (v1.2) to cover the gap between the existing `AuthorizationService` service methods and the missing API routes + admin UI.

---

# 2. Ownership

## Owns

- Group CRUD (create, list, delete)
- Group-to-permission assignment (grant/revoke)
- User-to-group assignment (add/remove)
- User-level permission overrides (grant/revoke)
- Effective permission resolution display
- API routes for programmatic access (consumed by tenant apps like e-cert)

## Does Not Own

- Tenant CRUD (handled by `admin-dashboard.md` v2)
- Tenant group management (handled by `admin-dashboard.md` v2)
- JWT token issuance (handled by `IdentityService`)
- Permission definitions (global catalog, managed via seeders)
- Authorization decisions in tenant apps (e-cert decides what to do with permissions)

---

# 3. Architecture Note

The `AuthorizationService` already implements all permission resolution logic:

- `getGroups($userId, $tenantId)` — returns group names
- `getPermissions($userId, $tenantId)` — resolves effective permissions (group union, deny-wins, user overrides)
- `addToGroup($userId, $groupId)` — add user to group
- `removeFromGroup($userId, $groupId)` — remove user from group
- `grantGroupPermission($groupId, $permissionKey, $tenantId)` — grant permission to group
- `revokeGroupPermission($groupId, $permissionKey, $tenantId)` — revoke permission from group

This spec adds **API routes** and **admin UI** that call these existing service methods.

---

# 4. Permission Naming Convention

Permissions follow the `{context}.{resource}.{action}` pattern defined in `kernels/identity/entities/permission.md`.

**App-scoped permissions (examples):**

| Key | Description |
|-----|-------------|
| `cert.certificates.issue` | Issue certificates |
| `cert.certificates.manage` | Full certificate management (issue, revoke, delete) |
| `cert.templates.manage` | Manage certificate templates |
| `cert.events.manage` | Manage events |
| `cert.certificates.view_all` | View all certificates (not just own) |
| `consult.appointments.create` | Create consultation appointments |
| `consult.appointments.view` | View consultation appointments |
| `users.view` | View user list |
| `users.manage` | Manage users (enable/disable, create) |

Permission definitions are seeded via database migrations. This spec does not manage the permission catalog — it manages **grants** (which groups/users have which permissions).

---

# 5. API Surface

All API routes are prefixed with `/api/v1` and require JWT authentication with the `users.manage` permission.

## 5.1 Groups

### `GET /api/v1/groups`

List all groups.

**Response (200):**

```json
{
  "data": [
    {
      "id": "grp_abc123",
      "name": "Faculty",
      "description": "All teaching staff",
      "tenant_id": null,
      "members_count": 15,
      "created_at": "2025-01-15T08:00:00Z"
    }
  ]
}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tenant_id` | string | null | Filter by tenant (null = platform-global groups) |

### `POST /api/v1/groups`

Create a new group.

**Request:**

```json
{
  "name": "Faculty",
  "description": "All teaching staff",
  "tenant_id": null
}
```

**Response (201):**

```json
{
  "id": "grp_abc123",
  "name": "Faculty",
  "description": "All teaching staff",
  "tenant_id": null,
  "created_at": "2025-01-15T08:00:00Z"
}
```

**Errors:**

| Status | Condition |
|--------|-----------|
| 409 | Group name already exists (for the given tenant scope) |
| 422 | Validation failed |

### `DELETE /api/v1/groups/{id}`

Delete a group. Removes all user memberships and permission grants for this group.

**Response (204):** No content.

**Errors:**

| Status | Condition |
|--------|-----------|
| 404 | Group not found |

## 5.2 Group Permissions

### `GET /api/v1/groups/{id}/permissions`

List permissions assigned to a group.

**Response (200):**

```json
{
  "group_id": "grp_abc123",
  "group_name": "Faculty",
  "permissions": [
    {
      "id": "perm_xyz",
      "key": "cert.certificates.issue",
      "description": "Issue certificates",
      "granted": true,
      "tenant_id": "tenant_loa"
    }
  ]
}
```

### `POST /api/v1/groups/{id}/permissions`

Sync permissions for a group (replaces all existing grants).

**Request:**

```json
{
  "permissions": [
    { "permission_key": "cert.certificates.issue", "granted": true },
    { "permission_key": "cert.templates.manage", "granted": true },
    { "permission_key": "cert.certificates.manage", "granted": false }
  ],
  "tenant_id": "tenant_loa"
}
```

**Behavior:**

1. Resolve each `permission_key` to a permission ID
2. For each permission, call `AuthorizationService::grantGroupPermission()` or `revokeGroupPermission()`
3. Set `tenant_id` on each grant

**Response (200):**

```json
{
  "status": "success",
  "group_id": "grp_abc123",
  "permissions_count": 3
}
```

**Errors:**

| Status | Condition |
|--------|-----------|
| 404 | Group not found |
| 422 | Validation failed (invalid permission key) |

## 5.3 User Groups

### `GET /api/v1/users/{id}/groups`

List groups a user belongs to.

**Response (200):**

```json
{
  "user_id": "usr_abc123",
  "groups": [
    {
      "id": "grp_abc123",
      "name": "Faculty",
      "description": "All teaching staff",
      "tenant_id": null
    }
  ]
}
```

### `POST /api/v1/users/{id}/groups`

Add a user to a group.

**Request:**

```json
{
  "group_id": "grp_abc123"
}
```

**Response (201):**

```json
{
  "status": "success",
  "user_id": "usr_abc123",
  "group_id": "grp_abc123"
}
```

**Errors:**

| Status | Condition |
|--------|-----------|
| 404 | User or group not found |
| 409 | User already in group |

### `DELETE /api/v1/users/{id}/groups/{groupId}`

Remove a user from a group.

**Response (204):** No content.

**Errors:**

| Status | Condition |
|--------|-----------|
| 404 | User or group not found |

## 5.4 User Permissions (Overrides)

### `GET /api/v1/users/{id}/permissions`

List a user's effective permissions (resolved from groups + overrides).

**Response (200):**

```json
{
  "user_id": "usr_abc123",
  "permissions": [
    "cert.certificates.issue",
    "cert.templates.manage",
    "users.view"
  ],
  "groups": ["Faculty"],
  "overrides": [
    {
      "permission_key": "cert.certificates.manage",
      "granted": true,
      "source": "user_override"
    }
  ]
}
```

### `POST /api/v1/users/{id}/permissions`

Grant or revoke a user-level permission override.

**Request:**

```json
{
  "permission_key": "cert.certificates.manage",
  "granted": true,
  "tenant_id": "tenant_loa"
}
```

**Behavior:**

1. Resolve `permission_key` to a permission ID
2. Insert or update the `user_permission` pivot with `granted` and `tenant_id`
3. This overrides group-level grants for this specific user

**Response (200):**

```json
{
  "status": "success",
  "user_id": "usr_abc123",
  "permission_key": "cert.certificates.manage",
  "granted": true
}
```

### `DELETE /api/v1/users/{id}/permissions/{permissionKey}`

Remove a user-level permission override (revert to group-level resolution).

**Response (204):** No content.

---

# 6. Admin UI

## 6.1 User Detail Page

**Route:** `GET /admin/users/{id}`

Shows a user's profile, group memberships, and effective permissions.

**Sections:**

1. **User Info** — email, name, status, created_at
2. **Group Membership** — list of groups with add/remove controls
3. **Effective Permissions** — resolved permission list (read-only, computed)
4. **Permission Overrides** — user-level overrides with grant/revoke controls

**Actions:**

| Action | Method | Route |
|--------|--------|-------|
| Add to group | POST | `/admin/users/{id}/groups` |
| Remove from group | POST | `/admin/users/{id}/groups/{gid}/remove` |
| Grant permission override | POST | `/admin/users/{id}/permissions` |
| Revoke permission override | POST | `/admin/users/{id}/permissions/{key}/remove` |

## 6.2 Group List Page

**Route:** `GET /admin/groups`

Shows all groups with member count.

**Columns:** name, description, scope (platform/tenant), members, actions

**Actions:** View detail, create new group

## 6.3 Group Detail Page

**Route:** `GET /admin/groups/{id}`

Shows group info, assigned permissions, and members.

**Sections:**

1. **Group Info** — name, description, scope
2. **Permissions** — checkbox grid (permission × granted/denied)
3. **Members** — list of users in this group

**Actions:**

| Action | Method | Route |
|--------|--------|-------|
| Update permissions | POST | `/admin/groups/{id}/permissions` |
| Add member | POST | `/admin/groups/{id}/members` |
| Remove member | POST | `/admin/groups/{id}/members/{userId}/remove` |

---

# 7. Route Summary

## 7.1 API Routes

All routes require `jwt.auth` + `jwt.permission:users.manage`.

```
# Groups
GET    /api/v1/groups
POST   /api/v1/groups
DELETE /api/v1/groups/{id}

# Group Permissions
GET    /api/v1/groups/{id}/permissions
POST   /api/v1/groups/{id}/permissions

# User Groups
GET    /api/v1/users/{id}/groups
POST   /api/v1/users/{id}/groups
DELETE /api/v1/users/{id}/groups/{groupId}

# User Permissions
GET    /api/v1/users/{id}/permissions
POST   /api/v1/users/{id}/permissions
DELETE /api/v1/users/{id}/permissions/{permissionKey}
```

## 7.2 Admin Web Routes

All routes require `auth` (web guard) + `web.admin`.

```
# Groups
GET    /admin/groups
GET    /admin/groups/{id}
POST   /admin/groups
POST   /admin/groups/{id}/permissions
POST   /admin/groups/{id}/members
POST   /admin/groups/{id}/members/{userId}/remove

# User Detail
GET    /admin/users/{id}
POST   /admin/users/{id}/groups
POST   /admin/users/{id}/groups/{groupId}/remove
POST   /admin/users/{id}/permissions
POST   /admin/users/{id}/permissions/{key}/remove
```

---

# 8. Implementation Inventory

## 8.1 New Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/GroupController.php` | Group CRUD + group-permission API |
| `app/Http/Controllers/UserGroupController.php` | User-group + user-permission API |
| `resources/views/admin/groups/index.blade.php` | Group list page |
| `resources/views/admin/groups/show.blade.php` | Group detail + permissions page |
| `resources/views/admin/users/show.blade.php` | User detail page |

## 8.2 Modified Files

| File | Change |
|------|--------|
| `routes/api.php` | Add group + user-group + user-permission routes |
| `routes/web.php` | Add admin group + user detail routes |
| `app/Http/Controllers/WebAdminController.php` | Add group + user detail methods |
| `resources/views/admin/users/index.blade.php` | Add link to user detail page |
| `resources/views/layouts/admin.blade.php` | Add "Groups" to admin nav |

## 8.3 Existing Services (No Changes)

| Service | Methods Used |
|---------|-------------|
| `AuthorizationService` | `addToGroup()`, `removeFromGroup()`, `grantGroupPermission()`, `revokeGroupPermission()`, `getPermissions()`, `getGroups()`, `hasPermission()` |
| `IdentityService` | `getUser()` |

---

# 9. Security Checklist

- [ ] All admin routes behind `auth` (web guard) + `web.admin` group check
- [ ] All API routes behind `jwt.auth` + `jwt.permission:users.manage`
- [ ] CSRF on every admin `POST` form
- [ ] Group deletion removes all memberships and grants (cascade)
- [ ] Permission resolution is never cached beyond access token lifetime
- [ ] User-level overrides are tenant-scoped (cannot grant cross-tenant)
- [ ] Self-group-management forbidden (admin cannot remove own admin group)
- [ ] No permission escalation: admin cannot grant permissions they don't have
- [ ] Group names are unique per tenant scope

---

# 10. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Managing permissions via direct DB manipulation | Bypasses audit trail, breaks resolution | Use API or admin UI |
| Granting permissions to users directly without groups | Bypasses group-level management | Prefer group grants; use user overrides sparingly |
| Caching effective permissions across requests | Stale permissions after group change | Resolve per-request (within token lifetime) |
| Allowing non-admins to manage groups | Privilege escalation | `users.manage` permission required |
| Granting permissions across tenants | Cross-tenant leakage | Always scope grants to a tenant |

---

# 11. Dependency References

| Spec | Role |
|------|------|
| `admin-dashboard.md` | Existing admin UI pattern, tenant group management |
| `web-ui.md` | Admin session lifecycle, login destination |
| `kernels/identity/entities/permission.md` | Permission naming convention |
| `kernels/identity/entities/user-group.md` | Group entity definition |
| `kernels/identity/rules/permission-resolution.md` | Resolution algorithm (union, deny-wins, overrides) |
| `kernels/identity/tenancy.md` | Tenant-scoped groups and grants |
| `assemblies/loa-cert-platform/README.md` | Cert Platform SSO callback contract |
| `assemblies/loa-cert-platform/web-ui.md` | Cert Platform permission-to-role mapping |
