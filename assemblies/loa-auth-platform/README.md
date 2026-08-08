# LOA Auth Platform
## Product Assembly Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The LOA Auth Platform Product Assembly provides a centralized authentication and identity service for the Lyceum of Alabang platform ecosystem.

It issues and validates JSON Web Tokens (JWT) consumed by all other LOA applications.

The LOA Auth Platform answers:

> **"Who is interacting with the platform?"**

It does not own consultation workflows, evaluation logic, certificate generation, or academic infrastructure.

---

# 2. Kernels Included

The LOA Auth Platform relies on the following Platform Kernel:

```
Identity
```

---

# 3. What the LOA Auth Platform Owns

The LOA Auth Platform owns:

- user registration and lifecycle
- authentication (login, logout, token refresh)
- JWT token issuance and validation
- password management (hashing, reset)
- group-based access control
- user profile management
- audit logging for authentication events
- browser-based authentication UI (see `web-ui.md`)

The LOA Auth Platform does not own consultation, evaluation, or certificate business logic.

---

# 4. What the LOA Auth Platform Does NOT Own

The LOA Auth Platform does not own:

- appointment scheduling
- faculty availability
- consultation workflows
- faculty evaluations
- rubric management
- evaluation results
- certificate generation
- certificate templates
- event management
- PDF rendering

Those belong to the Consult and Cert assemblies.

---

# 5. Included Kernels

## Identity Kernel

Owns digital identities:

- user accounts
- authentication methods (password)
- credential metadata
- identity lifecycle (active, disabled)
- token versioning

---

# 6. Excluded Kernels

The LOA Auth Platform explicitly excludes:

```
Party (non-person identities not managed here)
Workflow
Document
Activity
Audit (consult/cert own their own audit logs)
Events (cross-app events handled via HTTP)
Configuration
Offline
```

---

# 7. Industry Dependencies

The LOA Auth Platform has no Industry Domain dependencies.

It is industry-agnostic.

---

# 8. Services Dependencies

The LOA Auth Platform may consume:

```
Notification Service (email for password reset)
```

Services are optional and configured per deployment.

---

# 9. API Surface

The LOA Auth Platform exposes the following API:

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
PUT    /api/v1/auth/password
POST   /api/v1/auth/password/forgot
POST   /api/v1/auth/password/change-request
POST   /api/v1/auth/password/reset
GET    /api/v1/auth/verify
GET    /api/v1/users
GET    /api/v1/users/{id}
PATCH  /api/v1/users/{id}/status   (admin, `users.manage`) — set status `active`|`disabled`; disabling revokes all refresh tokens
```

All API endpoints return JSON.

`PATCH /users/{id}/status` enforces `kernels/identity/rules/account-status.md` transitions:
- `active` → `disabled`: account can no longer authenticate; **all refresh tokens revoked** (`kernels/identity/entities/refresh-token.md` rule 7)
- `disabled` → `active`: re-enable (also clears lock state)

## Web UI Surface

Browser-facing routes (HTML forms, not JSON):

```
GET  /login
POST /login
GET  /forgot-password
POST /forgot-password
GET  /reset-password
POST /reset-password
```

See `web-ui.md` for the complete web UI specification (login redirect, forgot/change password flows, token validation, email templates).

---

# 10. Deployment

The LOA Auth Platform is deployed as a standalone Laravel 12 application.

Deployment configuration:

- cPanel hosting
- PHP 8.2+
- MySQL 8 database
- Subdomain: auth.lyceumalabang.edu.ph
- Document root: public/

---

# 11. Cross-App Integration

The LOA Auth Platform is consumed by:

```
LOA Consult Platform ──JWT──► LOA Auth Platform
LOA Cert Platform ──JWT──► LOA Auth Platform
```

Both apps validate JWT tokens locally using a shared HMAC-SHA256 secret.

No session sharing. Fully stateless.

---

# 12. Future Evolution

The LOA Auth Platform may evolve to support:

- multi-factor authentication
- OAuth2 / SSO integration
- LDAP / Active Directory federation
- API key management
- session management dashboard
- brute-force protection
- IP whitelisting

Future additions should continue to represent identity and authentication concerns.

---

# 13. Anti-Patterns

The following are architectural violations.

## Business Logic Ownership

```
LOA Auth Platform

manages appointments
```

Appointment management belongs to the Consultation Business Context.

---

## Token Validation via HTTP

```
Consult App

calls Auth API on every request
```

JWT tokens are validated locally using the shared secret. No HTTP call per request.

---

## Shared Database

```
Consult App

reads Auth database directly
```

Each app has its own database. Cross-app data access is via API only.

---

# 14. Guiding Principle

The LOA Auth Platform is the single source of truth for identity.

It defines:

- who can authenticate
- how authentication works
- what tokens are issued
- when tokens expire

It does not define:

- what users do after authentication
- business workflows
- domain-specific logic

Those responsibilities belong to Business Contexts and Product Assemblies.

---

# 15. Testing

## Running Tests

### Prerequisites

- PHP 8.3+
- Composer

### Local (SQLite in-memory)

```bash
cd assemblies/loa-auth-platform
composer install
php vendor/bin/phpunit
```

### Docker

```bash
cd assemblies/loa-auth-platform

# Start the full stack
docker compose up -d

# Run all tests inside the app container
docker compose exec auth-app php vendor/bin/phpunit

# Run a specific test file
docker compose exec auth-app php vendor/bin/phpunit tests/Feature/Api/PermissionPolicy/ClaimPolicyMiddlewareTest.php

# Stop the stack
docker compose down
```

Tests use an in-memory SQLite database (configured in `phpunit.xml.dist`). No database setup is needed.
