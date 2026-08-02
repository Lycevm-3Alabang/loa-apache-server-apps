# LOA Cert Platform — Web UI
## Product Assembly Component Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-cert-platform`)
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

Browser-based web UI for the LOA Cert Platform:

- SSO callback handler (receives encrypted tokens from Auth Platform)
- Auth guard (detects unauthenticated state, redirects to login)
- Token lifecycle management (storage, refresh, expiry handling)
- Dashboard and certificate management pages
- Public certificate verification page (no auth required)

The Cert Platform does not own authentication. It consumes JWT tokens issued by the Auth Platform via the SSO redirect flow.

---

# 2. Scope

## Owns

- SSO callback handling (`#payload=` fragment extraction)
- JWT token storage and lifecycle (access + refresh)
- Auth guard logic (route protection, redirect to Auth Platform)
- Token refresh coordination with Auth Platform
- Dashboard page (authenticated)
- Certificate management pages (authenticated)
- Public verification page (no auth)
- Error pages (403, 404, 500)

## Does Not Own

- User login form (hosted by Auth Platform at `auth.loa.edu.ph`)
- User registration (hosted by Auth Platform)
- Password reset (hosted by Auth Platform)
- JWT token issuance
- Any authentication UI beyond the callback handler

---

# 3. Architecture Note: Auth Delegation

The Cert Platform is a **pure JWT consumer**. It has no login form, no registration form, and no password reset flow. All authentication UI lives on the Auth Platform.

The Cert Platform frontend is responsible for:

1. Detecting when the user is not authenticated
2. Redirecting the browser to `auth.loa.edu.ph/login?redirect=https://cert.loa.edu.ph`
3. Handling the return trip (encrypted payload in URL fragment)
4. Storing and refreshing JWT tokens
5. Attaching tokens to API requests

---

# 4. SSO Callback Flow

## 4.1 Fragment Detection

When the browser arrives at `cert.loa.edu.ph` from the Auth Platform redirect, the URL contains an encrypted payload in the fragment:

```
https://cert.loa.edu.ph#payload=<base64url_aes256gcm_blob>
```

**Detection logic (runs on every page load):**

```typescript
function hasSSOPayload(): boolean {
  return window.location.hash.startsWith('#payload=');
}
```

## 4.2 Fragment Extraction and Cleanup

1. Read `window.location.hash`
2. Extract everything after `#payload=`
3. URL-decode the base64url string
4. Clear the fragment from the URL bar (prevent refresh re-processing)

```typescript
function extractPayload(): string | null {
  const hash = window.location.hash;
  if (!hash.startsWith('#payload=')) {
    return null;
  }
  const payload = decodeURIComponent(hash.substring('#payload='.length));
  window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
  return payload;
}
```

## 4.3 Backend Callback

The frontend sends the encrypted payload to the Cert Platform backend for decryption and validation.

```
POST /api/v1/auth/callback
Content-Type: application/json

{
  "payload": "<encrypted_string>"
}
```

**Response (success):**

```json
{
  "status": "success",
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": { "id": "...", "email": "...", "name": "..." },
    "tenant": { "id": "...", "slug": "..." }
  }
}
```

**Response (failure):**

```json
{
  "status": "error",
  "message": "Payload decryption failed"
}
```

## 4.4 Post-Callback Redirect

After successful callback:

1. Store tokens (see Section 5)
2. Redirect to the originally intended destination (see Section 6)

If the callback fails:

1. Clear any partial state
2. Redirect to the dashboard (or show an error page)
3. Do NOT redirect back to Auth Platform in a loop

---

# 5. SSO Group and Permission Mapping

The Cert Platform does not define its own role model for SSO users. It reads permissions from the Auth Platform JWT claims.

## 5.1 Permission-Based Role Resolution

The Auth Platform resolves a user's effective permissions from their group memberships before issuing the JWT. The Cert Platform maps these permissions to its local role model:

| Permission Key | e-cert Role | Access Level |
|---------------|-------------|--------------|
| `cert.events.manage` | admin | Full event + certificate management |
| `cert.certificates.manage` | admin | Full certificate management |
| `cert.templates.manage` | staff | Template management |
| `cert.certificates.issue` | staff | Issue certificates |
| `cert.certificates.view_all` | staff | View all certificates |
| *(none of the above)* | participant | Own certificates only |

## 5.2 Resolution Logic

```typescript
function resolveRoleFromPermissions(permissions: string[]): UserRole {
  if (permissions.includes("cert.certificates.manage")) return "admin";
  if (permissions.includes("cert.certificates.issue")) return "staff";
  return "participant";
}
```

Permissions are read from the JWT `permissions` claim, which is an array of permission keys resolved by the Auth Platform.

## 5.3 Why Permissions Over Groups

- Groups are universal ("Faculty", "Students") — not app-specific
- Permissions are app-scoped (`cert.*`, `consult.*`)
- Same group can grant different permissions per app
- No hardcoded group-name mapping needed
- Auth Platform handles resolution; Cert Platform just checks the result

## 5.4 Group Examples (Reference Only)

These are Auth Platform group names — the Cert Platform never references them directly:

| Group | Typical cert.* Permissions |
|-------|---------------------------|
| Faculty | `cert.certificates.issue`, `cert.events.manage` |
| Program Chair | `cert.certificates.manage`, `cert.templates.manage` |
| Students | *(none)* → participant |

---

# 6. Token Lifecycle

## 5.1 Token Storage

| Token | Storage Location | Lifetime | Scope |
|-------|-----------------|----------|-------|
| Access Token | JavaScript variable (in-memory) | 15 minutes | Not persisted; lost on page refresh |
| Refresh Token | `httpOnly` cookie (set by backend) | 7 days | Sent automatically with requests to same origin |
| User Info | JavaScript variable (in-memory) | Session duration | Cached for UI rendering |

**Why not `localStorage`?**

`localStorage` is accessible to any JavaScript on the page, making it vulnerable to XSS attacks. The access token should live only in memory. The refresh token should be in an `httpOnly` cookie set by the backend callback response.

**Why not `sessionStorage`?**

`sessionStorage` is also accessible to JavaScript and vulnerable to XSS. Same risks as `localStorage` for token storage.

## 5.2 Token Usage

All authenticated API requests include the access token:

```
Authorization: Bearer <access_token>
```

The frontend attaches this header via an HTTP client interceptor (e.g., Axios interceptor, Fetch wrapper).

## 5.3 Token Refresh

When a 401 response is received (expired access token):

1. The frontend calls `POST https://auth.loa.edu.ph/api/v1/auth/refresh` with the refresh token
2. Auth Platform returns a new token pair
3. Frontend updates the in-memory access token
4. Frontend retries the original request with the new token

**Refresh flow:**

```
API Request → 401 Unauthorized
    |
    v
POST auth.loa.edu.ph/api/v1/auth/refresh
  { refresh_token: "..." }
    |
    v
New { access_token, refresh_token } received
    |
    v
Update in-memory access token
    |
    v
Retry original request
```

**Concurrent refresh handling:**

If multiple requests fail with 401 simultaneously, only one refresh call should be made. Subsequent 401s should wait for the in-flight refresh to complete before retrying.

## 5.4 Token Expiry Detection

The frontend can proactively check expiry before making requests:

```typescript
function isTokenExpiringSoon(decodedJWT: { exp: number }): boolean {
  const bufferSeconds = 60; // refresh 1 minute before expiry
  return decodedJWT.exp < Math.floor(Date.now() / 1000) + bufferSeconds;
}
```

If the token is expiring soon, refresh preemptively rather than waiting for a 401.

## 5.5 Logout

Logout requires:

1. **Frontend:** Clear in-memory access token and user info
2. **Frontend:** Call `POST /api/v1/auth/callback` endpoint to clear the refresh token cookie (or call Auth Platform logout directly)
3. **Frontend:** Redirect to Auth Platform login page

```
POST https://auth.loa.edu.ph/api/v1/auth/logout
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

After logout, the user is redirected to:

```
https://auth.loa.edu.ph/login?redirect=https://cert.loa.edu.ph
```

---

# 7. Return-to-URL Routing

## 6.1 Capturing the Intended Destination

When the auth guard redirects to Auth Platform, the Cert Platform should preserve the user's intended destination so they return there after authentication.

**Strategy:**

The Cert Platform stores the intended destination in `sessionStorage` before redirecting to Auth Platform. After the SSO callback, it reads the stored destination and redirects there.

```typescript
function redirectToLogin(): void {
  const intended = window.location.pathname + window.location.search;
  sessionStorage.setItem('cert_return_to', intended);

  const certOrigin = window.location.origin;
  const authUrl = `https://auth.loa.edu.ph/login?redirect=${encodeURIComponent(certOrigin)}`;
  window.location.href = authUrl;
}

function getReturnUrl(): string {
  const stored = sessionStorage.getItem('cert_return_to');
  sessionStorage.removeItem('cert_return_to');
  return stored || '/dashboard';
}
```

## 6.2 Post-Authentication Redirect

After successful SSO callback:

1. Read `cert_return_to` from `sessionStorage`
2. If present and starts with `/` (relative path), redirect there
3. If absent, redirect to `/dashboard`
4. Never redirect to external URLs (open redirect prevention)

## 6.3 Protected vs Public Routes

| Route | Auth Required | Behavior |
|-------|--------------|----------|
| `/dashboard` | Yes | Redirect to Auth Platform if no token |
| `/events` | Yes | Redirect to Auth Platform if no token |
| `/events/{id}` | Yes | Redirect to Auth Platform if no token |
| `/templates` | Yes | Redirect to Auth Platform if no token |
| `/certificates` | Yes | Redirect to Auth Platform if no token |
| `/certificates/{id}` | Yes | Redirect to Auth Platform if no token |
| `/verify/{certificate_number}` | No | Public page, no auth guard |
| `/view/{id}` | No | Public page, no auth guard |
| `/admin/*` | Yes | Redirect to Auth Platform if no token |

---

# 8. Auth Guard

## 7.1 Guard Logic

The auth guard runs on every route navigation (or page load). It determines whether the user can access the current route.

**Decision flow:**

```
Route navigation
    |
    v
Is route public? (verify, view)
    |-- YES → Allow (no auth check)
    |
    v
Is there an access token in memory?
    |-- NO → Check for SSO callback fragment
    |         |-- HAS #payload= → Process callback
    |         |-- NO → Redirect to Auth Platform
    |
    v
Is token expired?
    |-- YES → Attempt refresh
    |         |-- Refresh succeeds → Update token, allow
    |         |-- Refresh fails → Redirect to Auth Platform
    |
    v
Allow navigation
```

## 7.2 Initialization Sequence

On application boot (page load), the frontend executes:

```
1. Check for #payload= fragment
   → If present: extract, send to /api/v1/auth/callback, store tokens, redirect to return URL
   → If absent: continue

2. Check for access token in memory
   → If present: validate expiry, proceed to route
   → If absent: redirect to Auth Platform

3. Check route permissions (if role-based)
   → If unauthorized: show 403 page
```

## 7.3 Auth State

The frontend maintains an auth state object:

```typescript
interface AuthState {
  isAuthenticated: boolean;
  user: {
    id: string;
    email: string;
    name: string;
  } | null;
  tenant: {
    id: string;
    slug: string;
  } | null;
  accessToken: string | null;
}
```

This state is:
- Set after successful SSO callback
- Cleared on logout
- Lost on page refresh (must re-validate via token expiry check or silent refresh)

## 7.4 Silent Refresh on Load

On page load, if no access token is in memory but a refresh token exists (in `httpOnly` cookie), the frontend should attempt a silent refresh:

```
POST https://auth.loa.edu.ph/api/v1/auth/refresh
Cookie: refresh_token=...
```

If successful, the frontend has a valid access token without user interaction. If failed, redirect to Auth Platform.

---

# 9. HTTP Client Configuration

## 8.1 Axios Interceptor (Example)

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true, // send refresh token cookie
});

// Request interceptor: attach access token
api.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }
  return config;
});

// Response interceptor: handle 401
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      try {
        const { data } = await axios.post(
          'https://auth.loa.edu.ph/api/v1/auth/refresh',
          { refresh_token: refreshToken },
        );

        accessToken = data.access_token;
        originalRequest.headers.Authorization = `Bearer ${accessToken}`;
        return api(originalRequest);
      } catch {
        // Refresh failed — redirect to login
        redirectToLogin();
        return Promise.reject(error);
      }
    }

    return Promise.reject(error);
  },
);
```

---

# 10. Pages

## 9.1 Dashboard

- **Route:** `/dashboard`
- **Auth:** Required
- **Content:** Summary stats (total certificates, recent activity, quick actions)
- **Redirect on no auth:** Auth Platform login

## 9.2 Events

- **Route:** `/events`
- **Auth:** Required
- **Content:** Event list with search, filter, create button
- **Redirect on no auth:** Auth Platform login

## 9.3 Event Detail

- **Route:** `/events/{id}`
- **Auth:** Required
- **Content:** Event details, attendee list, certificate issuance controls
- **Redirect on no auth:** Auth Platform login

## 9.4 Templates

- **Route:** `/templates`
- **Auth:** Required
- **Content:** Template list, create/edit controls
- **Redirect on no auth:** Auth Platform login

## 9.5 Certificates

- **Route:** `/certificates`
- **Auth:** Required
- **Content:** Certificate list with search, filter, bulk actions
- **Redirect on no auth:** Auth Platform login

## 9.6 Certificate Detail

- **Route:** `/certificates/{id}`
- **Auth:** Required
- **Content:** Certificate details, PDF preview, revoke/email controls
- **Redirect on no auth:** Auth Platform login

## 9.7 Public Verification

- **Route:** `/verify/{certificate_number}`
- **Auth:** Not required (public)
- **Content:** Certificate verification result (valid/revoked/expired)
- **No auth guard**

## 9.8 Public View

- **Route:** `/view/{id}`
- **Auth:** Not required (public)
- **Content:** Certificate view (read-only)
- **No auth guard**

---

# 11. Error Pages

| Page | Route | Content |
|------|-------|---------|
| 403 | `/403` | "You do not have permission to access this page" |
| 404 | `/404` | "Page not found" |
| 500 | `/500` | "Something went wrong" |
| Auth Error | `/auth-error` | "Authentication failed. Please try again." |

The auth error page is shown when:

- SSO callback decryption fails
- Payload is expired
- User is not a member of the Cert Platform tenant
- Token refresh fails permanently

---

# 12. Security Checklist

- [ ] Access token stored in memory only (not `localStorage`, `sessionStorage`, or cookies)
- [ ] Refresh token in `httpOnly` cookie (set by backend, not accessible to JavaScript)
- [ ] No tokens in URL query parameters (only in fragment, which is never sent to servers)
- [ ] Fragment cleared from URL bar after extraction (prevent refresh re-processing)
- [ ] Return-to-URL is a relative path only (open redirect prevention)
- [ ] CSRF protection on `POST /api/v1/auth/callback` (Laravel `VerifyCsrfToken`)
- [ ] Auth guard checks token expiry before granting access
- [ ] Concurrent refresh requests deduplicated (only one refresh call at a time)
- [ ] Logout clears both frontend state and backend refresh token
- [ ] Public routes (`/verify`, `/view`) do not trigger auth guard

---

# 13. Configuration

```env
# Cert Platform frontend
VITE_AUTH_PLATFORM_URL=https://auth.loa.edu.ph
VITE_CERT_ORIGIN=https://cert.loa.edu.ph
```

```php
// config/auth-platform.php (backend)
return [
    'base_url' => env('AUTH_PLATFORM_BASE_URL', 'https://auth.loa.edu.ph'),
    'jwt_secret' => env('JWT_SECRET'),
    'encryption_key' => env('ENCRYPTION_KEY', ''),
    'encryption_key_previous' => env('ENCRYPTION_KEY_PREVIOUS', ''),
];

// config/cert-platform.php (backend)
return [
    'tenant_slug' => env('CERT_TENANT_SLUG', 'loa'),
];
```

---

# 14. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Storing access token in `localStorage` | XSS vulnerability — any script can read it | In-memory JavaScript variable |
| Storing access token in cookie | CSRF vulnerability — cookies sent automatically | In-memory variable + `Authorization` header |
| Redirecting to Auth Platform on every 401 without deduplication | Infinite refresh loops | Deduplicate concurrent refresh attempts |
| Following external return-to URLs | Open redirect vulnerability | Relative paths only |
| Skipping auth guard on "minor" pages | Inconsistent security model | All non-public routes use the same guard |
| Showing login form on Cert Platform | Authentication is Auth Platform's responsibility | Redirect to Auth Platform |
| Retaining `#payload=` in URL after processing | Refresh re-processes the payload, double-login | Clear fragment immediately |

---

# 15. Dependency References

This spec relies on the following:

| Spec | Role |
|------|------|
| `assemblies/loa-auth-platform/web-ui.md` | Auth Platform SSO redirect flow, encrypted payload format |
| `assemblies/loa-auth-platform/README.md` | Auth Platform API surface (`/api/v1/auth/refresh`, `/api/v1/auth/logout`) |
| `assemblies/loa-auth-platform/group-permission-management.md` | User-group and permission management API (how cert.* permissions are assigned) |
| `assemblies/loa-cert-platform/README.md` | Cert Platform API surface, SSO callback contract (`POST /api/v1/auth/callback`) |
| `kernels/identity/README.md` | JWT token structure, token lifecycle rules |
