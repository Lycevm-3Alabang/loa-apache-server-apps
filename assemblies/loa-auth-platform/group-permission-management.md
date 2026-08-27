# LOA Auth Platform — Group & Permission Management
## Product Assembly Component Specification

**Version:** 3.0
**Status:** Final — v3.0 membership-ownership restructure (§12) complete; all §12.8 open questions resolved; v2.1 content remains Final and implemented except where §12 supersedes it (`AI-RULES.md` Rule 0: no code against Draft sections — now cleared)
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

> **Amended by §12 (v3.0 Draft):** the "Group Membership" section below is
> **replaced** by a Platform-permissions toggle panel + read-only membership
> overview; the add/remove group actions move to the tenant domain. See §12.2–§12.3.

**Route:** `GET /admin/users/{id}`

Shows a user's profile, group memberships, and effective permissions.

**Sections:**

1. **User Info** — email, name, status, created_at
2. **Group Membership** — list of groups with add/remove controls
3. **SSO Platform Permissions** — resolved permission list (read-only, computed)
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

## 6.4 Tenant Group Detail Page

> **Amended by §12 (v3.0 Draft):** this page's linked **members page**
> (`GET /admin/tenants/{tenant}/groups/{group}/members`) becomes the single
> write surface for tenant-scoped group membership — it gains add/remove
> controls per §12.4. The user detail page loses group write access entirely.

**Route:** `GET /admin/tenants/{tenant}/groups/{group}`

Tenant-scoped group detail page (primary navigation path: **Admin → Tenants → {tenant} → Groups → {group}**). Shows group info plus the same permission configuration as §6.3, so auth API keys are managed where tenant groups actually live.

**Sections:**

1. **Group Info** — name, description, priority, scope, member count
2. **SSO Platform Permissions** — editable checkbox grid of the auth API permission catalog; checked = granted to every member of the group (same pivot rows as §6.3, `tenant_id = NULL` on grants)
3. **Quick Actions** — links to endpoint grants and members pages

**Actions:**

| Action | Method | Route |
|--------|--------|-------|
| Update permissions | POST | `/admin/tenants/{tenant}/groups/{group}/permissions` |

**Behavior:**

1. Validates that `$group->tenant_id === $tenant->id` (404 otherwise)
2. Validates `permissions[]` as an array of existing permission IDs (empty array = revoke all)
3. Syncs the full granted map in a transaction — identical semantics to §6.3 (`granted` boolean per key, `tenant_id = NULL`)
4. Members receive the new claims on their next login (tokens carry claims frozen at issuance)

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

# Tenant Group Detail
POST   /admin/tenants/{tenant}/groups/{group}/permissions

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
| `assemblies/loa-cert-platform/legacy-e-cert-integration.md` §7.4 | Cert Platform permission-to-role mapping (level-based) |

---

# 12. v3.0 — Membership ownership restructure (DRAFT)

> **Directive:** the **tenant domain** owns mapping members onto its groups.
> The user route path is not a group-management surface. Tenant-scoped group
> membership lives exclusively under
> `admin/tenants/{tenant}/groups/{group}/members`; the user detail page keeps
> only platform-level control, expressed as a permission toggle.

## 12.1 Problem being removed

| Problem | Today | After |
|---|---|---|
| Wrong surface | `admin/users/{id}` "Group Membership" dropdown lists **every** group — platform AND tenant-scoped | User page exposes platform permissions only; tenant groups managed in tenant domain |
| Invariant hole | Adding a user to a tenant-scoped group from the user page does **not** create the `user_tenants` pivot → user holds tenant-group claims for a tenant they cannot enter | Invariant I1 enforced at every write path (§12.2) |
| Scattered UX | Group membership writable from three places (user page, platform group page, nowhere on tenant group page) | One write surface per scope: platform → Groups console; tenant → tenant group members page |

## 12.2 Decisions

| # | Decision | Choice |
|---|---|---|
| M1 | Tenant-group membership surface | Add/remove of members to a **tenant-scoped** group happens ONLY on the tenant group members page (`admin.tenants.group.members`) |
| M2 | User detail panel replaced | The generic "Group Membership" panel + dropdown on `admin/users/{id}` is **removed**; replaced by a read-only membership overview plus a **Platform permissions** toggle panel |
| M3 | Platform permissions panel | A toggle list of platform-level permissions. v1 ships exactly one entry: **Platform admin** = membership in `config('auth-web.admin_group')` (`loa-auth-admin`). The registry is additive — future platform permissions appear as additional toggles without UI restructuring ("to be clarified later") |
| M4 | Invariant I1 | `user ∈ tenant-scoped group ⇒ user ∈ that tenant's pivot`. Enforced in the service layer at every write path — no UI can produce an orphan claim |
| M5 | Membership cascade | Removing a user from a tenant removes them from **all** of that tenant's groups, in the same transaction as the pivot removal |
| M6 | Groups console scoping | Member add/remove on `/admin/groups/{id}` is restricted to platform groups (`tenant_id IS NULL`); requests for a tenant-scoped group redirect to that group's tenant members page instead of writing |
| M7 | API contract preserved, invariant enforced | `/api/v1/users/{id}/groups` POST/DELETE keep their shape; assigning a tenant-scoped group without existing pivot membership → **422** with copy: *"User must be a member of the tenant before being added to one of its groups."* (default — see Q1) |
| M8 | Last-admin lockout guard is global | The "cannot revoke own platform-admin" rule lives in the **service layer**, enforced identically on every write path — web toggle, Groups-console member remove, and API `DELETE /users/{id}/groups` targeting the admin group for self. Never a Blade-only check |

## 12.3 User detail page after v3.0

`GET /admin/users/{id}` sections:

1. **User Info** — unchanged
2. **Platform permissions** *(new)* — toggle per registry entry (v1: Platform admin).
   Zero-JS: each row is its own POST form styled as a switch.
   Route: `POST /admin/users/{id}/platform-permissions`
   (`admin.users.platform-permissions`) with `permission_key` + `granted`.
   Toggling Platform admin writes/removes `loa-auth-admin` membership and
   audits with the existing `admin_group.granted` / `admin_group.revoked`
   evidence keys. Consequence-bearing copy under the panel title:
   *"Platform admin grants full console access — Users, Tenants, Audit log."*
   **Self-guard at render time:** viewing your own record renders the
   Platform-admin toggle DISABLED with inline microcopy
   ("You can't change your own admin access") — the server-side rejection is
   a backstop, not the UX.
3. **Memberships** *(read-only)* — two lists:
   - Tenant memberships: name → link to `admin.tenants.{id}`
   - Tenant-scoped group memberships **assigned within those tenants**: group
     name → deep-link to `admin.tenants.{tid}.groups.{gid}.members`
   No write controls here by design (M1/M2).
4. **SSO Platform Permissions resolved list** — unchanged
5. **Permission Overrides** — unchanged

## 12.4 Tenant group members page after v3.0

`admin.tenants.group.members` gains write controls:

- **Add member** — a **two-tier candidate search** served by a NEW dedicated
  endpoint (do NOT reuse `admin.tenants.members.search` verbatim: that endpoint
  excludes existing tenant members, which would hide the primary audience):

  ```
  GET /admin/tenants/{t}/groups/{g}/members/search?q=…
      name: admin.tenants.group.members.search
  →   { data: [ { id, name, email, status, tier } ] }
  ```

  `tier` is determined server-side:
  - `'primary'` — user EXISTS in `user_tenants` for this tenant but is NOT in
    this group's `user_groups` pivot. Query: `WHERE user_tenants.tenant_id = {t}
    AND NOT EXISTS (SELECT 1 FROM user_groups ug WHERE ug.user_id = users.id
    AND ug.group_id = {g})`.
  - `'secondary'` — user does NOT exist in `user_tenants` for this tenant.
    Query: `WHERE NOT EXISTS (SELECT 1 FROM user_tenants ut WHERE ut.user_id =
    users.id AND ut.tenant_id = {t})`.

  Both tiers filter `users.status != 'disabled'` and `LIKE` on name/email.

  1. **Primary tier** — the everyday case: existing staff gaining the group.
     Adding writes only the `user_groups` row (+ audit `tenant.group_member_added`).
     No pivot write needed — user is already a tenant member.
  2. **Secondary tier** — users outside the tenant, visually separated and
     labelled *"Not a member yet — adding will also grant tenant access"*.
     Adding runs ONE transaction: `AuthorizationService::addUserToTenant()`
     (audits `tenant.member_added`) + `AuthorizationService::addToGroup()`
     (audits `tenant.group_member_added`). Invariant I1 therefore holds by
     construction — the pivot is created before the group row in the same
     transaction.

  The existing tenant-members search endpoint (`admin.tenants.members.search`)
  is untouched — it continues to serve the tenant-members page for adding new
  members to the tenant itself.
- **Remove member** (per row) — labelled **"Remove from group"**;
  `AuthorizationService::removeFromGroup()` ONLY; tenant membership untouched
  (audit `tenant.group_member_removed`). No cascade — this is a single-group
  removal, reversible by re-adding. Full removal off the tenant (which cascades
  all group memberships per M5) lives on the tenant members page.
- **Empty state** — zero members renders explanatory copy with the add control
  still visible (never an empty silent panel).
- Both routes validate `$group->tenant_id === $tenant->id`, else 404
  (existing convention).

### Existing tenant-members page

The existing `admin.tenants.members` page (tenant members list) retains its
"Remove from tenant" button. That button now links to the confirmation
interstitial (§12.4 bottom) instead of posting directly. The interstitial POST
targets the existing `admin.tenants.members` route with `action=remove` — no
new write route. The tenant-members page's "Add member" functionality is
unchanged (it adds users to the tenant, not to any specific group).

### Navigation affordances (discovery)

- Tenant show page (`admin.tenants.{id}`) renders each group as a chip/link →
  its members page, annotated with current member counts — otherwise the sole
  write surface for tenant groups is undiscoverable three levels deep.
- Members page carries breadcrumb navigation back to the tenant's groups.

### Destructive-action semantics

- The two removal verbs must never share a label: **"Remove from group"**
  (single row, reversible by re-adding) vs **"Remove from tenant"** on the
  members page (cascades M5). 
- Only the cascade case gets a confirmation interstitial (zero-JS GET page →
  POST button); single-group removal stays a direct POST. The interstitial is
  real plumbing, not a pattern note:

  ```
  GET /admin/tenants/{t}/members/{userId}/remove/confirm
      name: admin.tenants.members.remove.confirm
  ```

  Renders the blast radius ("{user} · {tenant} · {n} groups to be removed:
  {list}") with a single POST button targeting the EXISTING
  `admin.tenants.members` route (`action=remove`) — no new write route.
  The members-page "Remove from tenant" control becomes a LINK to this page.

- Post-cascade flash enumerates the blast radius:
  *"Removed {user} from {tenant} and {n} group(s)."*

## 12.5 Route changes

```
REMOVED  POST /admin/users/{id}/groups                              admin.users.groups.store
REMOVED  POST /admin/users/{id}/groups/{groupId}/remove             admin.users.groups.remove
NEW      GET  /admin/tenants/{t}/groups/{g}/members/search          admin.tenants.group.members.search      (two-tier candidates)
NEW      POST /admin/tenants/{t}/groups/{g}/members                 admin.tenants.group.members.store
NEW      POST /admin/tenants/{t}/groups/{g}/members/{userId}/remove admin.tenants.group.members.remove
NEW      GET  /admin/tenants/{t}/members/{userId}/remove/confirm    admin.tenants.members.remove.confirm    (cascade interstitial; posts to existing admin.tenants.members)
NEW      POST /admin/users/{id}/platform-permissions                admin.users.platform-permissions
SCOPED   POST /admin/groups/{id}/members[...]                       platform groups only (M6)
```

## 12.6 Data repair (I1 backfill)

Existing prod rows may violate I1 (group rows without pivot). Ship a
report-only artisan command, then apply the owner-chosen resolution (§12.8 Q3).
The dashboard attention item defined in `admin-dashboard-home.md` §4.2 surfaces
the count until clean.

**Artisan command:** `php artisan auth:repair-i1-violations`

**Query:**
```sql
SELECT u.email, u.name, g.name AS group_name, t.name AS tenant_name
FROM user_groups ug
JOIN users u ON u.id = ug.user_id
JOIN user_groups g ON g.id = ug.group_id
JOIN tenants t ON t.id = g.tenant_id
WHERE g.tenant_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM user_tenants ut
    WHERE ut.user_id = ug.user_id
      AND ut.tenant_id = g.tenant_id
  )
```

**Output format:** one line per violation: `{email} | {group_name} | {tenant_name}`

**Exit code:** 0 if clean, 1 if violations found (CI-friendly).

**Behavior:** report-only — no writes, no auto-grant, no deletions. Admins
resolve violations manually via "Remove from group" on the dashboard attention
item or the tenant group members page.

## 12.7 Audit events

| Event | Writer | Scope |
|---|---|---|
| `tenant.group_member_added` / `tenant.group_member_removed` | NEW — `tenantsGroupMemberStore()` / `tenantsGroupMemberRemove()` | Tenant group members page (add/remove from a tenant-scoped group) |
| `tenant.member_added` / `tenant.member_removed` | Existing — `addUserToTenant()` / `removeFromTenant()` | Tenant members page + secondary-tier add in group members page + M5 cascade |
| `admin_group.granted` / `admin_group.revoked` | NEW — `setPlatformPermission()` | User detail Platform-permissions toggle (toggles `loa-auth-admin` membership) |

Note: `admin_group.granted`/`revoked` are **distinct** from
`tenant.group_member_added`/`removed` — different scope (platform vs tenant),
different write surface (user detail toggle vs tenant group members page). The
`auditGroupMembership()` helper used by the existing platform-level
`groupsMembersStore`/`Remove` writes `user_group.added`/`user_group.removed`
(unchanged).

M5 cascade note: one tenant removal can emit several group-level events.
Each row still audits individually (evidence completeness), but the
user-facing flash summarizes the blast radius (§12.4); the admin-console-home
feed's weighting rules keep such bursts from crowding out security signals.

## 12.8 Open questions (resolved at promotion to Final)

| # | Question | Resolution |
|---|---|---|
| Q1 | API behavior for tenant-scoped assignment without membership | 422 reject (M7) |
| Q2 | Confirm M5 cascade on tenant-membership removal | Yes, cascade |
| Q3 | Backfill policy: report-only then manual, or auto-grant missing pivots | **Report-only.** The artisan command (§12.6) lists violations. Admins resolve via "Remove from group" on the dashboard attention item or the tenant group members page. No auto-grant. No inline "Grant tenant access" action in v1. If auto-grant is desired later, it ships as a separate migration + UI change. |
| Q4 | Should the platform-permission registry live in config/`permissions.json` now? | Later — hardcoded single entry in v1 |

## 12.9 Implementation inventory (planned)

| File | Change |
|---|---|
| `WebAdminController.php` | Rewrite `showUser()` data (drop `$allGroups` dropdown); delete `storeUserGroup()`/`removeUserGroup()`; add `setPlatformPermission()`, `tenantsGroupMemberSearch()` (two-tier), `tenantsGroupMemberStore()`, `tenantsGroupMemberRemove()`, `tenantsMemberRemoveConfirm()` (GET interstitial); scope `groupsMembersStore/Remove()` (M6); cascade in tenant removal path (M5); service-layer M8 guard |
| `resources/views/admin/users/show.blade.php` | Replace panel per §12.3 |
| `resources/views/admin/tenants/group-members.blade.php` | Add/remove controls + two-tier search UI per §12.4 |
| `resources/views/admin/tenants/member-remove-confirm.blade.php` | NEW — cascade confirmation interstitial (§12.4) |
| `resources/views/admin/groups/show.blade.php` | Tenant-group member controls removed / redirected (M6) |
| `AuthorizationService` | `addToGroup()`: add I1 guard — if group has `tenant_id`, verify user has pivot in `user_tenants` for that tenant; if not, throw `HttpException(422)` with copy *"User must be a member of the tenant before being added to one of its groups."* (M7). `removeFromGroup()`: unchanged (removes group row only). New transactional helper for secondary-tier add: `addUserToTenant()` + `addToGroup()` in one DB transaction (M4). Global last-admin guard (M8) on all write paths. |
| `app/Console/Commands/RepairI1Violations.php` | NEW — `auth:repair-i1-violations` artisan command per §12.6 (report-only, exit 1 if violations) |
| Tests | Feature coverage for every §12.10 item |

## 12.10 Test checklist

- [ ] User page renders NO group dropdown; Platform-permissions toggle reflects `loa-auth-admin` truth and round-trips with audit rows
- [ ] Viewing OWN record: toggle renders disabled + microcopy (no server round-trip needed); server backstop still rejects direct POST
- [ ] M8 guard holds on ALL paths: web toggle, Groups-console member remove, and API DELETE of own admin-group membership all rejected
- [ ] Admin cannot revoke own platform-admin (last-admin guard)
- [ ] "Platform administrators cannot be deactivated" still enforced after restructure
- [ ] Candidate search endpoint returns tiered payload: primary = existing tenant members NOT in the group; secondary = outsiders, labelled in UI
- [ ] Cascade removal: confirm GET page renders blast radius (user · tenant · group list); its POST targets existing admin.tenants.members route
- [ ] "Remove from tenant" control links to the confirm page (not a direct POST)
- [ ] Cascade case shows confirmation interstitial; single-group remove does not
- [ ] Group members page add: non-member user gets pivot + group + both audit events
- [ ] Group members page add: existing member gets group + single audit event
- [ ] Remove from group preserves tenant membership
- [ ] Removing tenant membership cascades all that tenant's group rows (M5), audits each, and flash enumerates "{n} group(s)"
- [ ] Tenant show page exposes per-group links with member counts; breadcrumbs on members page
- [ ] Zero-member group renders empty-state copy with add control visible
- [ ] Tenant group accessed under wrong tenant → 404
- [ ] API assign tenant-scoped group without membership → 422 (Q1 default)
- [ ] Groups console write to tenant-scoped group redirects, no write occurs (M6)
- [ ] Repair command reports pre-existing violations accurately
- [ ] Primary tier search returns tenant members NOT in the group (tier = 'primary')
- [ ] Secondary tier search returns non-tenant users (tier = 'secondary')
- [ ] Secondary tier add creates pivot + group row in one transaction (I1 holds by construction)
- [ ] Secondary tier add: user already in tenant but not in group → classified as primary (not secondary)
- [ ] "Remove from tenant" cascade actually removes all group rows (M5), audits each, flash enumerates "{n} group(s)"
- [ ] Confirmation interstitial renders blast radius correctly (user · tenant · group list)
- [ ] Platform-permissions toggle audit keys are `admin_group.granted` / `admin_group.revoked` (not `user_group.added`)
- [ ] M6: platform group page with tenant-scoped group ID redirects to tenant group members page

---

# 13. Changelog

| Version | Change |
|---|---|
| 2.1 | Last implemented state (Final): API surface + admin UI for groups/permissions as built |
| 3.0 | DRAFT — §12 membership ownership restructure: tenant domain becomes sole write surface for tenant-scoped group membership (M1); user detail swaps Group-Membership dropdown for Platform-permissions toggle (M2/M3); invariant I1 + cascade (M4/M5); Groups-console scoping (M6); API invariant enforcement (M7). Pending §12.8 |
| 3.0 rev.2 | SME flow review folded into §12: two-tier candidate search (primary = existing members not in group — verbatim search reuse was a functional defect); discovery affordances (tenant-page group chips, breadcrumbs); disambiguated removal labels + cascade-only confirmation interstitial + blast-radius flash; self-toggle renders disabled with consequence copy; empty-state requirement; Q3 extended (grant-pivot surface gated on backfill decision); checklist expanded |
| 3.0-draft.2 | Second-pass review fixes: dedicated two-tier search endpoint defined (`admin.tenants.group.members.search`) + inventoried; cascade confirmation interstitial fully plumbed (`admin.tenants.members.remove.confirm` GET page posting to existing route) + view added to inventory; M8 global last-admin guard (all write paths incl. API) + tests; M7 422 copy specified; "inherited" wording corrected to assigned-within-tenant |
| 3.0-draft.3 | Third-pass review: §12.4 two-tier search query shape specified (primary = tenant members NOT in group; secondary = non-tenant users; tier determined server-side); I1 enforcement location clarified (service-layer guard in `AuthorizationService::addToGroup()`); removal semantics disambiguated (group-only = single-row, no cascade; tenant = cascade M5); §12.6 repair command implementation detail (`auth:repair-i1-violations`, report-only, exit 1); §12.7 audit key naming aligned (writers + scope column added); Q3 resolved (report-only, no auto-grant in v1); §12.9 inventory expanded (AuthorizationService guard, repair command row); §12.10 checklist expanded to 26 items |
| 3.0 | Promoted to Final: all §12.8 questions resolved, all gaps from third-pass review filled |
