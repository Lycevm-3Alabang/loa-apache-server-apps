# API Documentation Service

## Purpose

Provide OpenAPI 3.1 specification and interactive Swagger UI for all LOA platform APIs.

## Status

**Final**

## Scope

This service covers the auth platform API (`auth.loa.edu.ph`). Consult and Cert platforms will be added in later phases.

---

## Technology

| Component | Choice | Version |
|-----------|--------|---------|
| Package | `darkaonline/l5-swagger` | ^9.0 |
| Spec format | OpenAPI 3.1 | |
| UI | Swagger UI (bundled) | |
| Hosting | `/api/docs` (UI) + `/api/docs.json` (spec) |

---

## Endpoints to Document

### Auth API (`auth.loa.edu.ph`)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/register` | Public | Create new user |
| POST | `/api/login` | Public | Authenticate, returns tokens |
| POST | `/api/refresh` | Public | Rotate refresh token |
| POST | `/api/logout` | Bearer | Revoke refresh token |
| GET | `/api/me` | Bearer | Current user profile |
| PUT | `/api/password` | Bearer | Change password |
| POST | `/api/forgot-password` | Public | Request reset link |
| POST | `/api/reset-password` | Public | Reset with token |
| GET | `/api/verify` | Bearer | Validate access token |
| GET | `/api/users` | Bearer + `users.view` | List users (admin) |
| PATCH | `/api/users/{id}/status` | Bearer + `users.manage` | Enable/disable user |
| GET | `/api/docs` | Public | Swagger UI |
| GET | `/api/docs.json` | Public | OpenAPI JSON spec |

---

## Spec Structure

### Info

```yaml
openapi: 3.1.0
info:
  title: LOA Auth API
  version: 1.0.0
  description: JWT authentication and user management for LOA platform
  contact:
    name: LOA Dev Team
```

### Security Schemes

```yaml
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: Access token from /api/login or /api/refresh
```

### Schemas

**User**
```yaml
User:
  type: object
  properties:
    id:
      type: string
      format: uuid
    email:
      type: string
      format: email
    name:
      type: string
    status:
      type: string
      enum: [active, locked, disabled]
    created_at:
      type: string
      format: date-time
```

**TokenPair**
```yaml
TokenPair:
  type: object
  properties:
    access_token:
      type: string
    refresh_token:
      type: string
    token_type:
      type: string
      example: Bearer
    expires_in:
      type: integer
      description: Access token TTL in seconds
```

**Error**
```yaml
Error:
  type: object
  properties:
    message:
      type: string
```

---

## Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created (register) |
| 204 | No content (logout) |
| 400 | Bad request / validation error |
| 401 | Unauthorized (invalid/missing token) |
| 403 | Forbidden (disabled account / insufficient permissions) |
| 404 | Not found |
| 409 | Conflict (email already registered) |
| 422 | Validation failed |
| 423 | Account locked (too many attempts) |
| 500 | Server error |

---

## Implementation Plan

### Step 1: Install package
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### Step 2: Configure `config/l5-swagger.php`
- Set `default` to `auth`
- Set `paths` for docs, assets, routes
- Enable UI

### Step 3: Add OpenAPI annotations to controllers

Annotate each controller method with:
- `@OA\Get`, `@OA\Post`, `@OA\Patch`, `@OA\Put`
- `@OA\RequestBody` for input schemas
- `@OA\Response` for each status code
- `@OA\Security` for authenticated endpoints

### Step 4: Generate spec
```bash
php artisan l5-swagger:generate
```

### Step 5: Register route
Add `/api/docs` to `routes/api.php` pointing to Swagger UI.

---

## Anti-Patterns

- Do NOT document internal implementation details
- Do NOT expose server internals in error responses
- Do NOT skip security scheme documentation
- Do NOT use spec as source of truth — controllers are source of truth; spec is generated

---

## Open Questions

- None pending
