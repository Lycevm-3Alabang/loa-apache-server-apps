# Certificate Activation — Cross-Platform Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (Cert Platform + Auth Platform)
**Audience:** Engineers, AI Development Agents

---

# 1. Purpose

It answers:

> **"How does a certificate recipient who doesn't yet have a cert-user account claim their account and set a password, using only the certificate issuance email?"**

When the Cert Platform issues a certificate, it sends an email to the recipient. If the recipient already has a cert-user account, they see a "View Certificate" button. If they don't have an account, they see an "Activate Account" button that lets them create an account by setting a password — not a full registration form.

---

# 2. Scope

## Owns

- Cert Platform: conditional email content (activate button vs. view-only)
- Cert Platform: registration status check via Auth Platform API
- Cert Platform: activation URL generation
- Auth Platform: certificate validation endpoint (`GET /set-password/cert`)
- Auth Platform: user creation + tenant/group assignment + password set flow
- Auth Platform: existing user detection + redirect to SSO login

## Does Not Own

- Certificate issuance logic (existing Cert Platform flow)
- Email delivery infrastructure (existing mail system)
- User management UI (Auth Platform admin dashboard)
- JWT token issuance (Auth Platform SSO flow)
- Frontend SPA (e-cert Next.js app)

---

# 3. Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Cert Platform                            │
│  (loa-cert-platform, Laravel)                                   │
│                                                                 │
│  CertificateController / EventController                        │
│       │                                                         │
│       ▼                                                         │
│  CertUserChecker::isRegistered(email)                           │
│       │                                                         │
│       ▼                                                         │
│  CertificateEmail Mailable                                      │
│       │                                                         │
│       ▼                                                         │
│  Email sent to recipient                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ (if not registered)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Auth Platform                             │
│  (loa-auth-platform, Laravel)                                   │
│                                                                 │
│  GET /set-password/cert?cert=X&email=Y                          │
│       │                                                         │
│       ├── Validate cert+email via Cert Platform API             │
│       │                                                         │
│       ├── If user exists → redirect to SSO login                │
│       │                                                         │
│       └── If user new → create user + show set-password form    │
│                                                                 │
│  POST /set-password (existing)                                  │
│       │                                                         │
│       └── Set password + activate account                       │
└─────────────────────────────────────────────────────────────────┘
```

---

# 4. Flow

## 4.1 Email Sending (Cert Platform)

```
Certificate issued
    │
    ▼
CertUserChecker::isRegistered($email)
    │
    ├── true  → $isRegistered = true, $activateUrl = null
    │           Email shows "View Certificate" button only
    │
    └── false → $isRegistered = false, $activateUrl = generated URL
                Email shows "Activate Account" button + "View Certificate" button
```

## 4.2 Activation Link Click (Auth Platform)

```
User clicks "Activate Account" link
    │
    ▼
GET /set-password/cert?cert=CERT-001&email=user@example.com
    │
    ├── Validate inputs (cert non-empty, email valid format)
    │   └── Invalid → redirect to login with error
    │
    ├── Validate cert+email against Cert Platform
    │   └── Invalid/expired → redirect to login with error
    │
    ├── Check if user already exists
    │   └── Exists → redirect to SSO login with status message
    │
    └── New user:
        ├── Create user (pending status)
        ├── Add to loa-e-cert tenant
        ├── Add to cert-user group
        ├── Generate set-password token
        └── Show set-password form
```

## 4.3 Password Set (Auth Platform)

```
User submits password
    │
    ▼
POST /set-password
    │
    ├── Validate token (not expired, not used)
    │   └── Invalid → back with error
    │
    ├── Set password + activate user
    │
    └── Redirect to login with success message
```

---

# 5. API Contracts

## 5.1 Cert Platform → Auth Platform

### Check Registration Status

```
GET /api/v1/tenant/members?email={email}&limit=1
Headers:
  X-Api-Key: {api_key}
  Accept: application/json

Response 200:
{
  "data": [
    {
      "id": "...",
      "email": "user@example.com",
      ...
    }
  ]
}

Response 200 (empty):
{
  "data": []
}
```

**Logic:** If `data` is non-empty, the user is registered.

## 5.2 Auth Platform → Cert Platform

### Validate Certificate

```
GET /api/v1/verify/{certificate_number}

Response 200:
{
  "data": {
    "certificate_number": "CERT-001",
    "recipient_name": "Juan Dela Cruz",
    "recipient_email": "juan@example.com",
    "status": "active",
    ...
  }
}

Response 404:
{
  "message": "Certificate not found"
}
```

**Logic:** Auth Platform checks:
1. Response status is 200
2. `data.recipient_email` matches the `email` query parameter
3. `data.status` is `active`

---

# 6. Email Template Changes

## 6.1 Conditional Button

```blade
@if(!$isRegistered && $activateUrl)
    {{-- Green "Activate Account" button --}}
    <a href="{{ $activateUrl }}" class="btn btn-success">
        Activate Account
    </a>
    <p>A certificate has been issued in your name. Create your account to access it.</p>
@endif

{{-- Always present: "View Certificate" button --}}
<a href="{{ $viewUrl }}" class="btn btn-primary">
    View Certificate
</a>
```

## 6.2 Mailable Parameters

```php
class CertificateEmail extends Mailable
{
    public function __construct(
        // ... existing params ...
        public bool $isRegistered = true,
        public ?string $activateUrl = null,
    ) {}
}
```

---

# 7. Security Considerations

## 7.1 Certificate Validation

- Auth Platform validates cert+email against Cert Platform before creating any user
- Only certificates with `status: active` are accepted
- The `recipient_email` must exactly match the `email` query parameter

## 7.2 Existing User Detection

- If the email already belongs to a cert-user, redirect to SSO login (no duplicate accounts)
- Race condition: if two requests try to create the same user simultaneously, the second one catches the exception and redirects to login

## 7.3 Token Security

- Set-password tokens are SHA-256 hashed before storage
- Tokens expire after 48 hours
- Tokens are single-use (marked as `used_at` after password set)

## 7.4 Rate Limiting

- `GET /set-password/cert` is throttled (10 requests per 60 seconds per IP)
- `POST /set-password` uses existing throttle

## 7.5 Privacy

- The `recipient_email` field is added to the public verify endpoint response
- This is acceptable because:
  - Certificate numbers are not predictable
  - The recipient name is already exposed
  - The email is already on the certificate PDF

---

# 8. Error Handling

| Scenario | Behavior |
|----------|----------|
| Missing cert or email params | Redirect to login with "Invalid activation link" |
| Invalid email format | Redirect to login with "Invalid activation link" |
| Certificate not found | Redirect to login with "Invalid or expired certificate" |
| Certificate expired/revoked | Redirect to login with "Invalid or expired certificate" |
| Email doesn't match cert | Redirect to login with "Invalid or expired certificate" |
| User already exists | Redirect to SSO login with "You already have an account" |
| Auth Platform unreachable | Redirect to login with "Invalid or expired certificate" |
| User creation fails | Redirect to login with "Unable to create account" |
| Tenant not found | User created but no tenant/group assignment (logged warning) |
| Group not found | User added to tenant but not to group (logged warning) |

---

# 9. Configuration

## 9.1 Cert Platform

```php
// config/auth-platform.php
return [
    'base_url' => env('AUTH_PLATFORM_URL', 'https://auth.lyceumalabang.edu.ph'),
    'api_key' => env('AUTH_PLATFORM_API_KEY', ''),
    'http_timeout' => env('AUTH_PLATFORM_HTTP_TIMEOUT', 5),
];
```

## 9.2 Auth Platform

```php
// config/cert-platform.php
return [
    'base_url' => env('CERT_PLATFORM_URL', 'http://localhost:9001'),
];
```

---

# 10. Anti-Patterns

| Anti-Pattern | Why It Violates |
|--------------|-----------------|
| Creating user without validating cert | Arbitrary account creation vulnerability |
| GET request creating state without CSRF | Email prefetchers/forwarding could trigger unintended creation |
| Showing activate button when check fails | Fail-open exposes button to all recipients |
| Hardcoded tenant slug | Breaks if slug changes; should use config |
| No transaction on user creation | Partial creation leaves inconsistent state |

---

# 11. Open Items

1. **Two-step GET/POST flow:** The current implementation uses GET to create state. A confirmation page + POST would be more secure but adds complexity. Consider for v2.
2. **Batch registration checks:** For bulk sends (100+ certificates), individual HTTP calls are slow. Consider batching in v2.
3. **Name generation:** Currently derives name from email prefix. Could pass `recipient_name` from certificate for better defaults.

---

# 12. Testing

## 12.1 Test Files

| Component | Test File | Type |
|-----------|-----------|------|
| `CertUserChecker` | `tests/Unit/Services/CertUserCheckerTest.php` | Unit |
| `SetPasswordController` cert activation | `tests/Feature/SetPassword/CertActivationTest.php` | Feature |
| `PublicCertificateController` verify response | `tests/Feature/Api/PublicCertificateTest.php` | Feature (updated) |

## 12.2 CertUserChecker Unit Tests

| Test | Expected Behavior |
|------|-------------------|
| `test_is_registered_returns_true_when_member_exists` | Auth Platform returns member data → `true` |
| `test_is_registered_returns_true_when_api_key_missing` | No API key configured → `true` (fail-closed) |
| `test_is_registered_returns_true_when_api_fails` | Auth Platform returns 500 → `true` (fail-closed) |
| `test_is_registered_returns_true_when_api_timeout` | HTTP timeout → `true` (fail-closed) |
| `test_is_registered_returns_true_when_email_not_found` | Auth Platform returns empty data → `false` |
| `test_get_activate_url_builds_correct_url` | Returns `{auth_url}/set-password/cert?cert=X&email=Y` |

## 12.3 SetPasswordController Feature Tests

| Test | Expected Behavior |
|------|-------------------|
| `test_redirects_to_login_with_missing_params` | Missing `cert` or `email` → 302 to `/login` |
| `test_redirects_to_login_with_invalid_email` | Invalid email format → 302 to `/login` |
| `test_redirects_to_login_when_cert_invalid` | Cert Platform returns 404 → 302 to `/login` |
| `test_redirects_to_login_when_cert_expired` | Cert status is `expired` → 302 to `/login` |
| `test_redirects_to_login_when_email_mismatch` | Email doesn't match cert → 302 to `/login` |
| `test_redirects_to_sso_when_user_exists` | User already registered → 302 to `/sso/login` |
| `test_shows_confirmation_page_for_new_user` | Valid cert + new email → 200 with confirmation form |
| `test_creates_user_in_transaction` | Valid cert + new email → user created, added to tenant+group, token generated |
| `test_handles_missing_tenant_gracefully` | Tenant not found → user created, warning logged |
| `test_handles_missing_group_gracefully` | Group not found → user added to tenant, warning logged |

## 12.4 Test Mocking Strategy

### CertUserChecker (Unit)

```php
// Mock HTTP to return member data
Http::fake([
    'auth.lyceumalabang.edu.ph/api/v1/tenant/members' => Http::response([
        'data' => [['id' => '1', 'email' => 'user@example.com']],
    ], 200),
]);
```

### CertActivationTest (Feature)

```php
// Mock Cert Platform verify endpoint
Http::fake([
    'localhost:9001/api/v1/verify/*' => Http::response([
        'data' => [
            'certificate_number' => 'CERT-001',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_email' => 'juan@example.com',
            'status' => 'active',
        ],
    ], 200),
]);
```

## 12.5 Running Tests

```bash
# Cert Platform — CertUserChecker unit tests
docker compose exec app php vendor/bin/phpunit tests/Unit/Services/CertUserCheckerTest.php

# Auth Platform — CertActivation feature tests
docker compose exec app php vendor/bin/phpunit tests/Feature/SetPassword/CertActivationTest.php

# Cert Platform — PublicCertificate tests (updated assertions)
docker compose exec app php vendor/bin/phpunit tests/Feature/Api/PublicCertificateTest.php
```

---

# 13. Guiding Principle

> **Certificate-gated account creation.** The certificate is the invitation. No separate invite email, no registration form. The recipient sets a password and they're in.
