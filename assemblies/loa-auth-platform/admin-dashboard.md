# LOA Auth Platform — Admin Dashboard (User Management)
## Product Assembly Component Specification

**Version:** 3.0
**Status:** Final (v1 + v2 + v3 + v4 implemented). v1.1 note — the console chrome (`layouts/admin`) is now **shared** with the universal dashboard at `/` (`dashboard-account.md` v1.1): non-admins render the topbar without Users/Tenants/Audit-log links, sign-out moved to shared `POST /logout`; every `/admin/**` route stays platform-admin-only via `web.admin` (403)
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Server-rendered, session-authenticated admin interface for managing platform users.

It answers:

> **"Who are the users, and how do I control their access?"**

**v1 scope:** list users, search, and enable/disable accounts.

**v3 scope:** admin user creation (without self-registration).

**v4 scope:** group and permission administration (see `group-permission-management.md`).

Out of scope for v3: editing user details, group/permission administration from the user form, and triggering password resets from the UI.

---

# 2. Ownership

## Owns

- user list view (paginated, searchable, status filter)
- enable / disable account actions
- the admin web session lifecycle (established at `/login`, destroyed at logout)
- the destination for successful platform-admin logins

## Does Not Own

- self-service registration (`/register`) — users never create their own accounts via the admin dashboard
- tenant-app pages (Consult, Cert)
- group and permission administration (see `group-permission-management.md`)
- the JWT API surface (`/api/v1/*`)

---

# 3. Access Model

The dashboard is **session-authenticated**, not JWT-authenticated.

- Route protection: the `web` middleware group plus an `auth` guard check plus a `web.admin` check.
- `web.admin` verifies the session-authenticated user belongs to the group named by `auth-web.admin_group` (default `loa-auth-admin`); otherwise it aborts with `403`.
- Membership is read from the database (`User::inGroup()`), never from token claims.
- The admin web session is established **only** in `WebAuthController::login` (see `web-ui.md` section 4.1). Non-admins never receive a web auth session.
- The dashboard calls services directly (server-side). It does **not** call the JWT API.

---

# 4. Routes

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/users` | list (search + status filter) | `admin.users` |
| `POST` | `/admin/users/{id}/status` | enable / disable a user | `admin.users.status` |
| `POST` | `/admin/users/{id}/sessions/invalidate` | revoke all web sessions for a user | `admin.users.sessions.invalidate` |
| `POST` | `/admin/logout` | destroy admin session | `admin.logout` |

All routes require `auth` (web guard) + `web.admin`. All `POST` forms include `@csrf`.

---

# 5. Controller & Views

## Controller

`App\Http\Controllers\WebAdminController`

| Method | Responsibility |
|--------|----------------|
| `index(Request $request)` | Paginate users, apply search/status filters, render list |
| `updateStatus(Request $request, string $id)` | Validate target status, enforce `users.manage`, call `IdentityService::setUserStatus()`, redirect back |
| `invalidateSessions(Request $request, string $id)` | Validate target user, enforce `users.manage`, call session invalidation, redirect with flash |
| `logout(Request $request)` | `Auth::guard('web')->logout()`, invalidate + regenerate session, redirect to `/login` |

## Views

| View | Purpose |
|------|---------|
| `resources/views/admin/users/index.blade.php` | User management list (extends a dedicated admin layout) |

The admin layout (`resources/views/layouts/admin.blade.php`) provides the admin chrome (top bar, logout form, page title) distinct from the public auth layout.

---

# 6. List Behavior

- Default ordering: newest first (`created_at` desc).
- Pagination: 25 rows per page.
- Query parameters:
  - `q` — substring match on `name` OR `email` (case-insensitive).
  - `status` — `active` | `disabled` | `locked` | `all` (default `all`).
- Columns: name, email, status badge, failed attempts, locked until, created at, actions.
- **Platform-admin rows** show `(Admin)` beside the user name in the User column and offer no status action (the backend refuses to deactivate platform administrators).
- Actions (per row): a `View` link plus one status action rendered as **link text** (`.button-link`), not solid buttons — `Activate` for pending/locked, `Disable` for active non-admin non-self rows, `Enable` for disabled rows; POST forms submit via a confirm-then-submit anchor.
- The list must never expose the password hash or password-reset tokens (`User::$hidden` covers this).
- If `q` or `status` is present, filter the query accordingly; keep filters on re-render (query-string links, not a stateful UI).

---

# 6a. Admin UI Conventions

Shared chrome and interaction patterns for all `/admin/*` pages (styles live in `layouts/admin.blade.php`):

## Breadcrumbs

- Partial: `resources/views/admin/partials/breadcrumbs.blade.php` (`@include('admin.partials.breadcrumbs', ['items' => [['label' => ..., 'url' => ?], ...]])`).
- Rendered on **every** admin page, including roots (single crumb: `Users`, `Tenants`, `Groups`); last item is the current page (non-link), earlier items link up the hierarchy (e.g., `Tenants › {tenant} › Groups › {group} › Members`).
- Pure-navigation "Back to …" buttons are **removed**; breadcrumbs replace them. Form `Cancel` buttons and import-wizard resets (`Start Over`, `Start New Import`) remain.

## Table Row Actions = Link Text, Not Buttons

- In-table actions use `.button-link` (plain text links; danger variant `.button-danger` for destructive ones) — never solid buttons inside tables.
- State-changing rows still POST via hidden `@csrf` forms; anchors submit with `onclick="... this.closest('form').submit()"` and a `confirm()` guard.
- Solid buttons are reserved for form submits outside tables (Save/Create/Add).

## Quick-Action Tiles

Detail pages surface sub-navigation as tiles (`.quick-actions` / `.action-tile`) inside their first detail card — accent icon + label + one-line description + chevron:

| Page | Tiles |
|------|-------|
| Tenant detail | Edit tenant · Manage groups · Manage endpoints · Import/Export Config |
| Tenant group detail | Endpoints & permissions · Members |
| User detail | Endpoint overrides |

## Button Variants

| Class | Use |
|-------|-----|
| `.button` (solid brand) | Primary form submits only (topbar Sign out uses ghost) |
| `.button-neutral` | Secondary/outline actions on redesigned pages |
| `.button-link` (+`.button-danger`) | Link-text table row actions |
| `.button-soft-danger` / `.button-soft-success` | Destructive/activating status toggles (e.g., Suspend/Activate tenant) |
| `.button-ghost` | Light-theme by default in content; white variant scoped to `.admin-topbar` |

---

# 7. Enable / Disable

- Invokes the existing `IdentityService::setUserStatus(string $userId, string $status)`.
- Enforced transitions per `kernels/identity/rules/account-status.md`:
  - `active` → `disabled`: account can no longer authenticate; **all refresh tokens revoked** (`kernels/identity/entities/refresh-token.md` rule 7).
  - `disabled` → `active`: re-enable (also clears lock state).
- Requires the `users.manage` permission (checked via `AuthorizationService::hasPermission($userId, 'users.manage')`) in addition to the `web.admin` group gate.
- **Self-disable is forbidden**: an admin cannot disable their own account (would strand the only admin session).
- On success: `302` back to the list with a flash message. On failure: `302` back with a generic error flash.
- The current row status determines the offered action (disable when active, enable when disabled; no status action offered for `locked` rows beyond what the status rules allow).

---

# 8. Tenant Administration (v2)

Per `kernels/identity/tenancy.md`, the admin dashboard is where platform admins manage tenants, tenant groups, and tenant-scoped endpoint permissions.

## Routes

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/tenants` | list tenants | `admin.tenants` |
| `GET` | `/admin/tenants/create` | create form | `admin.tenants.create` |
| `POST` | `/admin/tenants` | store tenant | `admin.tenants.store` |
| `GET` | `/admin/tenants/{tenant}` | tenant detail | `admin.tenants.show` |
| `POST` | `/admin/tenants/{tenant}/status` | suspend / activate | `admin.tenants.status` |
| `GET` | `/admin/tenants/{tenant}/groups` | tenant group list | `admin.tenants.groups` |
| `POST` | `/admin/tenants/{tenant}/groups` | create tenant group | `admin.tenants.groups.store` |
| `POST` | `/admin/tenants/{tenant}/groups/{group}/permissions` | grant / revoke endpoint permissions | `admin.tenants.groups.permissions` |
| `POST` | `/admin/tenants/{tenant}/members` | add / remove user membership | `admin.tenants.members` |

All routes require `auth` (web guard) + `web.admin`; all `POST` forms include `@csrf`.

## Behavior

- **Tenant CRUD**: create a tenant (slug, name, `app_url`, `redirect_origins`), suspend/activate. `slug` is immutable after issuance.
- **Groups**: per-tenant groups with `(tenant_id, name)` uniqueness; platform admins can also create platform-global groups (`tenant_id NULL`).
- **Permissions per endpoint per tenant**: the permission catalog is global; the dashboard grants/revokes a permission **to a group within a tenant**. `user_group_permission.tenant_id` is set to the tenant (or `NULL` for platform-wide grants).
- **Members**: add/remove users to a tenant (`user_tenants`). Grants only take effect for tenant members.
- **Suspending** a tenant blocks its login redirect and rejects its tenant-scoped tokens (per `tenancy.md` §9).
- Self-service for tenants (own admin creating their own groups) is **out of scope**; all tenant administration is performed by platform admins.

## Security Checklist (tenants)

- [x] Tenant CRUD + group/grant/membership changes require platform-admin (`web.admin`)
- [x] `slug` immutable after issuance
- [x] `redirect_origins` strict-origin validation
- [x] Suspension enforced at login and token validation
- [x] No cross-tenant grant leakage (scope always explicit)

---

# 9. Admin User Creation (v3)

**Only platform admins** (members of the `loa-auth-admin` group) can create user accounts directly from the dashboard. This is not a self-service registration — it is an admin-only action.

Platform admins create user accounts without requiring self-registration.

## Routes

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/users/create` | create user form | `admin.users.create` |
| `POST` | `/admin/users` | store new user | `admin.users.store` |

Both routes require `auth` (web guard) + `web.admin` + `users.manage` permission.

## Form

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `email` | email | yes | Unique across `users` table |
| `name` | text | yes | Display name |
| `password` | password | no | If blank, a random 16-char password is auto-generated |
| `status` | select | yes | `active` (default) or `disabled` |

## Behavior

- **Validation**: email unique, valid email format, name required, password (if provided) must meet policy (min 8 chars, uppercase, lowercase, number, special char — per `kernels/identity/rules/password-policy.md`).
- **Auto-generated password**: if password field is left blank, generate a 16-character random string (mixed case + digits + symbols). The generated password is displayed **once** in a success flash message after creation.
- **Creation**: calls `IdentityService::register($email, $password, $name)` then `IdentityService::setUserStatus($userId, $status)` if status is `disabled`.
- **Email notification**: after successful creation, sends a password reset link to the new user's email via `PasswordResetNotificationService::sendForgotPasswordLink()`. The email uses the existing `PasswordResetMail` template. This allows the user to set their own password on first login.
- **On success**: redirect to `GET /admin/users` with flash message. If password was auto-generated, the flash includes the plaintext password (displayed once, never stored). The email is sent in the background (queued or dispatched synchronously per mail config).
- **On failure**: redirect back with validation errors.

## Controller

Add to `WebAdminController`:

| Method | Responsibility |
|--------|----------------|
| `create()` | Render the create-user form |
| `store(Request $request)` | Validate, register user via `IdentityService`, set status, send reset link via `PasswordResetNotificationService`, redirect with flash |

## Views

| View | Purpose |
|------|---------|
| `resources/views/admin/users/create.blade.php` | Create user form (extends admin layout) |

The form uses the same admin layout as the user list. Success flash message with auto-generated password uses a dismissible alert component.

## Security Checklist (v3)

- [x] Requires `web.admin` group gate
- [x] Requires `users.manage` permission (defense in depth)
- [x] CSRF on all forms
- [x] Auto-generated password displayed once, never logged or stored in session
- [x] Password policy enforced on manually-entered passwords
- [x] Email uniqueness validated (unique rule on `users.email`)
- [x] No way to set initial groups/membership from this form (deferred to tenant admin)
- [x] Password reset link sent via existing `PasswordResetNotificationService` (no new email template needed)

---

# 10. Logout

- `POST /admin/logout` (CSRF-protected).
- `Auth::guard('web')->logout()`, `$request->session()->invalidate()`, `$request->session()->regenerateToken()`.
- Redirect to `GET /login`.
- Logout is available from the admin layout top bar; session expires naturally per `SESSION_LIFETIME` otherwise.

---

# 11. Session Invalidation

Addresses the security scenario: **platform admin credentials compromised**.

When a platform admin's credentials are compromised, the admin can reset their password via `/forgot-password`. However, this only revokes JWT refresh tokens — **web sessions are not revoked**. The attacker's web session remains valid until `SESSION_LIFETIME` expires (default 480 min).

## Routes

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `POST` | `/admin/users/{id}/sessions/invalidate` | revoke all web sessions for a user | `admin.users.sessions.invalidate` |

Requires `auth` (web guard) + `web.admin` + `users.manage` permission.

## Behavior

- Revokes **all web sessions** for the specified user by deleting their session data from the session store.
- Does **not** revoke JWT refresh tokens (already handled by `IdentityService::resetPassword()` and `IdentityService::setUserStatus()`).
- After invalidation, the user is forced to re-authenticate on their next request.
- **Self-invalidation is forbidden**: an admin cannot invalidate their own sessions (would strand the only admin session).
- On success: redirect back with flash message. On failure: redirect back with error flash.

## Controller

Add to `WebAdminController`:

| Method | Responsibility |
|--------|----------------|
| `invalidateSessions(Request $request, string $id)` | Validate target user, enforce `users.manage`, call session invalidation, redirect with flash |

## Implementation Notes

- Use `Session::handler()->destroy($sessionId)` to invalidate specific sessions.
- For simplicity, invalidate **all sessions** for a user (not selective).
- Consider adding a "Last active" column to show when sessions were last used.

## Compromised Credentials Response Playbook

```
1. Real admin notices unauthorized access
2. Real admin goes to /forgot-password
3. Real admin receives email with reset link
4. Real admin resets password at /reset-password
5. All JWT refresh tokens are revoked (IdentityService::resetPassword())
6. Real admin goes to /admin/users/{compromised-id}/sessions/invalidate
7. Attacker's web session is destroyed
8. Attacker locked out completely
```

---

# 12. Security Checklist

- [ ] All admin routes behind `auth` (web guard) + `web.admin` group check
- [ ] CSRF on every `POST` form (list actions, logout, session invalidation)
- [ ] Session ID regenerated on admin login and on logout (session-fixation prevention)
- [ ] Non-admins never receive a web auth session
- [ ] Self-disable forbidden
- [ ] Self-deletion forbidden
- [ ] Platform-admin deletion forbidden
- [ ] Self-session-invalidation forbidden
- [ ] `users.manage` enforced on status changes and session invalidation (defense in depth)
- [ ] Search/filter responses never leak password hashes or reset tokens
- [ ] Generic error messages (no enumeration, no account-state disclosure beyond the visible status badge)

---

# 13. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Dashboard calls the JWT API | Double auth model, redundant token handling | Server-side service calls with session auth |
| Any authenticated web user reaching `/admin/*` | Scope escalation | `web.admin` group gate on every admin route |
| Creating a web session for non-admins | Scope escalation | Only platform admins get a web session |
| Disabling your own admin account | Locks out the only dashboard session | Forbid self-disable |
| Deleting your own admin account | Locks out the only dashboard session | Forbid self-deletion |
| Deleting a platform admin | Removes privileged access without audit trail | Forbid admin deletion, require disable instead |
| Invalidating your own sessions | Locks out the only admin session | Forbid self-session-invalidation |
| Managing groups/permissions in v1 | Unnecessary scope | Deferred to `group-permission-management.md` (v4) |

---

# 14. Delete User (v5)

Permanently removes a user and **all** related records from the database. This is a hard delete, distinct from Disable (which preserves the account for reactivation).

## Route

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `POST` | `/admin/users/{id}/delete` | hard-delete user and related data | `admin.users.delete` |

Requires `auth` (web guard) + `web.admin` + `users.manage` permission.

## Behavior

- **Preconditions checked before delete:**
  1. Target user must exist (404 otherwise).
  2. Admin must have `users.manage` permission (via `AuthorizationService`).
  3. Admin **cannot delete themselves** — would strand the session.
  4. Platform-admin users **cannot be deleted** — refuse with error flash.
- **Cascade cleanup** — the database FKs handle most records automatically (`ON DELETE CASCADE`):
  - `user_user_group` (pivot) — CASCADE
  - `user_tenants` (pivot) — CASCADE
  - `user_permission` (pivot) — CASCADE
  - `password_reset_tokens` — CASCADE
  - `refresh_tokens` — CASCADE
  - `activations` — CASCADE
  - `user_claim_overrides` — CASCADE
  - `password_set_tokens` — CASCADE
  - `tenant_endpoint_overrides` — CASCADE
  - `login_attempts.user_id` — SET NULL (audit trail preserved)
  - `tenant_api_keys.created_by` — SET NULL
  - `sessions.user_id` — no FK; stale sessions are harmless (expire naturally)
- **Audit log entry:** `user.deleted` recorded with `actor_id`, `target_id`, `target_email`.
- **UI:** a `Delete` link-text (`.button-danger`) in the Actions column, guarded by `confirm('Permanently delete this user? This cannot be undone.')` before submitting the POST form.

## Controller

| Method | Responsibility |
|--------|----------------|
| `deleteUser(Request $request, string $id)` | Validate preconditions, audit-log, call `$user->delete()`, redirect with flash |

## Views

| View | Change |
|------|--------|
| `resources/views/admin/users/index.blade.php` | Add delete action link per row (after existing status actions) |

## Security Checklist

- [x] Requires `web.admin` group gate
- [x] Requires `users.manage` permission (defense in depth)
- [x] CSRF on all forms
- [x] Self-deletion forbidden
- [x] Platform-admin deletion forbidden
- [x] Hard delete cascades via DB FK (no orphan records)
- [x] Audit trail entry created before deletion

---

# 15. Implementation Inventory

| Item | Detail |
|------|--------|
| Controller | `WebAdminController` (`index`, `updateStatus`, `deleteUser`, `invalidateSessions`, `logout`, `tenantsIndex`, `tenantsCreate`, `tenantsStore`, `tenantsShow`, `tenantsStatus`, `tenantsGroups`, `tenantsGroupsStore`, `tenantsGroupsPermissions`, `tenantsMembersStore`, `create`, `store`) |
| Middleware | `web.admin` (new) — group check using `auth-web.admin_group` |
| Routes | `routes/web.php` — admin group, `auth` + `web.admin` |
| Views | `layouts/admin.blade.php`, `admin/partials/breadcrumbs.blade.php` (included by every admin page), plus all views under `resources/views/admin/{users,groups,tenants}/` |
| Config | `config/auth-web.php` → add `admin_group` (env `AUTH_ADMIN_GROUP`, default `loa-auth-admin`) |
| Services (existing) | `IdentityService::register()`, `IdentityService::setUserStatus()`, `AuthorizationService::hasPermission()`, `TenantService` |

---

# 15. Dependency References

| Spec | Role |
|------|------|
| `web-ui.md` | Establishes the admin session at login; destination decision |
| `kernels/identity/rules/account-status.md` | Status transitions on enable/disable |
| `kernels/identity/rules/password-policy.md` | Password validation for admin-created users |
| `kernels/identity/entities/refresh-token.md` | `revokeAllRefreshTokens` on disable |
| `kernels/identity/README.md` (IdentityService) | `register()`, `setUserStatus()` contracts |
| `kernels/identity/tenancy.md` | Tenant entity, scoped groups/grants, tenant administration (§8) |
