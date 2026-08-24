# LOA Auth Platform — Web UI
## Product Assembly Component Specification

**Version:** 1.3
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Browser-based authentication UI for the LOA platform ecosystem:

- login page (email + password) with destination resolution
- registration page (name, email, password)
- post-login destination: admin session dashboard or tenant-app token redirect
- forgot password page (email link to change-password form)
- change password page (email-confirmed, token-validated)
- admin user-management dashboard (see `admin-dashboard.md`)

The Auth Platform remains the single source of truth for identity. This spec adds a web surface on top of the existing stateless JWT API.

---

# 2. Scope

## Owns

- login web form
- registration web form (name, email, password)
- destination resolution after successful login (admin session / tenant redirect / reject)
- admin web session lifecycle (establish on admin login, destroy on logout)
- forgot password web form
- email-notification triggers for reset/change links
- change-password form (one-time token validation)
- CSRF protection on all web forms

## Does Not Own

- user dashboard / profile pages
- application-specific pages (Consult, Cert)
- any business workflow outside identity

---

# 3. Architecture Note: Hybrid Surface

The Auth Platform keeps its stateless JWT API (`/api/v1/*`) for machine consumers (Consult, Cert).

The web UI (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/admin/*`) runs on Laravel web routes:

- for non-admin visitors, the web session exists **only** to carry the CSRF token (Laravel default `web` middleware)
- for platform admins, the web session **also** carries the authenticated admin identity (web guard), so the user-management dashboard is server-rendered
- tenant apps authenticate with the JWT pair delivered in the URL fragment; no cross-app session sharing
- non-admin visitors never receive a web authentication session

---

# 4. Flows

## 4.1 Admin Login

### Routes

```
GET  /login
POST /login
```

### Steps

1. User visits `GET /login`.
2. User submits email + password.
3. System validates via `IdentityService::login()` (respects account lockout and brute-force rules).
4. On success: check if user is a platform admin (belongs to `loa-auth-admin` group).
5. If admin: establish web session → `302` to `/admin/users`.
6. If not admin: reject with generic "Access denied" error (no redirect, no SSO).
7. On failure: re-render the form with a generic "Invalid credentials" error.

**Platform admin** = the authenticated user belongs to the group named by `auth-web.admin_group` (default `loa-auth-admin`). Membership is read from the database (`User::inGroup()`), never from token claims.

**This route is admin-only.** Non-admin users attempting to log in here receive a generic error. They should use `/sso/login` instead.

---

## 4.2 SSO Login (Tenant Apps)

### Routes

```
GET  /sso/login?redirect=<app-url>
POST /sso/login
```

### Steps

1. User visits `GET /sso/login`.
2. User submits email + password.
3. System validates via `IdentityService::login()` (respects account lockout and brute-force rules).
4. On success: check if user is a platform admin.
5. If admin: reject with generic error (admins should use `/login`).
6. If not admin: resolve tenant from `?redirect=` origin, verify tenant membership.
7. If tenant member: encrypt tokens → redirect to splash page → tenant app.
8. If not tenant member: reject with generic error.
9. On failure: re-render the form with a generic "Invalid credentials" error.

**Tenant context** = an explicit `?redirect=` query parameter whose origin matches an **active tenant's** `redirect_origins` (resolved via `TenantService::resolveTenantByRedirectOrigin`). There is **no implicit tenant fallback**: absent or invalid `redirect` means direct access, so logins are rejected.

### Redirect Target Resolution

| Source | Rule |
|--------|------|
| `?redirect=` query param | Origin must match a tenant's `redirect_origins` → tenant context |
| No param / invalid param | Reject — "Access denied" |

An unlisted host is **never** followed. Open redirects are a security violation.

### UI

Minimal login form: email + password + submit. No registration link (use `/sso/register`). No admin branding. Title: "Sign in to LOA Platform".

### Redirect Splash Screen

After successful login with a valid tenant redirect, the user sees an intermediate **redirect splash page** before being sent to the target app.

- Route: `GET /redirect` (session-authenticated, ephemeral)
- Displays: "Redirecting to **{app_url}**..." with the tenant's `app_url` or the resolved redirect origin
- Auto-redirects via JavaScript after 2 seconds, or provides a manual "Click here if not redirected" link
- Purpose: prevents token leakage in referrer headers, gives the user visibility into where they're going, and provides a fallback if JavaScript is disabled
- The splash page is server-rendered, uses the public auth layout (not admin layout)
- After redirect, the session is invalidated (one-time use)

### Token Delivery

```
{targetUrl}#payload={encrypted_base64url}
```

- The entire fragment is a single encrypted payload, not raw query params.
- Payload is JSON containing tokens + metadata, encrypted with AES-256-GCM.
- Encrypted payload is base64url-encoded (no padding) for URL safety.
- The target app decrypts the payload with the shared secret to extract tokens and metadata.

### Encrypted Payload Structure

**Plaintext JSON (before encryption):**

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "expires_in": 900,
  "user": {
    "id": "...",
    "email": "...",
    "name": "..."
  },
  "tenant": {
    "id": "...",
    "slug": "..."
  },
  "iat": 1754000000,
  "exp": 1754000900
}
```

**Encrypted delivery:**

```
#payload=eyJ2ZXJzaW9uIjoxLCJub25jZSI6Ii4uLiIsImV0aCI6Ii4uLiIsImNpcGhlciI6Ii4uLiJ9...
```

### Encryption Spec

| Parameter | Value |
|-----------|-------|
| Algorithm | AES-256-GCM |
| Key | `ENCRYPTION_KEY` env var (32 bytes / 256 bits, hex-encoded) |
| Nonce | 12 bytes, random per payload |
| Auth tag | 16 bytes (GCM default) |
| Encoding | `base64url(nonce + auth_tag + ciphertext)` — no padding |

**Wire format:**

```
[12 bytes nonce][16 bytes auth tag][N bytes ciphertext]
```

All concatenated, then base64url-encoded.

### Why AES-GCM

- **Confidentiality**: tokens are encrypted, not visible in browser history, logs, or referrer headers
- **Integrity**: GCM auth tag ensures the payload is not tampered with
- **Authenticity**: only parties with the shared secret can create valid payloads
- **No signature needed**: GCM provides both encryption and authentication in one step
- **Attack prevention**: prevents token injection, replay, and tampering — if an attacker modifies the payload, decryption fails and the target app rejects it

### Key Rotation

- `ENCRYPTION_KEY` is shared between Auth, Consult, and Cert apps
- On key rotation: accept payloads encrypted with the previous key for a grace period (configurable via `ENCRYPTION_KEY_PREVIOUS`)
- The splash page encrypts with the current key only

### Error Handling

If decryption fails (tampered payload, wrong key, expired):
- Target app shows a generic error page
- No token data is exposed
- User is redirected to login

### Admin Session

- On admin login the controller establishes a Laravel **web session**: `Auth::guard('web')->login($user)`, then regenerates the session ID (session-fixation prevention), then redirects to `admin.users`.
- Admins do **not** receive tokens via fragment on web login; the dashboard is server-rendered and session-authenticated (see `admin-dashboard.md`).
- An already-session-authenticated admin visiting `GET /login` is redirected straight to `admin.users`.
- Admin logout: `POST /admin/logout` destroys the session (see `admin-dashboard.md`).

### Rejected Login Cleanup

When a non-admin logs in without a valid tenant redirect, the controller revokes the just-issued refresh token via `IdentityService::logout($tokens['refresh_token'])` before re-rendering the form, so no orphan refresh-token row is left behind.

---

## 4.2 Forgot Password

### Routes

```
GET  /forgot-password
POST /forgot-password
```

### Steps

1. User submits email. The form (or API consumer) may include an optional `redirect` parameter — the tenant-app origin/URL that triggered the flow.
2. System calls `IdentityService.requestPasswordReset(email)` (existing).
3. If the user exists: send a `reset-password` email containing:

```
https://auth.lyceumalabang.edu.ph/reset-password?token={rawToken}&email={email}&redirect={validated-url}
```

   `redirect` is embedded **only after validation** (see "Post-Reset Redirect Resolution" below); invalid values are dropped, never emailed.
4. Always respond with a generic success message (anti-enumeration).
5. Rate limit: 1 request per 60 seconds per email (Laravel throttle).

---

## 4.3 Change Password (email-confirmed)

### Routes

```
POST /api/v1/auth/password/change-request    [jwt.auth]
GET  /reset-password?token=...&email=...
POST /reset-password
```

### Steps

1. Authenticated user triggers a change request (from the Auth API or a Consult/Cert UI calling it with the user's JWT).
2. System generates a token and sends a `change-password` email linking to the **same** `/reset-password` form.
3. User opens the link: the form shows the pre-filled, read-only email plus a new-password field.
4. User submits the new password.
5. System validates the token (exists, not expired, not used), updates the password, and revokes all refresh tokens for the user (`token-lifecycle.md`).
6. On success: redirect per "Post-Reset Redirect Resolution" below (app origin when validly provided; otherwise `/login`).

---

## 4.3a Post-Reset Redirect Resolution

After **Update password** succeeds on `/reset-password`, the user is returned to the app that started the flow — not stranded on the auth login page:

1. **Generation time** (`POST /forgot-password` form and `POST /api/v1/auth/password/forgot`): an optional `redirect` value is accepted and validated — only origins matching an active tenant's `redirect_origins`, or entries in the `AUTH_ALLOWED_REDIRECTS` bootstrap allowlist, are embedded into the emailed link as `&redirect=`. Everything else is dropped.
2. **Form carry-through**: `/reset-password?...&redirect=...` keeps the value in a hidden field so it survives the POST.
3. **Consumption time**: on successful reset, the posted `redirect` is re-validated with the same allowlist rule (never trust the round-trip blindly).
4. **Outcome**: valid ⇒ `302` to that app URL; missing/invalid ⇒ fall back to `/login`.
5. Session semantics: `IdentityService::resetPassword()` already revoked all refresh tokens; landing on the tenant app forces immediate re-login there. Any live access token simply expires within its TTL.

Open-redirect prevention is mandatory at both validation points (§11 anti-pattern: "Following any `redirect=` value").

---

## 4.4 SSO Registration (LOA Domains Only)

### Routes

```
GET  /sso/register
POST /sso/register
```

### Steps

1. User visits `GET /sso/register`.
2. User submits name, email, and password.
3. System validates the request server-side.
4. **Domain check**: email must end with `@lyceumalabang.edu.ph` or `@itmlyceumalabang.onmicrosoft.com`. Other domains are rejected with a generic error.
5. On validation failure: re-render the form with field-level errors.
6. On success: call `IdentityService::register(email, password, name)`.
7. If the email is already registered: re-render the form with a generic "An account with this email already exists" error (anti-enumeration).
8. On success: redirect to `/sso/login` with a success flash message ("Account created. Please sign in.").

### Domain Restriction

Only LOA email domains are allowed to self-register:

| Domain | Type |
|--------|------|
| `@lyceumalabang.edu.ph` | LOA primary |
| `@itmlyceumalabang.onmicrosoft.com` | LOA Microsoft |

All other domains are rejected. External users must be pre-provisioned by a platform admin.

### UI

Minimal form: name, email, password, confirm password, submit. Title: "Create your LOA account". Link: "Already have an account? Sign in" → `/sso/login`.

---

# 5. Unification: Forgot vs Change

Forget-password and change-password are the same mechanism with different triggers and email copy.

| Aspect | Forgot Password | Change Password |
|--------|-----------------|-----------------|
| Trigger | unauthenticated | authenticated (JWT required) |
| Entry | `/forgot-password` form | `POST /api/v1/auth/password/change-request` |
| Token | `PasswordResetToken` | `PasswordResetToken` (same) |
| Email template | `emails.reset-password` | `emails.change-password` |
| Consumer form | `/reset-password` | `/reset-password` (same) |
| Password update | via `resetPassword()` | via `resetPassword()` (same) |

No duplicate flow logic. One implementation, two entry points, two email templates.

The existing `PUT /api/v1/auth/password` (current-password-based change) remains for authenticated API clients.

---

# 6. Form Token Validation

- Every web form includes `@csrf`; Laravel's `web` middleware group provides CSRF protection automatically.
- The `/reset-password` form validates the one-time `PasswordResetToken`:
  - token is hashed in storage (SHA-256)
  - single-use
  - 60-minute expiry
  - generic "invalid or expired" error on any failure
- Rules live in `kernels/identity/rules/password-reset-flow.md`.

No custom form-token implementation is required.

---

# 7. Email Notifications

- Mailer: Laravel Mail via SMTP (cPanel).
- Env configuration:

```
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@lyceumalabang.edu.ph
MAIL_FROM_NAME="LOA Platform"
```

- Templates (Blade):
  - `resources/views/emails/reset-password.blade.php`
  - `resources/views/emails/change-password.blade.php`
- The raw token travels only inside the emailed link. The database stores only the SHA-256 hash.

---

# 8. Configuration

```
AUTH_ADMIN_GROUP=loa-auth-admin
ENCRYPTION_KEY=<hex-encoded-32-byte-key>
```

- `AUTH_ADMIN_GROUP` — the user-group whose members are platform admins (dashboard access).
- `ENCRYPTION_KEY` — shared AES-256 key for encrypting redirect payloads (hex-encoded, 64 chars). Generate with: `openssl rand -hex 32`
- `ENCRYPTION_KEY_PREVIOUS` — (optional) previous key for graceful rotation. Accept payloads encrypted with this key during rotation grace period.
- Tenant redirect origins are stored per tenant (`tenants.redirect_origins`) and resolved by `TenantService::resolveTenantByRedirectOrigin` (see `kernels/identity/tenancy.md`).
- `AUTH_ALLOWED_REDIRECTS` — **bootstrap fallback only**, used when no tenants are provisioned. Not the primary mechanism.
- `AUTH_REDIRECT_URL` — **deprecated.** No longer used to decide the login destination. Kept only for backwards compatibility; the login destination is resolved strictly per the decision table in section 4.1.

---

# 9. Web Route Summary

```
# Admin (session-authenticated)
GET  /login                 Admin login form
POST /login                 Authenticate admin → /admin/users

# SSO (tenant app redirect)
GET  /sso/login             SSO login form (minimal: email + password)
POST /sso/login             Authenticate → encrypt tokens → redirect to tenant app
GET  /sso/register          SSO registration form (LOA domains only)
POST /sso/register          Create account → redirect to /sso/login
GET  /redirect              Redirect splash page (tenant users)

# Password management
GET  /forgot-password       Forgot password form
POST /forgot-password       Send reset link
GET  /reset-password        Change password form (token-validated)
POST /reset-password        Update password + revoke refresh tokens
```

Admin routes (session-authenticated; full spec in `admin-dashboard.md`):

```
GET    /admin/users              User management list (search + status filter)
POST   /admin/users/{id}/status  Enable / disable a user
POST   /admin/logout             Destroy admin session
```

## New API Endpoint

```
POST /api/v1/auth/password/change-request    [jwt.auth]    → 204
```

No body. Uses the authenticated user; sends the change-password email to that user's address.

### Password API Surface (complete)

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /api/v1/auth/password/forgot` | public (`password.reset.throttle`) | Send reset/change link |
| `POST /api/v1/auth/password/reset` | public | Reset with `{token, password}` (min 8, upper/lower/digit); revokes all refresh tokens |
| `PUT /api/v1/auth/password` | `jwt.auth` | Change with `{old_password, new_password}` |
| `POST /api/v1/auth/password/change-request` | `jwt.auth` | Email a change link to self |

`forgot` and `reset` are intentionally public — the emailed single-use token replaces JWT as the identity proof for forgotten passwords. See `kernels/identity/rules/password-reset-flow.md`.

---

# 10. Security Checklist

- [ ] Open-redirect prevention via tenant `redirect_origins` (strict origin match)
- [ ] `/login` rejects non-admin users (no SSO redirect from admin login)
- [ ] `/sso/login` rejects admin users (admins use `/login`)
- [ ] `/sso/login` requires valid `?redirect=` matching a tenant's `redirect_origins`
- [ ] Tokens delivered via URL fragment only, and only to tenant contexts
- [ ] Generic errors (no user enumeration, no lockout disclosure)
- [ ] Reset requests rate-limited (1/60s per email)
- [ ] Reset tokens single-use, 60-minute expiry, hashed at rest
- [ ] CSRF on all web forms
- [ ] No raw token in database; raw token only in the emailed link
- [ ] SSO registration rejects non-LOA domains
- [ ] Registration rate-limited to prevent mass account creation
- [ ] Admin session ID regenerated on login and logout (session-fixation prevention)
- [ ] Admin dashboard routes gated by web guard + admin group check (see `admin-dashboard.md`)

---

# 11. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Tokens in query string | Logged by servers, leaked via Referer | URL fragment |
| Following any `redirect=` value | Open redirect vulnerability | Tenant `redirect_origins` check, reject on mismatch |
| Admin login redirecting to tenant apps | Confuses admin and SSO flows | Separate routes: `/login` (admin), `/sso/login` (SSO) |
| SSO login creating admin sessions | Scope escalation | `/sso/login` rejects admin users |
| "Email not registered" response | User enumeration | Generic success always |
| Revealing that an account is non-admin | Admin-only disclosure | Generic "Invalid credentials" |
| A separate change-password code path | Duplicated logic | Shared `/reset-password` flow |
| Long-lived web session as auth state for tenant users | Breaks stateless JWT model | Session only for CSRF; admin session for dashboard only |
| Granting non-admins a web auth session | Scope escalation | Only platform admins ever get a web session |
| LOA-only registration on `/register` | Confuses SSO and admin registration | SSO registration at `/sso/register` with domain restriction |

---

# 12. Implementation Inventory

## 12.1 Blade Templates

All views live in `resources/views/`.

| View | Path | Purpose |
|------|------|---------|
| Admin login form | `resources/views/login.blade.php` | Email + password form (admin-only, no redirect logic) |
| SSO login form | `resources/views/sso-login.blade.php` | Minimal email + password form (SSO redirect for tenant apps) |
| SSO registration form | `resources/views/sso-register.blade.php` | Name, email (LOA domains only), password, confirm password |
| Redirect splash | `resources/views/redirect.blade.php` | "Redirecting to {app}..." with auto-redirect |
| Forgot password form | `resources/views/forgot-password.blade.php` | Email input + success message |
| Reset password form | `resources/views/reset-password.blade.php` | Read-only email, new password + confirm, token-hidden input |
| Reset password email (forgot) | `resources/views/emails/reset-password.blade.php` | Mailable template for forgot flow |
| Change password email | `resources/views/emails/change-password.blade.php` | Mailable template for change flow |

## 12.2 Web Controllers

| Controller | Methods | Routes |
|------------|---------|--------|
| `WebAuthController` | `showLogin`, `login` (admin), `showSSOLogin`, `ssoLogin`, `showSSORegister`, `ssoRegister`, `showRedirect`, `showForgotPassword`, `sendResetLinkEmail` | `GET|POST /login`, `GET|POST /sso/login`, `GET|POST /sso/register`, `GET /redirect`, `GET|POST /forgot-password` |
| `WebResetController` | `showResetForm`, `reset` | `GET|POST /reset-password` |

The existing `AuthController` handles the JSON API layer only (`/api/v1/auth/*`). Web routes are separate.

## 12.3 New API Endpoint

| Route | Controller Method | Auth | Response |
|-------|-------------------|------|----------|
| `POST /api/v1/auth/password/change-request` | `AuthController::changePasswordRequest` | `jwt.auth` | `204` (no body) |

Uses the authenticated user's email; sends a `change-password` email linking to `/reset-password`.

---

# 13. Form Field Specifications

## 13.1 Login Form

| Field | Name | Type | Rules |
|-------|------|------|-------|
| Email | `email` | text | `required|email` |
| Password | `password` | password | `required|string` |

## 13.2 Forgot Password Form

| Field | Name | Type | Rules |
|-------|------|------|-------|
| Email | `email` | text | `required|email` |

## 13.3 Reset Password Form

| Field | Name | Type | Rules |
|-------|------|------|-------|
| Token | `token` | hidden | `required|string` (from URL query param) |
| Email | `email` | text (read-only) | `required|email` (from URL query param) |
| New Password | `password` | password | `required|string|min:8\|confirmed\|regex:/[A-Z]/\|regex:/[a-z]/\|regex:/[0-9]/` |
| Confirm Password | `password_confirmation` | password | `required|string` (must match `password`) |

Validation rule notation: `min:8` (8 chars), `confirmed` (matches `password_confirmation`), regex enforces at least one uppercase, one lowercase, one digit.

## 13.4 Registration Form

| Field | Name | Type | Rules |
|-------|------|------|-------|
| Name | `name` | text | `required|string|max:255` |
| Email | `email` | text | `required|email|max:255` |
| Password | `password` | password | `required|string|min:8\|regex:/[A-Z]/\|regex:/[a-z]/\|regex:/[0-9]/` |
| Confirm Password | `password_confirmation` | password | `required|string` (must match `password`) |

Password validation is identical to the reset-password form (section 13.3) minus the `confirmed` rule, since confirmation is handled via `password_confirmation` matching in the web form. The API uses `confirmed` if needed; the web controller validates the two fields explicitly and passes only `password` to `IdentityService::register()`.

---

# 14. Rate Limiting

## 14.1 Forgot Password

| Route | Throttle | Config |
|-------|----------|--------|
| `POST /forgot-password` | 1 request per 60 seconds per email+IP | `PasswordResetThrottle` middleware, applied in `routes/web.php` |
| `POST /api/v1/auth/password/forgot` (API) | 1 request per 60 seconds per email+IP | `PasswordResetThrottle` middleware |

`PasswordResetThrottle` uses Laravel's `RateLimiter` with a key derived from the normalized email and client IP. When rate-limited, it returns the same generic success message. Never reveal the throttle condition to the caller.

## 14.2 Login

Login uses the Identity Kernel's brute-force protection (`maxAttempts = 5`, `lockoutMinutes = 30`). No additional web-layer throttle needed; the lockout message must be surfaced as a generic "Invalid credentials" error.

## 14.3 SSO Registration

| Route | Throttle | Config |
|-------|----------|--------|
| `POST /sso/register` | 5 requests per 60 seconds per IP | Laravel `throttle:5,60` middleware in `routes/web.php` |

Prevents mass account creation. Uses the same generic "Please wait and try again" error when throttled.

---

# 15. Session & CSRF Configuration

## 15.1 Session Lifetime

Sessions carry the CSRF token for all visitors and, for platform admins, the authenticated web identity. Admin sessions therefore need a meaningful lifetime.

| Variable | Local | Production |
|----------|-------|------------|
| `SESSION_DRIVER` | `database` | `database` (preferred; `file` also works on cPanel) |
| `SESSION_LIFETIME` | `120` | `480` (minutes — admin sessions) |
| `SESSION_EXPIRE_ON_CLOSE` | `true` | `true` |
| `SESSION_HTTP_ONLY` | `true` | `true` |
| `SESSION_SECURE` | `false` (HTTP locally) | `true` (HTTPS in production) |
| `SESSION_SAME_SITE` | `lax` | `lax` |

`SESSION_SECURE=true` requires the subdomain to be served over HTTPS; otherwise the session cookie is dropped by the browser and every POST fails with 419 "Page Expired".

## 15.2 CSRF

Web routes use Laravel's default `web` middleware group, which includes `VerifyCsrfToken`. Every form includes `@csrf`, including the admin dashboard forms. No exceptions.

---

# 16. Deployment Considerations

## 16.1 Environment Variables

Add to `.env` for the web UI layer:

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_URL` | `https://auth.lyceumalabang.edu.ph` | Base URL for email links |
| `SESSION_DRIVER` | `database` | Preferred; `file` also works on cPanel |
| `SESSION_LIFETIME` | `480` | Admin sessions |
| `SESSION_SECURE` | `true` | HTTPS in production only |
| `AUTH_ALLOWED_REDIRECTS` | `https://aces-api.lyceumalabang.edu.ph,https://e-cert.vercel.app` | Comma-separated origin allowlist (tenant contexts) |
| `AUTH_ADMIN_GROUP` | `loa-auth-admin` | User-group treated as platform admins |
| `MAIL_*` | (see section 7) | SMTP credentials |
| `JWT_SECRET` | (same as other apps) | Required for token signing |

`AUTH_REDIRECT_URL` is deprecated and not required.

## 16.2 Production Routing

On cPanel, the document root is `public/`. The `index.php` inside `public/` handles both `/api/v1/*` (JSON) and `/login`, `/forgot-password`, `/reset-password`, `/admin/*` (web). A single Laravel app serves both surfaces.

## 16.3 Post-Deploy Verification

| Check | Expected |
|-------|----------|
| `GET /login` | 200, returns admin login form HTML |
| `POST /login` (admin credentials) | 302 → `/admin/users`; admin session cookie set |
| `POST /login` (non-admin) | 200, re-renders form with generic "Access denied" |
| `GET /sso/login` | 200, returns SSO login form HTML |
| `POST /sso/login` (non-admin + valid `?redirect=`) | 302 → `/redirect` → `{appUrl}#payload={encrypted}` |
| `POST /sso/login` (admin) | 200, re-renders form with generic "Access denied" |
| `POST /sso/login` (non-admin, no redirect) | 200, re-renders form with generic "Access denied" |
| `GET /sso/register` | 200, returns SSO registration form HTML |
| `POST /sso/register` (LOA domain) | 302 → `/sso/login` with success flash message |
| `POST /sso/register` (non-LOA domain) | 200, re-renders form with generic error |
| `POST /sso/register` (duplicate email) | 200, re-renders form with generic "already exists" error |
| `GET /admin/users` (no session) | 302 → `/login` |
| `GET /admin/users` (admin session) | 200, user list renders |
| `POST /forgot-password` (any email) | 200, generic success message |
| `GET /reset-password?token=...&email=...` | 200, returns form with pre-filled email |
| `POST /reset-password` (valid token + password) | 302 → `/login` |
| Mailpit (local) | Emails captured, links contain raw token in query param |
| SMTP (prod) | Forgot/change emails delivered with correct links |

---

# 17. Dependency References

This spec relies on the following existing Final specs:

| Spec | Role |
|------|------|
| `kernels/identity/rules/account-status.md` | Status check before password validation in login |
| `kernels/identity/rules/password-reset-flow.md` | Token generation, hashing, single-use, 60-min expiry |
| `kernels/identity/rules/token-lifecycle.md` | Refresh token revocation on password reset/change |
| `kernels/identity/entities/refresh-token.md` | `revokeAllRefreshTokens` on disable |
| `kernels/identity/entities/password-reset-token.md` | Token storage, expiry, used_at fields |
| `kernels/identity/README.md` (IdentityService) | `login()`, `requestPasswordReset()`, `resetPassword()` contracts |
| `admin-dashboard.md` | User-management dashboard: routes, access model, actions |
