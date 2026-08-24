# LOA Consult Platform — Auth Integration Readiness

**Version:** 1.0
**Status:** Draft
**Audience:** Auth Platform engineers, Consult Platform engineers
**Purpose:** What the Auth Platform must configure/provision for the e-consultation app (Next.js on Vercel) to integrate with loa-auth.

---

# 1. Overview

The e-consultation app (`aces.lyceumalabang.edu.ph`) is a **Next.js 16** application currently using **NextAuth v4** with its own user store (Supabase PostgreSQL). It must migrate to the centralized **loa-auth-platform** for authentication and authorization, following the same pattern as the LOA Cert Platform.

**Key difference from Cert:** The consult app's frontend is Next.js (Vercel), while the cert app's backend is Laravel (cPanel). The consult app keeps its Next.js backend — it does **not** use the planned Laravel `loa-consult-platform` assembly. The Laravel assembly is deferred.

---

# 2. What Must Exist on Auth Platform

## 2.1 Tenant

| Field | Value |
|-------|-------|
| Slug | `loa` |
| Name | Lyceum of Alabang |
| Status | `active` |
| App URL | `https://aces.lyceumalabang.edu.ph` |
| Redirect Origins | `https://aces.lyceumalabang.edu.ph` |

The `loa` tenant is shared with the cert platform. If it already exists (from cert setup), the redirect origins must include the consult app's Vercel domain.

## 2.2 Tenant User Groups

These groups map to the consult app's current roles. Each group is tenant-scoped (`tenant_id = loa`).

| Group Name | Purpose | Notes |
|------------|---------|-------|
| `ADMIN` | System administrators | Full access to all endpoints |
| `DEAN` | Department deans | Read/write on evaluations, read on appointments, full academic structure |
| `FACULTY` | Faculty members | Manage availability, view consultations, view own evaluation results |
| `STUDENT` | Students | Book consultations, submit evaluations |

**Note:** The consult app currently uses pipe-delimited roles (`ADMIN|FACULTY`). After migration, multi-role users belong to multiple groups (e.g., a user in both `ADMIN` and `FACULTY` groups).

## 2.3 Endpoint Catalog

The consult app has **~130 API endpoints**. The Auth Platform must register each as a `TenantAppEndpoint` with the correct `method`, `path`, and `required_level`.

### Level Definitions

| Level | Ordinal | Usage |
|-------|---------|-------|
| `read` | 1 | View/list/download operations |
| `write` | 2 | Create/update/delete non-destructive |
| `admin` | 3 | Destructive/sensitive: reset DB, delete users, invalidate evaluations |

### Public Endpoints (no auth required)

These go in the consult app's public allowlist, not the Auth Platform catalog:

```
POST   /api/auth/callback
POST   /api/auth/refresh
POST   /api/auth/logout
GET    /api/health
GET    /api/semesters/count-active
GET    /api/bug-reports
POST   /api/bug-reports
GET    /api/audit/forbidden
```

### Full Endpoint Catalog

#### Auth & User Profile

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/auth/me` | read |
| GET | `/api/auth/access` | read |
| GET | `/api/auth/users` | read |
| POST | `/api/auth/onboarding` | write |

#### Appointments

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/appointments` | read |
| POST | `/api/appointments` | write |
| GET | `/api/appointments/{id}` | read |
| PATCH | `/api/appointments/{id}` | write |
| POST | `/api/appointments/batch` | write |
| GET | `/api/appointments/faculty-booked` | read |
| PATCH | `/api/appointments/{id}/student-cancel` | write |
| POST | `/api/appointments/{id}/retry-sync` | write |
| POST | `/api/appointments/{id}/files` | write |
| GET | `/api/appointments/{id}/{action}` | read |
| POST | `/api/appointments/{id}/{action}` | write |
| GET | `/api/appointments/slots/{slotId}/teams-link` | read |
| PATCH | `/api/appointments/slots/{slotId}/teams-link` | write |

#### Users (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/users` | read |
| POST | `/api/admin/users` | admin |
| GET | `/api/admin/users/{id}` | read |
| PATCH | `/api/admin/users/{id}` | write |
| DELETE | `/api/admin/users/{id}` | admin |
| GET | `/api/admin/users/{id}/related-data` | read |
| POST | `/api/admin/users/{id}/soft-delete` | admin |
| POST | `/api/admin/users/{id}/restore` | admin |
| POST | `/api/admin/users/bulk-soft-delete` | admin |
| GET | `/api/admin/users/deleted` | read |
| GET | `/api/users/primary` | read |
| GET | `/api/users/attendees` | read |

#### Departments & Academic Structure

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/departments` | read |
| POST | `/api/admin/departments` | write |
| GET | `/api/admin/departments/{id}` | read |
| PATCH | `/api/admin/departments/{id}` | write |
| DELETE | `/api/admin/departments/{id}` | admin |
| GET | `/api/admin/department-courses` | read |
| POST | `/api/admin/department-courses` | write |
| GET | `/api/admin/department-courses/{id}` | read |
| PATCH | `/api/admin/department-courses/{id}` | write |
| DELETE | `/api/admin/department-courses/{id}` | admin |
| GET | `/api/admin/subjects` | read |
| POST | `/api/admin/subjects` | write |
| GET | `/api/admin/subjects/{id}` | read |
| PATCH | `/api/admin/subjects/{id}` | write |
| DELETE | `/api/admin/subjects/{id}` | admin |
| GET | `/api/admin/sections` | read |
| POST | `/api/admin/sections` | write |
| GET | `/api/admin/sections/{id}` | read |
| PATCH | `/api/admin/sections/{id}` | write |
| DELETE | `/api/admin/sections/{id}` | admin |
| POST | `/api/admin/sections/fix-names` | admin |
| GET | `/api/admin/faculty-subjects` | read |
| POST | `/api/admin/faculty-subjects` | write |
| POST | `/api/admin/faculty-subjects/reassign` | write |
| GET | `/api/admin/student-enrollments` | read |
| POST | `/api/admin/student-enrollments` | write |
| GET | `/api/admin/student-enrollments/{id}` | read |
| PATCH | `/api/admin/student-enrollments/{id}` | write |
| DELETE | `/api/admin/student-enrollments/{id}` | admin |

#### Semesters

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/semesters` | read |
| POST | `/api/semesters` | write |
| GET | `/api/semesters/{id}` | read |
| PATCH | `/api/semesters/{id}` | write |
| DELETE | `/api/semesters/{id}` | admin |
| GET | `/api/semesters/{id}/impacts` | read |

#### Evaluations

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/evaluations` | read |
| POST | `/api/evaluations` | write |
| GET | `/api/evaluations/{id}` | read |
| PATCH | `/api/evaluations/{id}` | write |
| GET | `/api/evaluations/pending` | read |
| POST | `/api/evaluations/{id}/submit` | write |
| GET | `/api/evaluations/{id}/ratings` | read |
| POST | `/api/evaluations/{id}/ratings` | write |
| GET | `/api/evaluations/{id}/comments` | read |
| POST | `/api/evaluations/{id}/comments` | write |
| POST | `/api/evaluations/dispute` | write |

#### Evaluation Periods

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/evaluation-periods` | read |
| POST | `/api/evaluation-periods` | write |
| GET | `/api/evaluation-periods/{id}` | read |
| PATCH | `/api/evaluation-periods/{id}` | write |
| DELETE | `/api/evaluation-periods/{id}` | admin |
| POST | `/api/evaluation-periods/{id}/activate` | write |
| POST | `/api/evaluation-periods/{id}/reset` | admin |
| GET | `/api/evaluation-periods/{id}/rubric` | read |
| PUT | `/api/evaluation-periods/{id}/rubric` | write |
| POST | `/api/evaluation-periods/{id}/rubric/copy` | write |
| GET | `/api/evaluation-periods/{id}/rubrics/items` | read |
| POST | `/api/evaluation-periods/{id}/rubrics/items` | write |
| GET | `/api/evaluation-periods/{id}/rubrics/items/{itemId}` | read |
| PATCH | `/api/evaluation-periods/{id}/rubrics/items/{itemId}` | write |
| DELETE | `/api/evaluation-periods/{id}/rubrics/items/{itemId}` | admin |

#### Evaluation Results

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/evaluation-results` | read |
| GET | `/api/admin/evaluation-results/departments/{departmentId}` | read |
| GET | `/api/admin/evaluation-results/departments/{departmentId}/faculty/{facultyId}` | read |
| GET | `/api/admin/evaluation-results/departments/{departmentId}/groups/{facultySubjectId}` | read |
| POST | `/api/admin/evaluation-results/invalidate` | admin |
| PUT | `/api/admin/evaluation-results/visibility` | admin |
| GET | `/api/dean/evaluation-results` | read |
| GET | `/api/dean/evaluation-results/department` | read |
| GET | `/api/dean/evaluation-results/departments/{departmentId}` | read |
| GET | `/api/dean/evaluation-results/departments/{departmentId}/faculty/{facultyId}` | read |
| GET | `/api/dean/evaluation-results/departments/{departmentId}/groups/{facultySubjectId}` | read |
| GET | `/api/dean/evaluation-results/details` | read |
| GET | `/api/faculty/evaluation-results` | read |
| GET | `/api/faculty/evaluation-results/subjects` | read |
| GET | `/api/faculty/evaluation-results/subjects/{facultySubjectId}` | read |

#### Evaluation Comments

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/evaluation-comments` | read |
| POST | `/api/evaluation-comments` | write |

#### Disabled Evaluations (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/evaluations/disabled` | read |
| POST | `/api/admin/evaluations/disabled/restore` | admin |
| GET | `/api/admin/evaluations/{evaluationId}/details` | read |
| POST | `/api/admin/evaluations/{evaluationId}/invalidate` | admin |

#### Rubric Groups

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/rubric-groups` | read |
| POST | `/api/rubric-groups` | write |
| GET | `/api/rubric-groups/{id}` | read |
| PATCH | `/api/rubric-groups/{id}` | write |
| DELETE | `/api/rubric-groups/{id}` | admin |
| POST | `/api/rubric-groups/{id}/duplicate` | write |
| GET | `/api/rubric-groups/{id}/items` | read |
| POST | `/api/rubric-groups/{id}/items` | write |
| GET | `/api/rubric-groups/{id}/items/{itemId}` | read |
| PATCH | `/api/rubric-groups/{id}/items/{itemId}` | write |
| DELETE | `/api/rubric-groups/{id}/items/{itemId}` | admin |
| POST | `/api/rubric-groups/{id}/snapshot` | write |
| GET | `/api/rubric-groups/{id}/categories` | read |
| POST | `/api/rubric-groups/{id}/categories` | write |

#### Import (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| POST | `/api/import/preview` | admin |
| GET | `/api/import/users/reference` | read |
| GET | `/api/import/departments-courses/reference` | read |
| POST | `/api/import/departments-courses` | admin |
| GET | `/api/import/faculties/reference` | read |
| POST | `/api/import/faculties` | admin |
| GET | `/api/import/students/reference` | read |
| POST | `/api/import/students` | admin |
| GET | `/api/import/subjects/reference` | read |
| GET | `/api/import/sections/reference` | read |

#### Availability Rules

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/availability-rules` | read |
| POST | `/api/availability-rules` | write |

#### Data & Audit (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/audit-logs` | read |
| POST | `/api/admin/data/delete-students` | admin |
| POST | `/api/admin/data/reset-db` | admin |
| POST | `/api/admin/data/export-consultations` | admin |
| GET | `/api/data/evaluation-mappings` | read |

#### Access Config (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/access-config` | read |
| POST | `/api/admin/access-config` | admin |
| GET | `/api/admin/access-config/export` | admin |
| POST | `/api/admin/access-config/import` | admin |

#### User Permissions (Admin)

| Method | Path | Required Level |
|--------|------|---------------|
| GET | `/api/admin/user-permissions/paths` | read |
| GET | `/api/admin/user-permissions/{userId}` | read |
| PUT | `/api/admin/user-permissions/{userId}` | admin |

#### Student Evaluations

| Method | Path | Required Level |
|--------|------|---------------|
| POST | `/api/student/evaluations/bootstrap` | write |

## 2.4 Default Group Grants

Recommended default grants per group. These can be adjusted per deployment.

### ADMIN

All endpoints: `admin` level. Full access.

### DEAN

| Domain | Level |
|--------|-------|
| `/api/admin/departments/*` | `read` |
| `/api/admin/department-courses/*` | `read` |
| `/api/admin/subjects/*` | `read` |
| `/api/admin/sections/*` | `read` |
| `/api/admin/faculty-subjects/*` | `read` |
| `/api/admin/student-enrollments/*` | `read` |
| `/api/admin/users` | `read` |
| `/api/admin/evaluation-results/*` | `read` |
| `/api/admin/evaluation-results/visibility` | `write` |
| `/api/admin/evaluations/disabled/*` | `read` |
| `/api/admin/audit-logs` | `read` |
| `/api/dean/*` | `read` |
| `/api/semesters/*` | `read` |
| `/api/evaluation-periods/*` | `read` |
| `/api/evaluation-periods/{id}/activate` | `write` |
| `/api/rubric-groups/*` | `read` |
| `/api/evaluations/*` | `read` |
| `/api/appointments/*` | `read` |
| `/api/users/*` | `read` |
| `/api/import/*` | `read` |
| `/api/data/*` | `read` |
| `/api/admin/access-config` | `read` |

### FACULTY

| Domain | Level |
|--------|-------|
| `/api/appointments` | `read` |
| `/api/appointments/{id}` | `read` |
| `/api/appointments/{id}/{action}` | `write` |
| `/api/appointments/faculty-booked` | `read` |
| `/api/appointments/slots/*/teams-link` | `write` |
| `/api/availability-rules` | `write` |
| `/api/faculty/*` | `read` |
| `/api/evaluations/{id}/ratings` | `read` |
| `/api/evaluations/{id}/comments` | `read` |
| `/api/evaluation-periods/{id}/rubric` | `read` |
| `/api/rubric-groups` | `read` |
| `/api/rubric-groups/{id}` | `read` |
| `/api/rubric-groups/{id}/items` | `read` |
| `/api/users/primary` | `read` |
| `/api/users/attendees` | `read` |
| `/api/semesters` | `read` |
| `/api/evaluation-periods` | `read` |

### STUDENT

| Domain | Level |
|--------|-------|
| `/api/appointments` | `write` |
| `/api/appointments/{id}` | `read` |
| `/api/appointments/{id}/student-cancel` | `write` |
| `/api/appointments/batch` | `write` |
| `/api/evaluations` | `write` |
| `/api/evaluations/{id}` | `read` |
| `/api/evaluations/{id}/submit` | `write` |
| `/api/evaluations/{id}/ratings` | `write` |
| `/api/evaluations/{id}/comments` | `write` |
| `/api/evaluations/pending` | `read` |
| `/api/evaluations/dispute` | `write` |
| `/api/student/*` | `write` |
| `/api/evaluation-periods/{id}/rubric` | `read` |
| `/api/rubric-groups` | `read` |
| `/api/rubric-groups/{id}/items` | `read` |
| `/api/users/primary` | `read` |
| `/api/semesters` | `read` |
| `/api/evaluation-periods` | `read` |

---

# 3. SSO Flow

The consult app uses the same SSO redirect pattern as the cert app.

```
1. User visits aces.lyceumalabang.edu.ph
2. No valid session → Frontend redirects to:
   https://auth.lyceumalabang.edu.ph/sso/login?redirect=https://aces.lyceumalabang.edu.ph
3. User authenticates on Auth Platform
4. Auth Platform encrypts JWT payload (AES-256-GCM)
5. Auth Platform redirects to:
   https://aces.lyceumalabang.edu.ph#payload=<encrypted_base64url>
6. Frontend JS extracts fragment
7. Frontend calls POST /api/auth/callback with encrypted payload
8. Backend decrypts, validates JWT locally, returns session tokens
9. Refresh token stored as httpOnly cookie (loa_connect_refresh)
```

---

# 4. Consult App Endpoints to Implement

The consult app (Next.js) must implement 3 auth endpoints, following the cert app pattern:

### `POST /api/auth/callback`

Decrypts SSO payload, validates JWT locally, sets httpOnly refresh cookie.

| Field | Value |
|-------|-------|
| Rate limit | 10 requests/minute |
| Request | `{ "payload": "<encrypted_string>" }` |
| Response 200 | `{ "access_token": "...", "user": { "id", "email", "name", "groups", "permissions" } }` |

### `POST /api/auth/refresh`

Reads refresh token from httpOnly cookie, proxies to `POST {AUTH_BASE_URL}/api/v1/auth/refresh`.

| Field | Value |
|-------|-------|
| Rate limit | 10 requests/minute |
| Request | Empty (reads cookie) |
| Response 200 | `{ "access_token": "..." }` |
| Response 204 | Cleared cookie |

### `POST /api/auth/logout`

Clears refresh cookie, proxies to `POST {AUTH_BASE_URL}/api/v1/auth/logout`.

| Field | Value |
|-------|-------|
| Request | Empty |
| Response 204 | Always |

---

# 5. Shared Secrets

Both apps must share:

| Secret | Purpose | Format |
|--------|---------|--------|
| `JWT_SECRET` | HMAC-SHA256 for JWT signing/validation | Same value on Auth + Consult |
| `ENCRYPTION_KEY` | AES-256-GCM for SSO payload encryption | 64 hex chars or `base64:` prefix |

---

# 6. Environment Variables (Consult App)

```env
# Auth integration
JWT_SECRET=<shared_with_loa_auth_platform>
AUTH_BASE_URL=https://auth.lyceumalabang.edu.ph
ENCRYPTION_KEY=<shared_with_loa_auth_platform>
TENANT_SLUG=loa
REFRESH_COOKIE=loa_connect_refresh
REFRESH_COOKIE_TTL=10080
```

---

# 7. Auth Platform Configuration Steps

Before the consult app can integrate, the Auth Platform must:

1. **Create tenant** (or update existing `loa` tenant):
   - Add `https://aces.lyceumalabang.edu.ph` to `redirect_origins`

2. **Create tenant user groups:**
   - `ADMIN`, `DEAN`, `FACULTY`, `STUDENT` (all scoped to `loa` tenant)

3. **Seed endpoint catalog:**
   - Register ~130 endpoints from §2.3 as `TenantAppEndpoint` records
   - Each entry: `method`, `path`, `required_level`, `tenant_id`

4. **Configure group grants:**
   - Assign `TenantEndpointGrant` records per group per endpoint (§2.4)
   - This is the bulk of the setup — ~500+ grant records

5. **Configure SSO redirect:**
   - Ensure `/sso/login` accepts `redirect=https://aces.lyceumalabang.edu.ph`
   - Ensure the `loa` tenant's `redirect_origins` includes the consult domain

6. **Share secrets:**
   - Provide `JWT_SECRET` and `ENCRYPTION_KEY` to the consult app team

---

# 8. Differences from Cert Platform

| Aspect | Cert Platform | Consult App |
|--------|---------------|-------------|
| Backend framework | Laravel 12 (PHP) | Next.js 16 (TypeScript) |
| Deployment | cPanel (PHP-FPM) | Vercel (Serverless) |
| JWT validation | Local (shared secret) | Local (shared secret) |
| Refresh cookie | `loa_cert_refresh` | `loa_connect_refresh` |
| Endpoint catalog size | 48 endpoints | ~130 endpoints |
| RBAC | EndpointPolicyMiddleware (Laravel) | EndpointPolicyMiddleware (Next.js middleware) |
| User store | Separate DB (`loa_cert`) | Separate DB (Supabase PostgreSQL) |

---

# 9. User Data Sync

The consult app maintains its own `users` table in Supabase for application-specific data (department assignments, course, employee number, etc.). After SSO login:

1. The consult app receives JWT claims (`sub`, `email`, `name`, `groups`, `permissions`)
2. It upserts into its local `users` table by `email`
3. Application-specific fields (department, course, employeeNo) are managed locally
4. Auth-specific fields (password, tokenVersion) are no longer needed

**Fields to remove from consult `users` table:**
- `passwordHash` (managed by loa-auth)
- `tokenVersion` (managed by loa-auth refresh tokens)
- `hasLoggedInBefore` (managed by loa-auth)

**Fields to keep:**
- `id`, `name`, `email` (synced from JWT)
- `departmentId`, `course`, `employeeNo` (application-specific)
- `isDisabled` (consult-specific flag, separate from loa-auth status)
- `createdAt`, `deletedAt`

---

# 10. Role-to-Group Migration

Current consult app roles (pipe-delimited on `users.role`):

| Current Role | loa-auth Group |
|-------------|---------------|
| `ADMIN` | `ADMIN` |
| `DEAN` | `DEAN` |
| `FACULTY` | `FACULTY` |
| `STUDENT` | `STUDENT` |
| `GUEST` | (removed — no access) |

Multi-role users (e.g., `ADMIN|FACULTY`) become members of multiple groups.

---

# 11. What Gets Removed from Consult App

| Component | Reason |
|-----------|--------|
| NextAuth (`lib/auth.ts`) | Replaced by loa-auth SSO |
| `app/api/auth/[...nextauth]` | No longer needed |
| `app/api/auth/activate` | Managed by loa-auth |
| `app/api/auth/forgot-password` | Managed by loa-auth |
| `app/api/auth/change-password` | Managed by loa-auth |
| `lib/access.ts` (RBAC) | Replaced by endpoint grants |
| `lib/default-access.ts` | Replaced by endpoint grants |
| `group_access` table | Replaced by `TenantEndpointGrant` |
| `user_permissions` table | Replaced by `TenantEndpointOverride` |
| `role` / `userrole` tables | Replaced by loa-auth `UserGroup` |
| `password_reset_tokens` table | Managed by loa-auth |
| `SessionProvider` | Replaced by JWT context |
| `useSession()` / `signIn()` / `signOut()` | Replaced by JWT context |
| `bcryptjs` dependency | Passwords managed by loa-auth |
| `AUTH_SECRET` / `NEXTAUTH_SECRET` | Replaced by `JWT_SECRET` |

---

# 12. Testing Checklist

After setup, verify:

- [ ] SSO redirect to auth platform works
- [ ] Encrypted payload decryption succeeds
- [ ] JWT validation with shared secret works
- [ ] Tenant slug validation works
- [ ] Refresh token rotation works
- [ ] Logout clears cookie and revokes token
- [ ] ADMIN group has full access
- [ ] DEAN group can read evaluations, cannot reset DB
- [ ] FACULTY can manage availability, view consultations
- [ ] STUDENT can book consultations, submit evaluations
- [ ] Closed-by-default: unknown endpoints return 403
- [ ] User override grants take precedence over group grants

---

# 13. Cross-References (e-consultation specs)

The e-consultation repo contains aligned specs in `D:\loa\e-consultation\specs/`:

| Consult Spec | This Doc | Purpose |
|-------------|----------|---------|
| `specs/auth-integration.md` | §3-5 | SSO flow, JWT validation, shared secrets, user data sync |
| `specs/endpoint-catalog.md` | §2.3-2.4 | Full endpoint catalog with required levels and group grants |
| `specs/migration-checklist.md` | §6-7 | Step-by-step tasks for each side, verification checklist |

**Rule:** Both repos must keep these specs in sync. If this doc changes, the corresponding consult spec must be updated, and vice versa.

---

## Document Control

- **Status:** Draft v1.0
- **Created:** 2026-08-24
- **Source:** e-consultation app analysis (`D:\loa\e-consultation`)
- **Cross-references:** `D:\loa\e-consultation\specs/` (3 spec files)
- **Supersedes:** None
