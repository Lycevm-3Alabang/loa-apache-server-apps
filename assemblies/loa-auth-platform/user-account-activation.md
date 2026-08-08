# LOA Auth Platform — User Account Activation
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Replaces self-registration with a backend-provisioned activation flow.

It answers:

> **"How does a newly provisioned user activate their account and set their password?"**

Users do not register themselves. Instead, a tenant application or platform admin provisions the user (creating the user record, tenant membership, and role), then the user activates their account via a secure email link.

**Current state:** Users are created via the admin dashboard or API with `status: active` and receive a password-reset email. Self-registration exists at `/register`.

**Target state:** Users are created with `status: pending`. An activation email is sent. The user clicks the link, sets their password, and activates their account. Self-registration is removed.

---

# 2. Ownership

## Owns

- The activation token lifecycle (generation, validation, expiration, single-use enforcement)
- The activation email (content and delivery)
- The activation page (repurposed from `/register` to `/activate`)
- The `pending` user status and its login-gating behavior
- The admin "resend activation" action

## Does Not Own

- User creation (owned by `admin-dashboard.md` §3.6 — the admin create-user flow)
- Tenant membership management (owned by `admin-dashboard.md` §3.7)
- Group/permission assignment (owned by `group-permission-management.md`)
- Password reset flow (separate feature)
- The JWT API surface (`/api/v1/*`)

---

# 3. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `admin-dashboard.md` §3.6 | Admin creates user — this spec changes the create flow to use `status: pending` and send activation email instead of password-reset email |
| `admin-dashboard.md` §3.7 | Tenant membership — unchanged; membership is created alongside the user |
| `group-permission-management.md` | Group assignment — unchanged; groups are assigned by admin after user creation |
| `web-ui.md` | Login flow — this spec adds a `pending` status check that blocks login |
| `environment.md` | `APP_URL` env var is used to build the activation link |

---

# 4. Model

## 4.1 User Status

The `users.status` enum gains a new value:

| Status | Meaning | Can log in? |
|--------|---------|-------------|
| `pending` | User provisioned, awaiting activation | No |
| `active` | Normal operational state | Yes |
| `disabled` | Manually disabled by admin | No |
| `locked` | Auto-locked after failed attempts | No (temporary) |

**Migration:** Add `pending` to the `status` enum column.

## 4.2 Activation Record

New table: `activations`

| Column | Type | Notes |
|--------|------|-------|
| `id` | uuid (PK) | Auto-generated |
| `user_id` | uuid (FK → users.id) | Cascade on delete |
| `token` | string | SHA-256 hash of the raw token |
| `expires_at` | timestamp | 24 hours from creation |
| `activated_at` | nullable timestamp | Set on successful activation |
| `created_at` / `updated_at` | timestamps | Standard Laravel |

**Indexes:** `token` column indexed for lookup.

**Token format:** `bin2hex(random_bytes(32))` — 64-character hex string (256 bits of randomness). Matches the existing `password_reset_tokens` pattern.

**Security properties:**
- Single-use (`activated_at` is set after use)
- Time-limited (24-hour expiry)
- Stored as SHA-256 hash (raw token never persisted)
- Cryptographically random

## 4.3 Activation Flow

```
Backend / Tenant App
      |
      | 1. Create user (status: pending)
      | 2. Add to tenant
      | 3. Assign role/group
      |
      v
Activation Email
      |  Contains: {APP_URL}/activate?token=<raw-token>
      |
      v
User clicks link
      |
      v
GET /activate?token=...
      |  Validate token (exists, not expired, not used)
      |  Display activation form: email (readonly), password, confirm password
      |
      v
POST /activate
      |  Validate password
      |  Set user status → active
      |  Mark activation as used
      |  Redirect to /login with success message
      |
      v
User can log in
```

## 4.4 Token Resolution

The activation token resolves to a user through the following chain:

```
raw token
    ↓  hash with SHA-256
activations.token
    ↓  lookup
activations.user_id
    ↓  lookup
users.status → set to 'active'
```

The tenant and role are **not** derived from the token. They already exist in the database from the provisioning step. The token only identifies the pending activation.

## 4.5 Existing Users

If a user already has an active LOA Auth account and is provisioned to a new tenant:

1. The user record is **not** recreated (email is unique)
2. The tenant membership is added silently
3. No activation email is sent (user can already log in)
4. The user can immediately access the new tenant

## 4.6 Existing Memberships

If the user already has an active membership for the tenant:

1. `TenantService::addUserToTenant()` is idempotent (`syncWithoutDetaching`)
2. No duplicate membership is created
3. No error is raised

## 4.7 Multi-Tenant Activation

If a user is provisioned for multiple tenants before activating:

1. Only **one** activation record exists per user (newest replaces oldest)
2. One activation email covers all pending tenants
3. Activation activates the user account, which enables access to all tenant memberships

## 4.8 Resend Activation

If the user loses the activation email:

1. Admin navigates to user detail page
2. Admin clicks "Resend activation email"
3. A new activation token is generated (replacing the old one)
4. A new activation email is sent
5. The old token is invalidated

---

# 5. Routes

## 5.1 Web Routes (Modified)

| Method | Path | Name | Auth | Description |
|--------|------|------|------|-------------|
| GET | `/activate` | `activate` | None | Show activation form (token in query string) |
| POST | `/activate` | — | None | Process activation (throttled: 5/60min) |

## 5.2 Web Routes (Removed)

| Method | Path | Name | Reason |
|--------|------|------|--------|
| GET | `/register` | `register` | Self-registration removed |
| POST | `/register` | — | Self-registration removed |

## 5.3 API Routes (Removed)

| Method | Path | Name | Reason |
|--------|------|------|--------|
| POST | `/api/v1/auth/register` | — | Self-registration removed |

## 5.4 Admin Routes (Added)

| Method | Path | Name | Auth | Description |
|--------|------|------|------|-------------|
| POST | `/admin/users/{id}/resend-activation` | `admin.users.resend-activation` | `auth:web`, `web.admin` | Resend activation email |

---

# 6. Views

## 6.1 Activation Page

**File:** `resources/views/activate.blade.php` (repurposed from `register.blade.php`)

Layout: `layouts.auth`

Content:
- **Email field** — readonly, pre-filled from token resolution, not editable
- **Password field** — user sets their password (min 8 chars, uppercase, lowercase, number)
- **Confirm password field** — must match
- **Hidden token field** — the raw activation token
- **Submit button** — "Activate account"

On success: redirect to `/login` with flash message "Account activated. Please sign in."
On error: redirect back with validation errors.

## 6.2 Deleted Views

| File | Reason |
|------|--------|
| `resources/views/register.blade.php` | Replaced by `activate.blade.php` |

## 6.3 Email Template

**File:** `resources/views/emails/activate-account.blade.php`

Minimal HTML template (matching `reset-password.blade.php` style):
- Subject: "Activate your LOA Platform account"
- Heading: "Activate your account"
- Body: "You have been invited to join the LOA Platform. Click the link below to set your password and activate your account."
- Activation link
- Expiry notice: "This link expires in 24 hours and can only be used once."
- Fallback: "If you did not expect this email, you can safely ignore it."

---

# 7. Services

## 7.1 ActivationService

**File:** `app/Services/ActivationService.php`

### Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `createActivation` | `(User $user): string` | Generate token, store hash, return raw token. Deletes any existing activation for this user first. |
| `activate` | `(string $rawToken): User` | Validate token (exists, not expired, not used), set user status to `active`, mark activation as used. Return the activated user. |
| `resendActivation` | `(User $user): string` | Generate new activation token (replaces old), return raw token. |

### Dependencies

- `IdentityService` — for `setUserStatus()` or direct status update
- `Activation` model
- `User` model

## 7.2 IdentityService Changes

**File:** `app/Services/IdentityService.php`

### login() — Add pending check

Before the existing `isLocked()` and `disabled` checks, add:

```php
if ($user && $user->status === 'pending') {
    throw new \Exception('Account not activated');
}
```

### register() — No changes

`register()` continues to create users with `status: active`. The admin controller will override to `pending` after creation, keeping the service signature stable.

---

# 8. Controllers

## 8.1 WebAuthController

**File:** `app/Http/Controllers/WebAuthController.php`

### Remove

- `showRegister()` method
- `register()` method

### Add

#### `showActivate(Request $request): View|RedirectResponse`

1. Extract `token` from query string
2. If missing, redirect to `/login`
3. Look up activation by hashed token
4. If not found or expired, redirect to `/login` with error
5. Load the user from `activation.user_id`
6. Return `activate` view with `email` and `token`

#### `activate(Request $request): RedirectResponse`

1. Validate: `token` (required), `password` (required, min 8, uppercase, lowercase, number), `password_confirmation` (required)
2. Call `ActivationService::activate($token)`
3. On success: redirect to `/login` with "Account activated. Please sign in."
4. On failure: redirect back with error

## 8.2 WebAdminController

**File:** `app/Http/Controllers/WebAdminController.php`

### store() — Modified

Current behavior:
1. Create user via `IdentityService::register()`
2. Optionally set disabled
3. Send forgot-password email

New behavior:
1. Create user via `IdentityService::register()`
2. Override status to `pending`
3. Generate activation token via `ActivationService::createActivation()`
4. Send activation email via `Mail::to()`

### Add

#### `resendActivation(Request $request, string $id): RedirectResponse`

1. Verify admin has `users.manage` permission
2. Look up user by ID
3. If user status is not `pending`, redirect with error
4. Call `ActivationService::resendActivation($user)`
5. Send activation email
6. Redirect with success message

## 8.3 AuthController

**File:** `app/Http/Controllers/AuthController.php`

### Remove

- `register()` method and its OpenAPI annotation

### login() — Modified

Add handler for the new `'Account not activated'` exception:

```php
if ($e->getMessage() === 'Account not activated') {
    return response()->json([
        'message' => 'Account not activated. Please check your email.'
    ], 403);
}
```

---

# 9. Migrations

## 9.1 Add pending status

**File:** `database/migrations/2026_08_07_000001_add_pending_status_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('status', ['pending', 'active', 'disabled', 'locked'])
        ->default('active')
        ->change();
});
```

## 9.2 Create activations table

**File:** `database/migrations/2026_08_07_000002_create_activations_table.php`

```php
Schema::create('activations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
    $table->string('token');
    $table->timestamp('expires_at');
    $table->timestamp('activated_at')->nullable();
    $table->timestamps();

    $table->index('token');
});
```

---

# 10. Admin Dashboard Changes

## 10.1 Create User Form

**File:** `resources/views/admin/users/create.blade.php`

Changes:
- **Remove** the `status` dropdown (always `pending` for new users)
- **Remove** the `password` field (user sets password during activation)
- Keep `email` and `name` fields
- Update button text: "Create user and send activation email"

## 10.2 User Detail Page

**File:** `resources/views/admin/users/show.blade.php`

Add:
- If user status is `pending`, show a "Resend activation email" button
- The button POSTs to `/admin/users/{id}/resend-activation`

---

# 11. Tests

## 11.1 New Tests

| Test File | Test Cases |
|-----------|------------|
| `tests/Feature/Activation/ActivationTest.php` | Token generation, activation success, expired token, used token, invalid token, password validation |

## 11.2 Modified Tests

| Test File | Changes |
|-----------|---------|
| `tests/Feature/Auth/LoginTest.php` | Add test: pending user cannot login (403 "Account not activated") |
| `tests/Feature/Auth/RegisterTest.php` | Remove or update — registration endpoint is deleted |
| `tests/Feature/Admin/UserManagementTest.php` | Update: new users have status `pending`, activation email is sent |

---

# 12. Environment Variables

No new environment variables required. Uses existing:
- `APP_URL` — used to build the activation link in emails
- `MAIL_MAILER`, `MAIL_HOST`, etc. — used for email delivery

---

# 13. Scope Limitations

This spec does **not** cover:
- Editing user details after creation
- Bulk user provisioning
- CSV/Excel import of users
- Passwordless authentication
- Social login (OAuth)
- Multi-factor authentication
- Tenant app integration (how tenant apps call the provisioning API)

---

# 14. Open Questions

None at this time. Spec is ready for review.

---

# 15. Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 Draft | 2026-08-07 | AI Agent | Initial draft |
