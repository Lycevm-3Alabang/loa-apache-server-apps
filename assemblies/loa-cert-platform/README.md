# LOA Cert Platform
## Product Assembly Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The LOA Cert Platform Product Assembly composes Business Contexts to deliver a digital certificate management application for Lyceum of Alabang.

It assembles existing Business Contexts into a deployable API application without owning any business logic.

The LOA Cert Platform answers:

> **"How do we issue, manage, and verify digital certificates?"**

It does not own user authentication, consultation workflows, or evaluation logic.

---

# 2. Business Contexts Included

The LOA Cert Platform includes the following Business Contexts:

```
Certificate
     ↓
Event (domain)
     ↓
PDF Service (platform service)
```

---

# 3. What the LOA Cert Platform Owns

The LOA Cert Platform owns:

- API routing and middleware
- JWT validation (via shared secret)
- role-based access enforcement
- request/response transformation
- API documentation
- deployment configuration
- file storage configuration

The LOA Cert Platform does not own any business logic.

---

# 4. What the LOA Cert Platform Does NOT Own

The LOA Cert Platform does not own:

- user registration or authentication
- JWT token issuance
- certificate business rules
- template rendering logic
- certificate number generation
- PDF rendering
- email delivery
- consultation workflows
- evaluation logic

Those belong to the Auth Platform, Certificate Context, or Consult Platform.

See `assemblies/loa-auth-platform/group-permission-management.md` for how `cert.*` permissions are assigned to users via groups.

---

# 5. Included Business Contexts

## Certificate

Owns the certificate lifecycle:

- certificate issuance
- certificate revocation
- certificate deletion
- certificate verification
- certificate number generation
- template management
- email delivery with PDF attachment
- audit trail

---

# 6. Included Domains

## Event

Owns event and attendee management:

- event CRUD
- event lifecycle (draft, active, archive)
- attendee management
- CSV import
- attendance tracking

---

# 7. Excluded Business Contexts

The LOA Cert Platform explicitly excludes:

```
Consultation
Evaluation
Commercial
CRM
Workshop
Inventory
Fleet
Finance
```

---

# 8. Platform Dependencies

The LOA Cert Platform relies on Platform Kernels for:

```
Identity (user data via Auth API)
Organization (multi-tenant support)
Document (certificate as document)
```

Platform Kernels are consumed via the Auth API, not direct database access.

---

# 9. Services Dependencies

The LOA Cert Platform consumes:

```
PDF Service (certificate PDF generation)
Notification Service (email with PDF attachment)
Storage Service (PDF file persistence)
```

---

# 10. API Surface

The LOA Cert Platform exposes the following API groups:

```
# Events
GET    /api/v1/events
POST   /api/v1/events
GET    /api/v1/events/{id}
PUT    /api/v1/events/{id}
DELETE /api/v1/events/{id}

# Attendees
GET    /api/v1/events/{id}/attendees
POST   /api/v1/events/{id}/attendees
POST   /api/v1/events/{id}/attendees/import
DELETE /api/v1/events/{id}/attendees/{aid}

# Templates
GET    /api/v1/templates
POST   /api/v1/templates
GET    /api/v1/templates/{id}
PUT    /api/v1/templates/{id}
DELETE /api/v1/templates/{id}

# Certificates
POST   /api/v1/certificates
POST   /api/v1/certificates/bulk
GET    /api/v1/certificates
GET    /api/v1/certificates/{id}
GET    /api/v1/certificates/{id}/pdf
PUT    /api/v1/certificates/{id}/revoke
DELETE /api/v1/certificates/{id}
POST   /api/v1/certificates/{id}/email

# Public (no auth)
GET    /api/v1/verify/{certificate_number}
GET    /api/v1/view/{id}

# Auth Callback
POST   /api/v1/auth/callback

# Admin
GET    /api/v1/admin/audit
GET    /api/v1/admin/dashboard
GET    /api/v1/admin/users
PUT    /api/v1/admin/users/{id}/role
```

---

# 11. SSO Redirect and Callback

The LOA Cert Platform does not issue its own JWT tokens. Authentication is delegated entirely to the LOA Auth Platform via a browser-based redirect flow with encrypted token delivery.

## 11.1 Flow Overview

```
User visits cert.loa.edu.ph
    |
    v
[No valid session/token]
    |
    v
Frontend redirects browser to:
    auth.loa.edu.ph/login?redirect=https://cert.loa.edu.ph
    |
    v
User authenticates on auth.loa.edu.ph
    |
    v
Auth Platform encrypts JWT payload (AES-256-GCM)
    |
    v
Auth Platform redirects browser to:
    https://cert.loa.edu.ph#payload=<encrypted_base64url>
    |
    v
Cert Platform frontend extracts fragment
    |
    v
Frontend calls POST /api/v1/auth/callback with encrypted payload
    |
    v
Backend decrypts payload, validates tokens, returns session tokens
    |
    v
Frontend stores tokens, establishes authenticated session
```

## 11.2 Redirect to Auth Platform

The Cert Platform frontend initiates SSO by redirecting the browser to the Auth Platform login page.

**Redirect URL format:**

```
https://auth.loa.edu.ph/login?redirect=https://cert.loa.edu.ph
```

**Parameters:**

| Parameter  | Required | Description |
|-----------|----------|-------------|
| `redirect` | Yes | The origin URL of the Cert Platform (`https://cert.loa.edu.ph`). Must match the Auth Platform's allowed redirects list. |

**Requirements:**

- The `redirect` origin must be registered in Auth Platform's `AUTH_ALLOWED_REDIRECTS` or tenant `redirect_origins`
- The Cert Platform must NOT append paths or query strings to the redirect URL — Auth Platform only validates the origin
- The Cert Platform must store the current page/location so the user can be returned to it after authentication

## 11.3 Callback Endpoint

After the Auth Platform authenticates the user and redirects back with the encrypted payload, the Cert Platform backend exposes an endpoint to process the callback.

### `POST /api/v1/auth/callback`

Processes the encrypted SSO payload and returns validated session tokens.

**Request:**

```http
POST /api/v1/auth/callback
Content-Type: application/json
```

```json
{
  "payload": "<base64url_encoded_aes256gcm_blob>"
}
```

**Encrypted Payload Structure (after decryption):**

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "Bearer",
  "expires_in": 900,
  "user": {
    "id": "usr_abc123",
    "email": "teacher@loa.edu.ph",
    "name": "Juan Dela Cruz"
  },
  "tenant": {
    "id": "tenant_loa",
    "slug": "loa"
  },
  "iat": 1754000000,
  "exp": 1754000900
}
```

**Success Response (200):**

```json
{
  "status": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": {
      "id": "usr_abc123",
      "email": "teacher@loa.edu.ph",
      "name": "Juan Dela Cruz"
    },
    "tenant": {
      "id": "tenant_loa",
      "slug": "loa"
    }
  }
}
```

**Error Responses:**

| Status | Condition |
|--------|-----------|
| 400 | Missing or malformed `payload` field |
| 400 | Payload decryption failed (invalid key, tampered data) |
| 400 | Payload expired (`exp` < current time) |
| 401 | User referenced in payload does not exist or is disabled |
| 401 | User is not a member of the Cert Platform tenant |
| 403 | Tenant in payload does not match Cert Platform's configured tenant |

**Behavior:**

1. Extract and base64url-decode the `payload` string
2. Decrypt using AES-256-GCM with the shared `ENCRYPTION_KEY`
3. Validate `exp` claim has not passed
4. Validate the user exists and is active via Auth Platform API (or local user cache)
5. Validate user belongs to the Cert Platform tenant
6. Auto-link to existing user by email (or create new SSO user)
7. Resolve e-cert role from SSO `permissions` claim (see Section 5)
8. Set session cookie
9. Return the decrypted tokens and user info to the frontend
10. Log the authentication event

## 11.4 Encryption Contract

The Cert Platform must share the `ENCRYPTION_KEY` with the Auth Platform to decrypt payloads.

**Key Format:**

```
ENCRYPTION_KEY=<64_character_hex_string>
# OR
ENCRYPTION_KEY=base64:<base64_encoded_32_bytes>
```

**Decryption Algorithm:**

1. Base64url-decode the payload string (restore padding)
2. Split decoded bytes: `nonce[12] + auth_tag[16] + ciphertext[...]`
3. AES-256-GCM decrypt using `ENCRYPTION_KEY`, nonce, and auth tag
4. Parse resulting JSON

**Key Rotation:**

The Cert Platform may configure `ENCRYPTION_KEY_PREVIOUS` to support key rotation. If decryption with the current key fails, the previous key is tried. This allows the Auth Platform to rotate keys without breaking in-flight redirects.

```
ENCRYPTION_KEY_PREVIOUS=<old_64_character_hex_string>
```

## 11.5 Session Establishment

After the callback succeeds, the Cert Platform frontend stores the returned tokens:

| Storage Method | Lifetime | Scope |
|---------------|----------|-------|
| Access Token | 15 minutes (default) | In-memory or short-lived cookie |
| Refresh Token | 7 days (default) | Secure cookie or local storage |

**Token Usage:**

```
Authorization: Bearer <access_token>
```

All authenticated API requests include the access token in the `Authorization` header. The Cert Platform validates JWT tokens locally using the shared `JWT_SECRET` — no HTTP call to Auth Platform per request.

**Token Refresh:**

When the access token expires, the frontend calls:

```
POST https://auth.loa.edu.ph/api/v1/auth/refresh
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

The Auth Platform returns a new token pair. The old refresh token is rotated (single-use).

## 11.6 Logout

Logout requires two actions:

1. **Frontend:** Clear stored tokens (cookies, local storage)
2. **Backend (optional):** Revoke refresh token via Auth Platform API

```
POST https://auth.loa.edu.ph/api/v1/auth/logout
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

## 11.7 Security Requirements

| Requirement | Detail |
|-------------|--------|
| HTTPS Only | All redirect URLs must use HTTPS in production |
| Origin Validation | Auth Platform validates redirect origin against allowlist |
| Encrypted Payload | AES-256-GCM ensures confidentiality and integrity |
| Key Management | `ENCRYPTION_KEY` must be at least 32 bytes, rotated periodically |
| Fragment Isolation | URL fragments are not sent to servers — only client-side JS can read them |
| Token Storage | Access tokens should NOT be stored in `localStorage` (XSS risk). Prefer in-memory or httpOnly cookies. |
| CSRF Protection | Callback endpoint should include CSRF token validation |
| Payload Expiry | Encrypted payloads have an `exp` claim; stale payloads must be rejected |

## 11.8 Auth Platform Configuration Reference

The Auth Platform must include `https://cert.loa.edu.ph` in its allowed redirects:

```env
# Auth Platform .env
AUTH_ALLOWED_REDIRECTS=https://consult.loa.edu.ph,https://cert.loa.edu.ph
ENCRYPTION_KEY=<shared_key>
```

Tenant-level configuration (if using multi-tenancy):

```sql
UPDATE tenants
SET redirect_origins = '["https://cert.loa.edu.ph"]'
WHERE slug = 'loa';
```

---

# 12. Frontend Implementation Example

The following is a reference implementation for the Cert Platform frontend SSO callback handler. This is not part of the API spec — it is a guide for frontend engineers.

## 12.1 Callback Handler (JavaScript/TypeScript)

```typescript
// sso-callback.ts

interface SSOPayload {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  user: {
    id: string;
    email: string;
    name: string;
  };
  tenant: {
    id: string;
    slug: string;
  } | null;
}

interface CallbackResponse {
  status: string;
  data: SSOPayload;
}

/**
 * Called on app load when URL contains #payload=<encrypted>
 * Extracts the encrypted fragment and sends it to the backend.
 */
async function handleSSOCallback(): Promise<SSOPayload | null> {
  const hash = window.location.hash;

  if (!hash.startsWith('#payload=')) {
    return null;
  }

  const payload = decodeURIComponent(hash.substring('#payload='.length));

  // Clear the fragment from the URL (history cleanup)
  window.history.replaceState(
    {},
    document.title,
    window.location.pathname + window.location.search
  );

  const response = await fetch('/api/v1/auth/callback', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payload }),
  });

  if (!response.ok) {
    throw new Error(`SSO callback failed: ${response.status}`);
  }

  const result: CallbackResponse = await response.json();
  return result.data;
}

/**
 * Stores tokens after successful SSO callback.
 */
function storeTokens(data: SSOPayload): void {
  // Store access token in memory (not localStorage)
  sessionStorage.setItem('access_token', data.access_token);

  // Store refresh token in httpOnly cookie via backend
  // (set by the /api/v1/auth/callback response as Set-Cookie)
}

/**
 * Initiates SSO login redirect.
 */
function redirectToAuthPlatform(): void {
  const certOrigin = window.location.origin;
  const authUrl = `https://auth.loa.edu.ph/login?redirect=${encodeURIComponent(certOrigin)}`;
  window.location.href = authUrl;
}
```

## 12.2 Backend Callback Controller (Laravel)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthCallbackController extends Controller
{
    public function __construct(
        private readonly EncryptionService $encryption,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payload' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or invalid payload',
            ], 400);
        }

        $decrypted = $this->encryption->decrypt(
            $request->string('payload')->toString()
        );

        if ($decrypted === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload decryption failed',
            ], 400);
        }

        if (isset($decrypted['exp']) && $decrypted['exp'] < time()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload expired',
            ], 400);
        }

        $userId = $decrypted['user']['id'] ?? null;

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid user data in payload',
            ], 401);
        }

        // Validate user via Auth Platform API
        $authResponse = Http::withToken($decrypted['access_token'])
            ->get(config('auth-platform.base_url') . '/api/v1/auth/verify');

        if (!$authResponse->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token verification failed',
            ], 401);
        }

        // Validate tenant
        $expectedTenant = config('cert-platform.tenant_slug');
        $actualTenant = $decrypted['tenant']['slug'] ?? null;

        if ($expectedTenant && $actualTenant !== $expectedTenant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant mismatch',
            ], 403);
        }

        // Log authentication event
        // ...

        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $decrypted['access_token'],
                'refresh_token' => $decrypted['refresh_token'],
                'token_type' => $decrypted['token_type'],
                'expires_in' => $decrypted['expires_in'],
                'user' => $decrypted['user'],
                'tenant' => $decrypted['tenant'],
            ],
        ]);
    }
}
```

## 12.3 Backend Encryption Service (Laravel)

```php
<?php

namespace App\Services;

class EncryptionService
{
    private ?string $key;
    private ?string $previousKey;

    public function __construct()
    {
        $rawKey = config('auth-platform.encryption_key', '');
        $this->key = $rawKey !== '' ? $this->decodeKey($rawKey) : null;

        $rawPrevious = config('auth-platform.encryption_key_previous', '');
        $this->previousKey = $rawPrevious !== '' ? $this->decodeKey($rawPrevious) : null;
    }

    public function isConfigured(): bool
    {
        return $this->key !== null;
    }

    public function decrypt(string $encoded): ?array
    {
        if ($this->key === null) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/') . '==', true);

        if ($decoded === false || strlen($decoded) < 29) {
            return null;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false && $this->previousKey !== null) {
            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->previousKey,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
            );
        }

        if ($plaintext === false) {
            return null;
        }

        $payload = json_decode($plaintext, true);

        return is_array($payload) ? $payload : null;
    }

    private function decodeKey(string $rawKey): string
    {
        if (str_starts_with($rawKey, 'base64:')) {
            $key = base64_decode(substr($rawKey, 7), true);
            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('ENCRYPTION_KEY base64 must decode to 32 bytes');
            }
            return $key;
        }

        $key = hex2bin($rawKey);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('ENCRYPTION_KEY must be 64 hex chars or base64: prefixed');
        }

        return $key;
    }
}
```

## 12.4 Route Registration

```php
// routes/api.php
use App\Http\Controllers\Api\AuthCallbackController;

Route::post('/auth/callback', AuthCallbackController::class)
    ->middleware(['throttle:10,1']);
```

## 12.5 Configuration

```env
# Cert Platform .env
JWT_SECRET=<shared_hmac_secret_with_auth_platform>
AUTH_PLATFORM_BASE_URL=https://auth.loa.edu.ph
ENCRYPTION_KEY=<same_key_as_auth_platform>
ENCRYPTION_KEY_PREVIOUS=
CERT_TENANT_SLUG=loa
```

```php
// config/auth-platform.php
return [
    'base_url' => env('AUTH_PLATFORM_BASE_URL', 'https://auth.loa.edu.ph'),
    'jwt_secret' => env('JWT_SECRET'),
    'encryption_key' => env('ENCRYPTION_KEY', ''),
    'encryption_key_previous' => env('ENCRYPTION_KEY_PREVIOUS', ''),
];

// config/cert-platform.php
return [
    'tenant_slug' => env('CERT_TENANT_SLUG', 'loa'),
];
```

---

# 13. Deployment

The LOA Cert Platform is deployed as a standalone Laravel 12 application.

Deployment configuration:

- cPanel hosting
- PHP 8.2+
- MySQL 8 database
- Subdomain: cert.loa.edu.ph
- Document root: public/

See `web-ui.md` for the frontend specification (auth guard, SSO callback handling, token lifecycle).

---

# 14. Cross-App Integration

The LOA Cert Platform integrates with:

```
LOA Cert Platform ──JWT──► LOA Auth Platform (token validation)
LOA Cert Platform ──HTTP──► LOA Auth Platform (user lookup)
LOA Consult Platform ──Event──► LOA Cert Platform (future: evaluation → certificate)
```

Cross-app communication uses HTTP with Bearer tokens.

---

# 15. Future Evolution

The LOA Cert Platform may evolve to support:

- visual template editor (canvas-based)
- bulk certificate issuance workflow
- QR code verification
- certificate sharing via public URL
- certificate revocation list
- batch email delivery
- certificate analytics
- API key authentication for external systems
- webhook notifications

Future additions should continue to represent certificate management workflows.

---

# 16. Anti-Patterns

The following are architectural violations.

## Authentication Ownership

```
LOA Cert Platform

issues JWT tokens
```

JWT issuance belongs to the Auth Platform.

---

## Direct Database Access

```
LOA Cert Platform

reads Auth database for user data
```

User data is accessed via the Auth API. Each app owns its database.

---

## Evaluation Logic

```
LOA Cert Platform

computes evaluation results
```

Evaluation computation belongs to the Consult Platform.

---

# 17. Guiding Principle

The LOA Cert Platform is a thin composition layer.

It wires together Business Contexts for certificate management.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
