# LOA Auth Platform — Web UI
## Product Assembly Component Specification

**Version:** 1.1
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Browser-based authentication UI for the LOA platform ecosystem:

- login page (email + password)
- registration page (name, email, password)
- post-login redirect to a configured application URL
- forgot password page (email link to change-password form)
- change password page (email-confirmed, token-validated)

The Auth Platform remains the single source of truth for identity. This spec adds a web surface on top of the existing stateless JWT API.

---

# 2. Scope

## Owns

- login web form
- registration web form (name, email, password)
- redirect resolution after successful login
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

The web UI (`/login`, `/register`, `/forgot-password`, `/reset-password`) runs on Laravel web routes:

- minimal session exists **only** to carry the CSRF token (Laravel default `web` middleware)
- authentication state after login is the JWT pair, not the web session
- no cross-app session sharing

---

# 4. Flows

## 4.1 Login → Redirect

### Routes

```
GET  /login?redirect=<app-url>
POST /login
```

### Steps

1. User visits `GET /login`.
2. User submits email + password.
3. System validates via `IdentityService.login()` (respects account lockout and brute-force rules).
4. On success: `302` redirect to the resolved application URL with the token pair in the **URL fragment**.
5. On failure: re-render the form with a generic "Invalid credentials" error. Never reveal whether the email exists or whether the account is locked.

### Redirect Target Resolution

The login form includes a **"New here? Create an account"** link pointing to `/register`. The registration form includes a **"Already have an account? Sign in"** link pointing to `/login`.

| Source | Rule |
|--------|------|
| `?redirect=` query param | Host must match an entry in `AUTH_ALLOWED_REDIRECTS` allowlist |
| No param / invalid param | Fall back to `AUTH_REDIRECT_URL` default |

An unlisted host is **never** followed. Open redirects are a security violation.

### Token Delivery

```
302 Location: {appUrl}#access_token=...&refresh_token=...&token_type=Bearer&expires_in=...
```

- Tokens travel in the fragment (never the query string) so they are not written to server logs or sent in the Referer header.
- The target app's frontend reads the fragment, stores tokens securely, and strips the fragment from the URL.

---

## 4.2 Forgot Password

### Routes

```
GET  /forgot-password
POST /forgot-password
```

### Steps

1. User submits email.
2. System calls `IdentityService.requestPasswordReset(email)` (existing).
3. If the user exists: send a `reset-password` email containing:

```
https://auth.loa.edu.ph/reset-password?token={rawToken}&email={email}
```

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
6. On success: redirect to `/login`.

---

## 4.4 Registration

### Routes

```
GET  /register
POST /register
```

### Steps

1. User visits `GET /register`.
2. User submits name, email, and password.
3. System validates the request server-side (see section 13.4).
4. On validation failure: re-render the form with field-level errors.
5. On success: call `IdentityService::register(email, password, name)`.
6. If the email is already registered: re-render the form with a generic "An account with this email already exists" error (anti-enumeration).
7. On success: redirect to `/login` with a success flash message ("Account created. Please sign in.").

### Visual Direction

The registration page reuses the same split layout as login (dark navy brand panel on the left, light form card on the right). The brand panel content adapts to the registration context:

- Heading: **"Welcome to Connect Hub"**
- Subheading: **"Create your account to manage bookings and consultations"**

The form card contains:

- Name field
- Email field
- Password field (with show/hide toggle)
- Confirm Password field (with show/hide toggle)
- "Sign up" primary button
- "Already have an account? Sign in" link → `/login`

The layout is responsive: on mobile the brand panel stacks above the form card.

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
MAIL_FROM_ADDRESS=noreply@loa.edu.ph
MAIL_FROM_NAME="LOA Platform"
```

- Templates (Blade):
  - `resources/views/emails/reset-password.blade.php`
  - `resources/views/emails/change-password.blade.php`
- The raw token travels only inside the emailed link. The database stores only the SHA-256 hash.

---

# 8. Configuration

```
AUTH_REDIRECT_URL=https://consult.loa.edu.ph
AUTH_ALLOWED_REDIRECTS=https://consult.loa.edu.ph,https://cert.loa.edu.ph
```

Redirect allowlist entries are full origins (scheme + host), matched strictly.

---

# 9. Web Route Summary

```
GET  /login                 Login form
POST /login                 Authenticate + redirect
GET  /register              Registration form
POST /register              Create account + redirect to login
GET  /forgot-password       Forgot password form
POST /forgot-password       Send reset email
GET  /reset-password        Change password form (token-validated)
POST /reset-password        Update password + revoke refresh tokens
```

## New API Endpoint

```
POST /api/v1/auth/password/change-request    [jwt.auth]    → 204
```

No body. Uses the authenticated user; sends the change-password email to that user's address.

---

# 10. Security Checklist

- [ ] Open-redirect prevention via `AUTH_ALLOWED_REDIRECTS`
- [ ] Tokens delivered via URL fragment only
- [ ] Generic errors (no user enumeration, no lockout disclosure)
- [ ] Reset requests rate-limited (1/60s per email)
- [ ] Reset tokens single-use, 60-minute expiry, hashed at rest
- [ ] CSRF on all web forms
- [ ] No raw token in database; raw token only in the emailed link
- [ ] Registration rejects duplicate emails with generic error (no enumeration)
- [ ] Registration rate-limited to prevent mass account creation

---

# 11. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Tokens in query string | Logged by servers, leaked via Referer | URL fragment |
| Following any `redirect=` value | Open redirect vulnerability | Allowlist check, fallback default |
| "Email not registered" response | User enumeration | Generic success always |
| A separate change-password code path | Duplicated logic | Shared `/reset-password` flow |
| Long-lived web session as auth state | Breaks stateless JWT model | Session only for CSRF |
| Revealing "email already registered" details | User enumeration | Generic error on duplicate email |

---

# 12. Implementation Inventory

## 12.1 Blade Templates

All views live in `resources/views/`.

| View | Path | Purpose |
|------|------|---------|
| Login form | `resources/views/login.blade.php` | Email + password form, error display, redirect param passthrough |
| Registration form | `resources/views/register.blade.php` | Name, email, password, confirm password form with field-level errors |
| Forgot password form | `resources/views/forgot-password.blade.php` | Email input + success message |
| Reset password form | `resources/views/reset-password.blade.php` | Read-only email, new password + confirm, token-hidden input |
| Reset password email (forgot) | `resources/views/emails/reset-password.blade.php` | Mailable template for forgot flow |
| Change password email | `resources/views/emails/change-password.blade.php` | Mailable template for change flow |

## 12.2 Web Controllers

| Controller | Methods | Routes |
|------------|---------|--------|
| `WebAuthController` | `showLogin`, `login`, `showRegister`, `register`, `showForgotPassword`, `sendResetLinkEmail` | `GET|POST /login`, `GET|POST /register`, `GET|POST /forgot-password` |
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

## 14.3 Registration

| Route | Throttle | Config |
|-------|----------|--------|
| `POST /register` | 5 requests per 60 seconds per IP | Laravel `throttle:5,60` middleware in `routes/web.php` |

Prevents mass account creation. Uses the same generic "Please wait and try again" error when throttled.

---

# 15. Session & CSRF Configuration

## 15.1 Session Lifetime

Sessions exist solely to carry the CSRF token. Configure `SESSION_LIFETIME` in `.env`:

| Variable | Local | Production |
|----------|-------|------------|
| `SESSION_DRIVER` | `database` | `file` (cPanel, no Redis) |
| `SESSION_LIFETIME` | `5` (minutes) | `5` (minutes) |
| `SESSION_EXPIRE_ON_CLOSE` | `true` | `true` |
| `SESSION_HTTP_ONLY` | `true` | `true` |
| `SESSION_SECURE` | `false` (HTTP locally) | `true` (HTTPS in production) |
| `SESSION_SAME_SITE` | `lax` | `lax` |

## 15.2 CSRF

Web routes use Laravel's default `web` middleware group, which includes `VerifyCsrfToken`. Every form includes `@csrf`. No exceptions.

---

# 16. Deployment Considerations

## 16.1 Environment Variables

Add to `.env` for the web UI layer:

| Variable | Value | Notes |
|----------|-------|-------|
| `APP_URL` | `https://auth.loa.edu.ph` | Base URL for email links |
| `SESSION_DRIVER` | `file` | cPanel has no Redis |
| `SESSION_SECURE` | `true` | HTTPS in production only |
| `AUTH_REDIRECT_URL` | `https://consult.loa.edu.ph` | Fallback redirect |
| `AUTH_ALLOWED_REDIRECTS` | `https://consult.loa.edu.ph,https://cert.loa.edu.ph` | Comma-separated origin allowlist |
| `MAIL_*` | (see section 7) | SMTP credentials |
| `JWT_SECRET` | (same as other apps) | Required for token signing |

## 16.2 Production Routing

On cPanel, the document root is `public/`. The `index.php` inside `public/` handles both `/api/v1/*` (JSON) and `/login`, `/forgot-password`, `/reset-password` (web). A single Laravel app serves both surfaces.

## 16.3 Post-Deploy Verification

| Check | Expected |
|-------|----------|
| `GET /login` | 200, returns login form HTML |
| `POST /login` (valid) | 302 → `{appUrl}#access_token=...&refresh_token=...` |
| `GET /register` | 200, returns registration form HTML |
| `POST /register` (valid) | 302 → `/login` with success flash message |
| `POST /register` (duplicate email) | 200, re-renders form with generic "already exists" error |
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
