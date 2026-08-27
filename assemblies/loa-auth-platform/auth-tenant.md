# Auth Tenant + Member Management UX

**Version:** 1.0  
**Status:** Draft  
**Spec Owner:** Auth Platform  
**Last Updated:** 2026-08-27

---

## 1. Purpose

Register the auth app itself as a tenant, and unify the member-management UX across all tenant and group pages with a search-first "Add Member" pattern and a "Create User" flow available from any tenant page.

---

## 2. Background

Currently the auth app's admin users have no tenant membership. The admin group (`loa-auth-admin`) is platform-level (`tenant_id = null`). There is no navigation path to `/admin/groups` from the topbar. Adding members to tenants or groups requires navigating to a specific page and using a static dropdown.

This spec:
1. Makes the auth app a tenant (consistent with how cert/consult work)
2. Unifies the "add member" UX across all surfaces
3. Adds a "Create User" flow available from any tenant page
4. Adds a platform-groups shortcut on the admin dashboard

---

## 3. Auth Tenant

### 3.1 Registration

| Field | Value |
|-------|-------|
| slug | `auth` |
| name | `LOA Auth Platform` |
| status | `active` |
| app_url | Auth app's own URL (e.g., `https://auth.lyceumalabang.edu.ph`) |
| redirect_origins | **Empty** — must NOT include the auth app's own origin (prevents SSO redirect loop) |
| dev_redirect_origins | **Empty** |

The auth tenant is created via a seeder (non-production) and SQL script (production) — consistent with the existing `LocalCertReadinessSeeder` pattern. No special code path; uses `TenantService::createTenant()`.

### 3.2 Read-Only Constraints

The auth tenant page (`/admin/tenants/{auth-tenant-id}`) is **read-only** except for member management:

| Action | Allowed on auth tenant? |
|--------|------------------------|
| View tenant info | ✅ Yes |
| Edit name/app_url/redirect_origins | ❌ No — edit button hidden |
| Suspend/activate | ❌ No — status toggle hidden |
| Manage groups | ✅ Yes |
| Manage members | ✅ Yes |
| Access config import/export | ✅ Yes |
| Endpoint catalog | ✅ Yes |

Implementation: check `$tenant->slug === 'auth'` (or a `is_platform` boolean column) to conditionally hide edit/status controls.

### 3.3 Tenant List Badge

In `/admin/tenants`, the auth tenant row shows a **"Platform"** badge next to its name. The badge is informational only — the row is still clickable and leads to the tenant detail page.

### 3.4 JWT Claims

When a user belongs to the auth tenant, their JWT includes:
```json
{
  "tenant": {
    "id": "<uuid>",
    "slug": "auth"
  }
}
```

This is handled by the existing `IdentityService::generateTokenPair()` — no changes needed. The tenant claim is included whenever a `Tenant` object is passed.

### 3.5 Relationship to Platform Admin Group

| Concept | Ownership | Purpose |
|---------|-----------|---------|
| `loa-auth-admin` group | Platform-level (`tenant_id = null`) | Controls `/admin/*` access |
| Auth tenant | Tenant row (slug `auth`) | Container for users who can log in; JWT `tenant` claim target |

A user can be:
- **Platform admin only**: member of `loa-auth-admin`, not in auth tenant (cannot log in to consumer apps)
- **Auth tenant member only**: in auth tenant, not in `loa-auth-admin` (can log in, no admin access)
- **Both**: member of `loa-auth-admin` AND in auth tenant (can log in + admin access)

The `loa-auth-admin` group remains `tenant_id = null` throughout. No changes to `WebAdminMiddleware`, `PermissionPolicyService::isPlatformAdmin()`, or deactivation guards.

---

## 4. Search-First "Add Member" Pattern

### 4.1 Surfaces

This pattern applies to **all** member-add surfaces:

| Surface | URL | Current UX |
|---------|-----|------------|
| Tenant show page | `/admin/tenants/{tenant}` | Static dropdown of non-members |
| Tenant group members | `/admin/tenants/{tenant}/groups/{group}/members` | Search input (already implemented) |
| Platform group members | `/admin/groups/{group}` | Static dropdown of non-members |

### 4.2 UX Flow

1. Page loads — member list is visible, **no** add-member form
2. User clicks **"Add Member"** button
3. A search input appears (replaces the static dropdown)
4. User types ≥ 2 characters → debounced search (300ms) → results appear below input
5. Results show: name, email, status badge, checkbox
6. User clicks a result → user is selected (shown as a chip/tag below the search input, checkbox checked)
7. **Multi-select**: user can search again and select additional users (up to 20 per search)
8. Selected users accumulate as chips below the search input
9. **"Add N members"** button shows count → user clicks → all selected members are added in one batch
10. Search input clears, member list refreshes, chips cleared

### 4.3 Search Behavior

The search follows the **two-tier** model already implemented on tenant group member pages:

- **Primary tier**: users who are members of the current tenant but NOT in the current group (for group pages) / users who are NOT in the tenant (for tenant show pages)
- **Secondary tier**: users who are NOT in the tenant at all (shown when primary results are empty)
- Tier is determined server-side and returned in the JSON response
- The controller decides whether to call `addToGroup()` (primary) or `addToGroupTransactional()` (secondary, creates tenant pivot first)
- **Multi-select**: each selected user carries its own tier. When confirming, the controller processes each user according to its tier.

### 4.4 Renaming

| Old | New |
|-----|-----|
| "Add to tenant" | "Add Member" |
| "Select a user…" | "Search by name or email…" |
| "Add to group" | "Add Member" |

---

## 5. CSV Import

### 5.1 Availability

A **"Import CSV"** button appears on **every tenant show page** (`/admin/tenants/{tenant}`), next to the "Create User" button.

### 5.2 CSV Format

```csv
name,email,groups
"nin alamo","alamoninofrancisco@gmail.com","cert-admin,cert-staff"
"juan dela cruz","juan@example.com","cert-user"
"maria santos","maria@example.com",""
```

| Column | Required | Notes |
|--------|----------|-------|
| `name` | ✅ Yes | User's display name |
| `email` | ✅ Yes | Must be a valid email; used for dedup |
| `groups` | ❌ No | Comma-separated group names within the current tenant; empty = add to tenant only (no group) |

### 5.3 UX Flow

1. User clicks **"Import CSV"** on tenant page
2. A file picker appears (accepts `.csv` only)
3. User selects file → system parses and validates
4. **Preview table** shows (editable):
   - Row number
   - Name (editable text field)
   - Email (editable text field)
   - Groups (editable — multi-select dropdown showing ONLY groups belonging to the current tenant)
   - Status: `✓ Ready` / `⚠ Already in tenant` / `✗ Invalid` (with reason)
5. User can **edit any cell** directly in the preview table (fix typos, correct group names, remove invalid rows)
6. User can **delete rows** from the preview (checkbox + "Remove selected" button)
7. User reviews → clicks **"Import N members"**
8. System processes each row (same rules as 5.4)
9. **Results summary** shows:
   - Created: N users
   - Updated: N users (added to new groups)
   - Skipped: N rows (with reasons)
   - Errors: N rows (with reasons)

### 5.4 Group Resolution

Groups in the CSV are resolved **strictly within the current tenant**:

| Scenario | Action |
|----------|--------|
| Group name matches a tenant group | ✅ Add user to that group |
| Group name does NOT exist in this tenant | ❌ Error on that group — "Group 'cert-admin' not found in tenant 'loa-e-cert'" |
| Group name exists in a DIFFERENT tenant | ❌ Same error — groups are tenant-scoped, cross-tenant reference is invalid |
| Empty groups field | ✅ User added to tenant only (no group assignment) |
| Multiple groups (comma-separated) | ✅ Each resolved independently within the tenant |

The groups dropdown in the preview table is populated from `Tenant::find($tenantId)->userGroups()` — only tenant-scoped groups appear. No cross-tenant group lookup is possible.

### 5.5 Processing Rules

| Scenario | Action |
|----------|--------|
| Email not in system | Create user (status: `pending`) + attach to tenant + add to listed groups + send set-password email |
| Email exists, already in this tenant | Skip user creation; add to any NEW groups not already in |
| Email exists, NOT in this tenant | Attach to tenant + add to listed groups + send set-password email (no user creation) |
| Group name doesn't exist in this tenant | ❌ Row marked invalid in preview; must fix or remove before import |
| Invalid email format | ❌ Row marked invalid in preview; must fix or remove before import |
| Missing name | ❌ Row marked invalid in preview; must fix or remove before import |
| Duplicate email in same CSV | Process first occurrence; skip duplicates |
| Group from different tenant | ❌ Treated as "not found" — same error as non-existent group |

### 5.6 Server-Side

Controller methods (reuse existing `TenantMemberImportController` infrastructure):

**Preview step** (`POST /admin/tenants/{tenant}/members/import/preview`):
1. Parse CSV (max 500 rows)
2. Validate headers: must contain `name`, `email`; `groups` optional
3. For each row: validate fields, resolve groups against current tenant only
4. Return editable preview data (JSON or Blade with editable fields)
5. Store preview in session for the process step

**Edit step** (`POST /admin/tenants/{tenant}/members/import/edit`):
1. Accept edited rows from preview form
2. Re-validate (same rules)
3. Return updated preview

**Process step** (`POST /admin/tenants/{tenant}/members/import/process`):
1. Load edited preview from session
2. Process each row in a DB transaction:
   - Create/attach user, add groups, send email
3. Return results summary
4. Audit log: `tenant.members_imported` with counts

### 5.6 Existing Import Infrastructure

The project already has `TenantMemberImportController` with routes:
- `GET /admin/tenants/{tenant}/members/import` — show form
- `POST /admin/tenants/{tenant}/members/import/preview` — preview
- `POST /admin/tenants/{tenant}/members/import/process` — process
- `GET /admin/tenants/{tenant}/members/import/failed` — download failed rows
- `POST /admin/tenants/{tenant}/members/import/discard` — discard

This spec **reuses** the existing import infrastructure but **adds group assignment** to the CSV format and processing logic.

---

## 6. Create User Flow

### 5.1 Availability

A **"Create User"** button appears on **every tenant show page** (`/admin/tenants/{tenant}`).

### 5.2 UX Flow

1. User clicks **"Create User"** on tenant page
2. A modal or inline form appears with fields:
   - Name (required)
   - Email (required)
3. User fills form → clicks **"Create & Invite"**
4. System creates the user (status: `pending`) AND adds them to the current tenant in one transaction
5. System sends an email with a **set-password link** (unique, signed, single-use)
6. User clicks link → lands on a set-password page → creates their own password → account becomes `active`
7. Tenant member list refreshes, new user appears with `pending` status

### 5.3 Set-Password Email

The email is distinct from forgot-password and reset-password flows:

| Flow | Trigger | Token purpose | Post-action status |
|------|---------|---------------|-------------------|
| Forgot password | User clicks "Forgot password" | Reset existing password | Stays `active` |
| Reset password | Admin resets via user detail | Reset existing password | Stays `active` |
| **Set password (new)** | Admin creates user via tenant page | Set password for first time | `pending` → `active` |

The set-password link uses a dedicated route (`/set-password?token=...`) and a dedicated token table (`password_set_tokens`) — separate from `password_reset_tokens`.

### 5.4 Server-Side

Controller method: `tenantsCreateUser(Request $request, Tenant $tenant)`

1. Validate input (name, email unique)
2. Create user via `User::create()` with status `pending`, random hashed password placeholder
3. Attach to tenant via `$tenant->users()->attach($user->id)`
4. Generate set-password token (signed, expires 48h)
5. Send set-password email via `Mail::to($email)`
6. Audit log: `user.created` + `tenant.member_added`
7. Redirect back with success flash

### 5.5 Relationship to Global "Create User"

The existing `/admin/users/create` page (`WebAdminController::create`) creates a user WITHOUT assigning them to a tenant and WITHOUT sending a set-password email. The new "Create User" on tenant pages creates a user, adds them to the tenant, and emails a set-password link. Both flows coexist:

| Button | Location | Creates user | Adds to tenant | Emails set-password |
|--------|----------|-------------|----------------|-------------------|
| "Create User" (global) | `/admin/users/create` | ✅ | ❌ | ❌ |
| "Create User" (tenant) | `/admin/tenants/{tenant}` | ✅ | ✅ (current tenant) | ✅ |

---

## 7. Dashboard Shortcut

### 6.1 Visibility

A **"Platform Groups"** shortcut appears on the admin dashboard (`/`) **only for platform admins** (users in `loa-auth-admin`).

### 6.2 Placement

In the **quick-actions** section of the admin zone partial (`admin-zone.blade.php`), alongside the existing buttons.

### 6.3 Link

`/admin/groups` — the platform groups index page.

### 6.4 Label

**"Platform Groups"** with a group/teams icon.

---

## 8. Navigation Summary

After this spec, the navigation flow is:

| To manage… | Path |
|------------|------|
| Platform admins | Dashboard → **Platform Groups** shortcut → `loa-auth-admin` → Add/Remove members |
| Tenant users | Topbar → **Tenants** → click tenant → member list + Add Member / Create User |
| Tenant groups | Tenant page → **Groups** tab → click group → members |
| Platform groups | Dashboard → **Platform Groups** shortcut → click group → members |
| Audit log | Topbar → **Audit log** |

No new topbar links. The "Groups" concept is accessed via dashboard shortcut (platform) or tenant page (tenant-scoped).

---

## 9. Implementation Inventory

### New Files
| File | Purpose |
|------|---------|
| `database/seeders/LocalAuthTenantSeeder.php` | Seed auth tenant (non-production, like `LocalCertReadinessSeeder`) |
| `app/Mail/SetPasswordMail.php` | Set-password email mailable |
| `resources/views/emails/set-password.blade.php` | Set-password email template |
| `app/Http/Controllers/SetPasswordController.php` | Set-password page + handler |
| `resources/views/auth/set-password.blade.php` | Set-password form page |
| `database/migrations/xxxx_create_password_set_tokens_table.php` | Token table for set-password flow |
| `app/Models/PasswordSetToken.php` | Token model |

### Modified Files
| File | Change |
|------|--------|
| `WebAdminController.php` | `tenantsShow()` hide edit/status for auth tenant; `tenantsCreateUser()` method; batch add member endpoint |
| `TenantMemberImportController.php` | Add group assignment to CSV import logic |
| `Tenant.php` | Add `isPlatform()` helper (check slug) |
| `admin/tenants/show.blade.php` | Hide edit/status for auth tenant; "Create User" button; search-first multi-select Add Member; "Import CSV" button |
| `admin/tenants/index.blade.php` | "Platform" badge on auth tenant row |
| `admin/tenants/import.blade.php` | Editable preview table with group column, multi-select dropdown scoped to tenant groups, row editing/deletion |
| `admin/groups/show.blade.php` | Search-first multi-select Add Member (replace static dropdown) |
| `admin/tenants/group-members.blade.php` | Multi-select support (already search-first) |
| `admin/partials/admin-zone.blade.php` | "Platform Groups" quick-action button |
| `routes/web.php` | New routes for `tenantsCreateUser`, batch add, `setPassword` |
| `DatabaseSeeder.php` | Call `LocalAuthTenantSeeder` in non-production |

### Unchanged
| File | Reason |
|------|--------|
| `AuthorizationService.php` | I1/M8 guards already work |
| `WebAdminMiddleware` | Still checks `loa-auth-admin` (platform-level) |
| `PermissionPolicyService` | `isPlatformAdmin()` unchanged |
| `IdentityService` | JWT tenant claim already works when Tenant is passed |

---

## 10. Open Questions

| ID | Question | Resolution |
|----|----------|------------|
| Q1 | Should the auth tenant be seeder-created or migration-created? | Seeder (non-production) + SQL script (production) — consistent with existing `LocalCertReadinessSeeder` pattern |
| Q2 | Should "Create User" send a set-password email? | ✅ Yes — dedicated set-password flow, not forgot/reset password |
| Q3 | What if someone tries to suspend the auth tenant via API? | Reject with 422 (same as UI read-only constraint) |
| Q4 | Should the search-first pattern support multi-select? | ✅ Yes — search, select multiple users, batch add |
| Q5 | Should the platform groups shortcut be visible to non-admin users? | No — gated by `$isAdmin` in the admin zone partial |
| Q6 | Set-password token expiry? | 48 hours |
| Q7 | Can the set-password link be reused? | No — single-use, deleted after successful set |
| Q8 | What happens if admin creates a user with an existing email? | Validation rejects — email must be unique |
| Q9 | CSV max rows? | 500 rows per import |
| Q10 | Should CSV import use the existing `TenantMemberImportController` routes? | ✅ Yes — reuse existing infrastructure, extend with group assignment |
| Q11 | What if a CSV references a group that exists in a different tenant? | ❌ Treated as "not found" — groups are strictly tenant-scoped |

---

## 11. Testing

| Test | Surface | Assertion |
|------|---------|-----------|
| Auth tenant seeded | Seeder | `Tenant::where('slug', 'auth')->exists()` |
| Auth tenant read-only | Web | Edit button hidden, status toggle hidden |
| Auth tenant badge | Web | "Platform" badge visible in tenant list |
| Create User (tenant) | Web | User created (status: pending), tenant pivot exists, set-password email sent |
| Create User (global) | Web | User created, no tenant pivot, no email |
| Set password flow | Web | Token valid → set password → status becomes active; token deleted after use |
| Set password expiry | Web | Expired token → redirect with error |
| Search-first add (tenant) | Web | Static dropdown removed, search input present, multi-select works |
| Search-first add (group) | Web | Static dropdown removed, search input present, multi-select works |
| Batch add members | Web | Multiple users added in one request, each processed by tier |
| Platform groups shortcut | Web | Visible to admin, hidden from non-admin |
| Redirect loop prevention | Config | Auth tenant `redirect_origins` does not include own origin |
| CSV preview | Web | Preview table shows all rows with group-resolution status |
| CSV editable preview | Web | Cells are editable, rows deletable |
| CSV tenant-scoped groups | Web | Group dropdown shows only current tenant's groups |
| CSV invalid group rejected | Web | Group not in tenant → row marked invalid, blocked from import |
| CSV new user import | Web | User created + tenant pivot + groups + set-password email |
| CSV existing user re-import | Web | User not duplicated, added to new groups only |
| CSV empty groups | Web | User added to tenant only, no group assignment |
| CSV duplicate email | Web | First occurrence processed, duplicates skipped |

---

## 12. Doc Control

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0 Final | 2026-08-27 | AI | Initial draft — auth tenant, search-first, CSV import, create user, set-password flow |
