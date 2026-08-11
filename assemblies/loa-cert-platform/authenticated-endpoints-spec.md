# LOA Cert Platform — Authenticated Endpoints Specification

**Version:** 1.1 (Updated 2026-08-11 — C-Auth implemented)

All API endpoints require authentication unless listed as "Public". Authentication uses JWT Bearer tokens validated locally via shared HMAC-SHA256 secret.

## Base URL

```
https://cert-api.lyceumalabang.edu.ph/api/v1
```

---

## Auth Endpoints (Public — No JWT Required)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/auth/callback` | SSO callback — decrypts payload, validates JWT, sets httpOnly refresh cookie, returns access token |
| `POST` | `/auth/refresh` | Token refresh — reads httpOnly cookie, proxies to Auth platform, rotates cookie, returns new access token |
| `POST` | `/auth/logout` | Logout — clears refresh cookie, proxies logout to Auth platform, returns 204 |

**SSO Flow:**
1. Browser → `auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app`
2. Auth redirects back → `https://e-cert.vercel.app#payload=<AES-256-GCM encrypted blob>`
3. Frontend extracts `#payload=` → `POST /api/v1/auth/callback` with `{ "payload": "..." }`
4. Response: `{ "status": "success", "data": { "access_token", "token_type", "expires_in", "user", "tenant" } }`
5. Refresh token set as httpOnly cookie `loa_cert_refresh` (invisible to JS)

---

## Public Endpoints (No Authentication Required)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/verify/{certificate_number}` | Verify certificate by number |
| `GET` | `/view/{id}` | Public read-only certificate viewer data |

---

## Events

| Method | Path | Required Level | Description |
|--------|------|----------------|-------------|
| `GET` | `/events` | `read` | List events for the organization |
| `POST` | `/events` | `write` | Create an event |
| `GET` | `/events/{id}` | `read` | Get a single event |
| `PATCH` | `/events/{id}` | `write` | Update an event (partial) |
| `DELETE` | `/events/{id}` | `write` | Delete an event |
| `GET` | `/events/{id}/stats` | `read` | Event statistics |
| `POST` | `/events/{id}/clone-template` | `write` | Clone certificate template for event |
| `POST` | `/events/{id}/clone-email-template` | `write` | Clone email template for event |
| `POST` | `/events/{id}/bulk-issue` | `write` | Bulk issue certificates to attendees |
| `POST` | `/events/{id}/reissue` | `admin` | Reissue certificates for event |
| `GET` | `/events/{id}/revoke-expired` | `read` | Count expired certificates |
| `POST` | `/events/{id}/revoke-expired` | `admin` | Revoke expired certificates |
| `POST` | `/events/{id}/issue-completed` | `write` | Issue certificates for completed attendees |

---

## Events — Attendees

| Method | Path | Required Level | Description |
|--------|------|----------------|-------------|
| `GET` | `/events/{eventId}/attendees` | `read` | List attendees for an event |
| `POST` | `/events/{eventId}/attendees` | `write` | Add a single attendee (upsert by email) |
| `POST` | `/events/{eventId}/attendees/import` | `write` | Bulk import attendees (JSON, `merge`/`replace` mode) |

---

## Attendees

| Method | Path | Required Level | Description |
|--------|------|----------------|-------------|
| `PATCH` | `/attendees/{id}` | `write` | Update an attendee |
| `DELETE` | `/attendees/{id}` | `write` | Remove an attendee |
| `DELETE` | `/attendees/{id}/with-cert` | `admin` | Delete attendee and their certificate |
| `GET` | `/attendees/{id}/delete-preview` | `read` | Preview delete impact |
| `GET` | `/attendees/{id}/file-data` | `read` | Get uploaded certificate source file |

---

## Templates

| Method | Path | Required Level | Description |
|--------|------|----------------|-------------|
| `GET` | `/templates` | `read` | List templates |
| `POST` | `/templates` | `write` | Create a template |
| `GET` | `/templates/{id}` | `read` | Get a template |
| `PATCH` | `/templates/{id}` | `write` | Update a template |
| `DELETE` | `/templates/{id}` | `write` | Delete a template |

---

## Certificates

| Method | Path | Required Level | Description |
|--------|------|----------------|-------------|
| `GET` | `/certificates` | `read` | List certificates |
| `POST` | `/certificates` | `write` | Issue a single certificate |
| `POST` | `/certificates/bulk` | `write` | Bulk issue certificates |
| `POST` | `/certificates/upload` | `write` | Upload pre-rendered certificate PDF |
| `GET` | `/certificates/qr` | `read` | Generate QR code for verification |
| `POST` | `/certificates/expire` | `admin` | Auto-revoke expired certificates |
| `GET` | `/certificates/{id}` | `read` | Get a single certificate |
| `GET` | `/certificates/{id}/pdf` | `read` | Stream PDF (inline) |
| `GET` | `/certificates/{id}/download` | `read` | Download PDF (attachment) |
| `POST` | `/certificates/{id}/revoke` | `admin` | Revoke a certificate |
| `DELETE` | `/certificates/{id}` | `admin` | Delete a certificate permanently |
| `POST` | `/certificates/{id}/email` | `write` | Send certificate email |
| `GET` | `/certificates/{id}/email-logs` | `read` | List email delivery logs |
| `POST` | `/certificates/{id}/reissue` | `admin` | Reissue a certificate |

---

## Authentication Requirements

All endpoints except those listed as "Public" require:

1. `Authorization: Bearer <access_token>` header
2. Valid JWT token issued by LOA Auth Platform
3. Token validates locally using shared `JWT_SECRET` (HMAC-SHA256)
4. Token must be of type `access`, not expired, and contain valid tenant claim (`tenant.slug = loa`)
5. Caller must have sufficient level for the endpoint (checked against JWT `permissions` claim)

**Middleware (enforced on every request):**
- `jwt.auth` — validates JWT signature, type, expiry, and tenant claim
- `jwt.endpoint` — checks `permissions` claim against local endpoint catalog (level-based)

**Error responses:**
- `401` — Missing, expired, or invalid token
- `403` — Valid token but insufficient level for the endpoint
- `429` — Rate limit exceeded (auth endpoints: 10/min per IP)

---

## JWT Token Structure

The access token JWT contains these claims:

```json
{
  "iss": "loa-auth",
  "aud": "loa-cert",
  "iat": 1754000000,
  "exp": 1754000900,
  "type": "access",
  "sub": "user-uuid",
  "email": "user@lyceumalabang.edu.ph",
  "name": "User Name",
  "groups": ["cert-admin"],
  "permissions": [
    "admin:/api/v1/events",
    "admin:/api/v1/events/{id}",
    "read:/api/v1/certificates",
    "write:/api/v1/certificates",
    ...
  ],
  "tenant": {
    "id": "tenant-uuid",
    "slug": "loa"
  }
}
```

**For front-end role mapping:** parse `permissions` claim to determine UI role (see `web-ui.md` §5).

---

## Reference

- **Full spec:** `api-endpoints.md` (Final v1.5)
- **SSO flow:** `legacy-e-cert-integration.md` §6
- **Frontend integration:** `FRONTEND-INTEGRATION.md`
