# LOA Auth Platform — Web UI
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Browser-based authentication UI for the LOA platform ecosystem:

- login page (email + password)
- post-login redirect to a configured application URL
- forgot password page (email link to change-password form)
- change password page (email-confirmed, token-validated)

The Auth Platform remains the single source of truth for identity. This spec adds a web surface on top of the existing stateless JWT API.

---

# 2. Scope

## Owns

- login web form
- redirect resolution after successful login
- forgot password web form
- email-notification triggers for reset/change links
- change-password form (one-time token validation)
- CSRF protection on all web forms

## Does Not Own

- registration UI (registration stays API-only for now)
- user dashboard / profile pages
- application-specific pages (Consult, Cert)
- any business workflow outside identity

---

# 3. Architecture Note: Hybrid Surface

The Auth Platform keeps its stateless JWT API (`/api/v1/*`) for machine consumers (Consult, Cert).

The web UI (`/login`, `/forgot-password`, `/reset-password`) runs on Laravel web routes:

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

---

# 11. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Tokens in query string | Logged by servers, leaked via Referer | URL fragment |
| Following any `redirect=` value | Open redirect vulnerability | Allowlist check, fallback default |
| "Email not registered" response | User enumeration | Generic success always |
| A separate change-password code path | Duplicated logic | Shared `/reset-password` flow |
| Long-lived web session as auth state | Breaks stateless JWT model | Session only for CSRF |
