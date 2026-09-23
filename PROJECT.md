# LOA Platform Revamp
## Project Tracker

**Started:** 2026-07-30
**Last Updated:** 2026-09-23
**Target:** cPanel (PHP 8.3+ / MySQL 8 / Laravel 12)

---

# ⛔ MANDATORY: Specs Before Code

**No code may be written, modified, or refactored until the relevant spec `.md` file exists and is Final.**

- Missing spec → write it first, or ask
- Draft spec → complete it first
- Final spec → code exactly to it

This rule is enforced in `AGENTS.md` (sole entry; detail in `principles.md` / `platform.md`). Violations are failures.

---

# Architecture Status

## Identity Kernel (v2.0)

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Kernel | Identity | `kernels/identity/README.md` | ✅ v3.0 Draft (tenancy layer) |
| Kernel | Tenancy | `kernels/identity/tenancy.md` | ✅ Final — implemented |
| Kernel | User | `kernels/identity/entities/user.md` | ✅ Draft |
| Kernel | UserGroup | `kernels/identity/entities/user-group.md` | ✅ Draft |
| Kernel | Permission | `kernels/identity/entities/permission.md` | ✅ Draft |
| Kernel | LoginAttempt | `kernels/identity/entities/login-attempt.md` | ✅ Draft |
| Kernel | PasswordResetToken | `kernels/identity/entities/password-reset-token.md` | ✅ Draft |
| Kernel | RefreshToken | `kernels/identity/entities/refresh-token.md` | ✅ Final |
| Kernel | Contracts | `kernels/identity/contracts/interfaces.md` | ✅ Draft |
| Kernel | Events (15) | `kernels/identity/events/` | ✅ Draft |
| Kernel | Business Rules (8) | `kernels/identity/rules/` | ✅ Draft |

## Education Domain

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Domain | Education Pack | `domains/education/README.md` | ✅ Draft |
| Domain | Department | `domains/education/department.md` | ✅ Draft |
| Domain | Course | `domains/education/course.md` | ✅ Draft |
| Domain | Semester | `domains/education/semester.md` | ✅ Draft |
| Domain | Subject | `domains/education/subject.md` | ✅ Draft |
| Domain | Section | `domains/education/section.md` | ✅ Draft |
| Domain | Enrollment | `domains/education/enrollment.md` | ✅ Draft |

## Business Contexts

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Context | Consultation | `business-contexts/consultation/README.md` | ✅ Draft |
| Context | Appointment | `business-contexts/consultation/appointment.md` | ✅ Draft |
| Context | Availability | `business-contexts/consultation/availability.md` | ✅ Draft |
| Context | Evaluation | `business-contexts/evaluation/README.md` | ✅ Draft |
| Context | Evaluation (aggregate) | `business-contexts/evaluation/evaluation.md` | ✅ Draft |
| Context | Rubric | `business-contexts/evaluation/rubric.md` | ✅ Draft |
| Context | Certificate | `business-contexts/certificate/README.md` | ✅ Draft |
| Context | Certificate (aggregate) | `business-contexts/certificate/certificate.md` | ✅ Draft |
| Context | Certificate Template | `business-contexts/certificate/template.md` | ✅ Draft |
| Context | Event | `business-contexts/certificate/event.md` | ✅ Final |
| Context | Event Attendee | `business-contexts/certificate/event-attendee.md` | ✅ Final |

## Product Assemblies

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Service | QR Code | `services/qrcode/README.md` | ✅ Draft (spec behind code: `QrCodeService::toDataUri` implemented in cert) |
| Pattern (not a service) | CORS | `platform.md` §15b + per-assembly `config/cors.php` | ✅ Implemented (no service spec — config + gotcha, not a reusable capability) |
| Pattern (not a service) | API Documentation | Controllers' `#[OA]` attributes + `l5-swagger` | ✅ Implemented (no service spec — doc generation, not a capability) |
| Service | Database Seeder | `assemblies/loa-auth-platform/database/seeders/seeder-spec.md` | ✅ Final |
| Assembly | LOA Auth Platform | `assemblies/loa-auth-platform/README.md` | ✅ Scaffolded |
| Assembly | LOA Auth Web UI | `assemblies/loa-auth-platform/web-ui.md` | ✅ Final (v1.2 — destination resolution) — implemented |
| Assembly | LOA Admin Dashboard | `assemblies/loa-auth-platform/admin-dashboard.md` | ✅ Final (v1 + v2 implemented) |
| Assembly | Access Config Import/Export | `assemblies/loa-auth-platform/access-config-import-export.md` | ✅ Final v1.0 — implemented |
| Assembly | LOA Consult Platform | `assemblies/loa-consult-platform/README.md` | ✅ Scaffolded (2026-09-18); data-model Final v1.3 status-split, 3 endpoint modules Final v1.0, docker-compose-spec Final v1.0 |
| Assembly | LOA Cert Platform | `assemblies/loa-cert-platform/README.md` | ✅ Draft |

---

# Implementation Status

## Phase 1: Auth Service

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ✅ Done | Laravel 12 skeleton, cPanel-ready |
| Identity Kernel spec | ✅ Done | User, UserGroup, Permission, JWT, LoginAttempt, PasswordResetToken |
| User model + migrations | ✅ Done | UUID PK, status, failed_attempts, locked_until |
| UserGroup model + migrations | ✅ Done | Flexible grouping (replaces Role) |
| Permission model + migrations | ✅ Done | Fine-grained, endpoint-mapped |
| UserGroupPermission pivot + migration | ✅ Done | UserGroup → Permission mapping |
| UserUserGroup pivot + migration | ✅ Done | User → UserGroup membership |
| UserPermission pivot + migration | ✅ Done | Per-user override (grant/deny) |
| LoginAttempt model + migration | ✅ Done | Brute-force tracking |
| PasswordResetToken model + migration | ✅ Done | Hashed tokens, 60min expiry |
| JWT service (pure PHP) | ✅ Done | HMAC-SHA256, access + refresh tokens |
| IdentityService | ✅ Done | Register, login, refresh, logout, getUser, updatePassword, password reset |
| AuthorizationService | ✅ Done | Group-based permission checks, user overrides |
| JWT middleware | ✅ Done | `jwt.auth` — validates access token, resolves user |
| Permission middleware | ✅ Done | `jwt.permission:{key}` — checks token claims |
| JWT claims | ✅ Done | Token includes `groups` + `permissions` |
| Department model + migrations | ⬜ Not started | Moved to Education Domain |
| Register endpoint | ✅ Done | Validates password policy, 201 response |
| Login endpoint | ✅ Done | Returns access + refresh tokens, 423 on lock, 403 on disabled |
| Refresh endpoint | ✅ Done | Rotates token pair |
| Logout endpoint | ✅ Done | 204 response |
| Password reset flow | ✅ Done | Forgot + reset endpoints, hashed tokens |
| User profile endpoint | ✅ Done | `GET /auth/me` + `PUT /auth/password` |
| User list endpoint (admin) | ✅ Done | Requires `users.view` permission |
| Verify endpoint | ✅ Done | Public token validation for consumers |
| Refresh token revocation | ✅ Done | Model + migration + IdentityService wiring (issue on login, rotate on refresh, revoke on logout/password change/reset/lock) |
| Account disable endpoint | ✅ Done | PATCH /users/{id}/status, users.manage permission, revokes all refresh tokens |
| Refresh token pruning | ✅ Done | PruneRefreshTokens command + daily schedule via routes/console.php |
| Local Docker dev environment | ✅ Done | docker-compose.yml, php:8.3-fpm, nginx, mysql, mailpit, scheduler |
| PHP 8.3 + Laravel 12 upgrade | ✅ Done | L11 EOL (security advisories Jul 2026), L12 API-compatible |
| CORS configuration | ✅ Done | `config/cors.php` per `services/cors/README.md`, LOA subdomains + env override |
| Auth Web UI spec | ✅ Done | `assemblies/loa-auth-platform/web-ui.md` — login redirect, forgot/change password, email |
| OpenAPI/Swagger UI | ✅ Done | `darkaonline/l5-swagger`, PHP 8 attributes, 12 endpoints, `/api/docs` |
| Database seeder | ✅ Done | `database/seeders/database.sql` rebuilt from migration schema, verified import parity (admin + loa-auth-admin group) |
| Login page (web) | ✅ Done | Blade form; post-login redirect via fragment per web-ui.md |
| Forgot password page (web) | ✅ Done | Email form + reset link email |
| Change password page (web) | ✅ Done | Shared `/reset-password` form, token-validated |
| SMTP/mail config + email templates | ✅ Done | MAIL_* env, reset-password + change-password Blade templates |
| Tenants (v3.0) | ✅ Done | `tenants` + `user_tenants` tables (migrations 000011–000012), Tenant model, TenantService CRUD + redirect-origin resolution |
| Tenant-scoped groups/grants | ✅ Done | Migrations 000013–000015, `user_groups.tenant_id` + scope-unique pivots, `AuthorizationService` tenant scoping |
| Tenant JWT claims + middleware | ✅ Done | `tenant {id, slug}` claim, `jwt.tenant` middleware, suspended tenant rejection (login + refresh + verify) |
| Login destination resolution | ✅ Done | Admin → session dashboard; non-admin + valid redirect → tenant fragment redirect; non-admin direct → reject + revoke tokens |
| Admin dashboard v1 | ✅ Done | `WebAdminController`, `web.admin` middleware, `/admin/users` (list, search, enable/disable), self-disable guard, admin layout |
| Admin dashboard v2 (tenant mgmt) | ✅ Done | Tenant CRUD, groups, per-group permissions, members (add/remove), suspend/activate; `admin-dashboard.md` promoted to Final |
| Admin session login/logout | ✅ Done | `WebAuthController` admin flow, session auth, logout route, dashboard access control |
| Admin web UI spec | ✅ Done | `assemblies/loa-auth-platform/admin-dashboard.md` — v1 + v2 implemented, Final |
| Admin create user (v3) | ✅ Done | `WebAdminController::create/store`, `admin/users/create.blade.php`, routes — spec Final in admin-dashboard.md §9 |
| Group/permission management (v4) | ✅ Done | `GroupController`, `UserGroupController`, 3 Blade views, API + admin routes — spec in group-permission-management.md |
| Access Config Import/Export | ✅ Done | `AccessConfigController`, web + API routes, import Blade view, 3 factories, 29 tests — spec Final v1.0 |
| Deploy to auth.lyceumalabang.edu.ph | ⬜ Not started | |

### Bugs Found & Fixed (2026-07-31)

These pre-existing bugs were discovered during Docker testing and fixed in this session:

| Bug | Location | Fix |
|-----|----------|-----|
| CORS config nesting | `config/cors.php` | Removed double-wrapped `array_values(array_filter([...]))` — was producing array-of-arrays instead of flat array |
| No UUID generation | User, RefreshToken, PasswordResetToken, LoginAttempt models | Added `boot()` + `creating` listener to auto-generate `Str::uuid()` on create |
| Wrong table name in pluck | `AuthorizationService::getPermissions()` | `pluck('permission.key')` → `pluck('permissions.key')` (table is plural) |
| Disabled users could log in | `IdentityService::login()` | Added `status === 'disabled'` check before password validation (per `account-status.md` rule) |
| Login endpoint no 403 | `AuthController::login()` | Added catch for "Account is disabled" → 403 response |

## Phase 2: Consult App

| Task | Status | Notes |
|------|--------|-------|
| Consult endpoint inventory (scan-only savepoint) | ✅ Done | 2026-09-18: 112 route files scanned (~142 combos; 118 migrating to Laravel); drift vs `endpoint-catalog.md` recorded; frontend untouched |
| Consult modular spec plan | ✅ Done | 2026-09-18: 10-file savepoint (conventions, 5 endpoint modules, auth-integration, data-model, runbooks); reports deferred to Phase E |
| Laravel project scaffold | ✅ Done | 2026-09-18: Laravel 12.12 via composer, swagger + phpunit 12, PHP 8.3 pinned, HealthTest green in Docker |
| `api-endpoints.md` spec | ✅ Final v1.0 | Root spec: conventions, 118-route summary, levels, design decisions |
| `auth-integration.md` spec | ✅ Final v1.4 | SSO contract, cookie, middleware, 14-item port inventory, students/employees first-class; §11 Steps 1–7 landed (config · services · JwtMiddleware · catalog 118+5 · EndpointPolicy · auth trio · gate routes + tests) — suite green 2026-09-22, port COMPLETE |
| `data-model.md` spec | ✅ Final v1.3 | Shape contract (M17/M19/M21/M26/M27/M30/M31); §3 Status gates implementability (6 Implemented / 3 Delta pending / 17 Specified—not migrated); §7 baseline delta |
| `endpoints-academic.md` spec | ✅ Final v1.0 | 14 handlers; parent levels corrected (POST/PATCH + impacts → admin) |
| `endpoints-appointments.md` spec | ✅ Final v1.0 | 12 combos: booking model, batch, action dispatch |
| `endpoints-evaluations.md` spec | ✅ Final v1.0 | 24 handlers: lifecycle/masking, periods, rubrics, results, dispute mail |
| `endpoints-admin-import.md` spec | ✅ Final v1.0 | Phase D: Auth-owned users (aces-*, link reads only), domain imports, double-guard destructives, proxy decision open |
| `url-flattening.md` spec | ✅ Final v1.1 | Flat URL scheme (113+5, single scoped results, in-place v1 rename; F2 green) |
| URL flattening (F1+F2) | ✅ Done 2026-09-23 | Flat routes + scoped results + catalog/JSON regen + ResultsTest scoping; suite green (user) |
| `test-suite.md` spec | ✅ Final v1.0 | Test contract for auth-layer + B/C (CON/DEC/ACC/D); MySQL `loa_consult_test`, JWT helper, user-run sequential |
| `docker-compose-spec.md` spec | ✅ Final v1.0 | Root-stack `consult-*` blocks port 9002, `loa_consult` init |
| `LOCAL-DEV-RUNBOOK.md` spec | ✅ Draft v0.1 | Shared root-stack pattern, wiring gate + checklist |
| `consult-readiness.md` spec | ✅ Final v1.4 | Provisioning checklist (aces-admin/dean/faculty/user + Auth↔students/employees link, 118+5 pointer) |
| First migration(s) + baseline deltas | ✅ Done 2026-09-22 | `000001-000010` academic + `000011` academic baseline + `000012` appointment-family + `000013` section-link + `000014` evaluation tables; per data-model Final v1.3 §3/§7 |
| JWT middleware | ✅ Step 3 done | Cert port: `consult-platform.tenant_slug=loa`, `consult_user`; Unit tests green. **Gated in Step 7** |
| Permission middleware | ✅ Step 5 done | Cert port verbatim (`consult-endpoints` re-point only, `jwt_claims` confirmed); Unit tests green (public/403/level/catalog-count). **Gates all domain routes since Step 7** |
| Route gating | ✅ Step 7 done | `auth/*` public + `throttle:10,1` on callback/refresh; health + count-active public; semesters/admin under `jwt.auth`+`jwt.endpoint`; RouteGatingTest green 2026-09-22 |
| Appointment model + migrations | ✅ Done 2026-09-22 | B2 `000012`: appointments table + thin model; slice B COMPLETE green pasted |
| TimeSlot model + migrations | ✅ Done 2026-09-22 | B2 `000012`: slots table + thin model; slice B COMPLETE green pasted |
| Attendee model + migrations | ✅ Done 2026-09-22 | B2 `000012`: attendees table + thin model; slice B COMPLETE green pasted |
| AvailabilityRule model + migrations | ✅ Done 2026-09-22 | B2 `000012` table + B3 `AvailabilityRuleController` + `AvailabilityTest` 10/10 green pasted |
| Appointment CRUD endpoints | ✅ Done 2026-09-22 | B4 `AppointmentController` 10 routes + `AppointmentTest` 12/12 green pasted |
| Batch appointment creation | ✅ Done 2026-09-22 | B4 batch path in `AppointmentController`; green pasted with B4 |
| Conflict detection service | ✅ Done 2026-09-22 | B4 legacy-verified conflicts/slots/rules; green pasted with B4 |
| Accept/decline/complete endpoints | ✅ Done 2026-09-22 | B4 action dispatch; green pasted with B4 |
| Semester model + migrations | ✅ Done 2026-09-22 | `Semester.php` + `000007` + `SemesterController` gated (`jwt.auth`+`jwt.endpoint`) + B5 per-semester impacts; slice B COMPLETE |
| Rubric models + migrations | ✅ Done 2026-09-22 | C1 `000014` rubric tables + C2 periods/rubric-groups endpoints + `PeriodsRubricsTest` green pasted |
| Evaluation model + migrations | ✅ Done 2026-09-22 | C1 `000014` 10 evaluation tables + 10 thin models + `EvaluationTablesTest` 4/4 green pasted |
| Evaluation endpoints | ✅ Done 2026-09-22 | C3 evaluations lifecycle + `EvaluationFlowTest` green pasted |
| Evaluation result computation | ✅ Done 2026-09-22 | C4 ResultsService + 20 routes + `ResultsTest` green pasted; slice C COMPLETE |
| Subject/Section/Enrollment models | ✅ Done 2026-09-22 | 9 models + `000001-000009` + B1 `000011` baseline delta + B5 hardening; slice B COMPLETE |
| Academic infrastructure endpoints | ✅ Done 2026-09-22 | `AcademicController` + gated `admin/*` routes + B5 hardening (`AcademicHardeningTest`) + `000013`; slice B COMPLETE |
| User link reads (Phase D) | ✅ Done 2026-09-23 | `UserLinkController` (primary/attendees/related-data via email link) + 3 gated reads + `UserLinkTest` 8/8 green (user); writes absent per DEC-1 |
| Import domain (Phase D) | ✅ Done 2026-09-23 | `ImportController` (preview + references + idempotent writes) + 10 gated routes + `ImportTest` 7/7 green (user); users/reference absent per DEC-2 |
| Data/audit (Phase D) | ✅ Done 2026-09-23 | `DataController` (delete-students/reset-db/export/mappings) + 4 gated routes + `DataAuditTest` 8/8 green (user); per-resource DELETE on owners; audit-logs absent by gate |
| Report endpoints (7 types) | ⬜ Deferred to Phase E | No REST routes exist; Server Components compute directly |
| CSV import | ⬜ Not started | |
| Email notifications | ⬜ Not started | |
| Deploy to aces-api.lyceumalabang.edu.ph | ⬜ Not started | |

## Phase 3: Cert App

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ⬜ Not started | |
| JWT middleware | ⬜ Not started | |
| Permission middleware | ⬜ Not started | Check UserGroup permissions |
| Organization model + migrations | ⬜ Not started | |
| UserMembership model + migrations | ⬜ Not started | |
| Event model + migrations | ⬜ Not started | |
| EventAttendee model + migrations | ⬜ Not started | |
| CertificateTemplate model + migrations | ⬜ Not started | |
| Certificate model + migrations | ⬜ Not started | |
| CertificateSequence model + migrations | ⬜ Not started | |
| Event CRUD endpoints | ⬜ Not started | |
| Attendee management endpoints | ⬜ Not started | |
| CSV import for attendees | ⬜ Not started | |
| Template CRUD endpoints | ⬜ Not started | |
| Certificate issuance endpoint | ⬜ Not started | |
| Bulk issuance endpoint | ⬜ Not started | |
| Certificate number generation | ⬜ Not started | Atomic, database-backed |
| PDF generation | ⬜ Not started | DOMPDF |
| QR code generation | ⬜ Not started | |
| Email with PDF attachment | ⬜ Not started | |
| Public verification endpoint | ⬜ Not started | No auth required |
| Revoke/delete endpoints | ⬜ Not started | |
| Audit trail | ⬜ Not started | |
 | Deploy to cert-api.lyceumalabang.edu.ph | ⬜ Not started | |

## Phase 4: Integration

| Task | Status | Notes |
|------|--------|-------|
| Cross-app JWT validation testing | ⬜ Not started | |
| Auth API → Consult app user lookup | ⬜ Not started | |
| Auth API → Cert app user lookup | ⬜ Not started | |
| API documentation (OpenAPI) | ⬜ Not started | |
| Audit trail consistency | ⬜ Not started | |

---

# App Overview

| App | Subdomain | Database | Framework | Purpose |
|-----|-----------|----------|-----------|---------|
| Auth | auth.lyceumalabang.edu.ph | loa_auth | Laravel 12 | JWT token service, user management |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Laravel 12 | Consultation booking, faculty evaluation |
| Cert API | cert-api.lyceumalabang.edu.ph | loa_cert | Laravel 12 | Certificate issuance, verification |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js 16 | Cert frontend; consumer of Auth + Cert APIs |

---

# Cross-App Communication

```
Consult ──JWT──► Auth (token validation)
Cert    ──JWT──► Auth (token validation)
Consult ──HTTP──► Auth (user lookup)
Cert    ──HTTP──► Auth (user lookup)
```

All JWT validation is local (shared HMAC-SHA256 secret). No HTTP call per request.

---

# File Structure

```
loa-apache-server-apps/
├── PROJECT.md                          # This file
├── AGENTS.md                            # AI agent instructions (sole entry)
├── principles.md                       # SDD+TDD + coding rules (authoritative detail)
├── platform.md                         # LOA architecture + Laravel gotchas
├── dependency-rules.md                 # Dependency matrix
├── kernels/
│   └── identity/                       # Identity Kernel (v2.0)
│       ├── README.md                   # Full spec
│       ├── entities/                   # User, UserGroup, Permission, etc.
│       ├── contracts/                  # Public interfaces
│       ├── events/                     # Domain events
│       └── rules/                      # Business rules
├── domains/
│   └── education/                      # Education Domain Pack
│       ├── README.md                   # Education domain pack
│       ├── department.md               # Academic units
│       ├── course.md
│       ├── semester.md
│       ├── subject.md
│       ├── section.md
│       └── enrollment.md
├── business-contexts/
│   ├── consultation/
│   │   ├── README.md
│   │   ├── appointment.md
│   │   └── availability.md
│   ├── evaluation/
│   │   ├── README.md
│   │   ├── evaluation.md
│   │   └── rubric.md
│   └── certificate/
│       ├── README.md
│       ├── certificate.md
│       └── template.md
├── assemblies/
│   ├── loa-auth-platform/              # Auth app (scaffolded)
│   │   ├── app/Console/Commands/       # PruneRefreshTokens, TestAuth (test only)
│   │   ├── app/Http/Controllers/
│   │   ├── app/Models/                 # User, UserGroup, Permission, RefreshToken, etc.
│   │   ├── app/Services/               # JWTService, IdentityService, AuthorizationService
│   │   ├── database/migrations/        # 9 migrations (incl. refresh_tokens)
│   │   ├── docker/                     # php/Dockerfile, nginx/default.conf
│   │   ├── docker-compose.yml          # Local dev stack (PHP 8.3, nginx, MySQL, mailpit)
│   │   ├── environment.md              # Local + deployed tooling spec
│   │   ├── routes/api.php
│   │   ├── routes/console.php          # Scheduler (refresh-tokens:prune daily)
│   │   ├── config/jwt.php
│   │   └── composer.json
│   ├── loa-consult-platform/README.md  # Consult assembly spec
│   └── loa-cert-platform/README.md     # Cert assembly spec
└── services/                           # Existing template services
```

---

# Decisions Log

| Date | Decision | Reason |
|------|----------|--------|
| 2026-07-30 | PHP + Laravel + MySQL | cPanel hosting constraint |
| 2026-07-30 | Three separate Laravel apps | Isolation, independent deployment |
| 2026-07-30 | Custom JWT (no firebase) | Zero external dependencies |
| 2026-07-30 | Stateless JWT validation | No HTTP call per request |
| 2026-07-30 | Subdomains for each app | auth/cert-api/aces-api *.lyceumalabang.edu.ph (2026-08-05: UIs move to Vercel, APIs keep *.lyceumalabang.edu.ph) |
| 2026-07-30 | Education domain pack | New industry pack for LOA |
| 2026-07-31 | UserGroup model (replaces Role) | Flexible grouping, multi-department support |
| 2026-07-31 | Department in Education Domain | Education-specific, not a canonical kernel |
| 2026-07-31 | Spec-first development | Design before code, catch issues early |
| 2026-07-31 | Specs-before-code is MANDATORY | No code without a Final spec — enforced in AGENTS.md (detail: principles.md / platform.md) |
| 2026-07-31 | Identity Kernel v2.0 | Universal grouping, not role-based |
| 2026-07-31 | Event spec files (15) | Per-event spec under kernels/identity/events/ |
| 2026-07-31 | Business rule spec files (8) | Per-rule spec under kernels/identity/rules/ |
| 2026-07-31 | RefreshToken entity spec | DB-backed refresh tokens (jti hashed, single-use, rotation + revocation) per token-lifecycle.md |
| 2026-07-31 | RefreshToken spec promoted to Final + implemented | Model, migration `2026_07_30_000009`, IdentityService wiring; rotation/revocation per spec |
| 2026-07-31 | Upgrade to Laravel 12 + PHP 8.3 | L11 EOL with security advisories (Jul 2026); L12 API-compatible, requires PHP ^8.3; scaffold code unchanged |
| 2026-07-31 | Auth Web UI spec | Login page + redirect (fragment token handoff, allowlist), unified forgot/change password flow, SMTP email |
| 2026-07-31 | OpenAPI/Swagger UI | `darkaonline/l5-swagger` v11, PHP 8 Attributes, serves at `/api/docs` |
| 2026-07-31 | Master admin seeder | `loa-auth-admin` group + all permissions + admin user from `.env`, idempotent, run after migrations |
| 2026-08-01 | Login destination resolution (web-ui.md v1.2) | Admin login → admin session dashboard; non-admin + valid `?redirect=` → tenant fragment redirect; non-admin direct → reject with generic error; no implicit tenant fallback |
| 2026-08-01 | Admin Dashboard spec (Draft) | Server-rendered, session-authenticated user management (list, search, enable/disable); `web.admin` group gate; self-disable forbidden |
| 2026-08-01 | Identity Kernel v3.0 tenancy | Tenants = external client organizations (per user); `tenants` + `user_tenants` tables; tenant-scoped groups (`user_groups.tenant_id`); tenant-scoped grants (`user_group_permission.tenant_id`); `tenant` JWT claim + `jwt.tenant` middleware; login redirect resolved from `tenants.redirect_origins` instead of env |
| 2026-08-01 | Admin dashboard v2 tenant admin | Platform admins manage tenants, per-tenant groups, per-endpoint grants, membership via `/admin/tenants/*` |
| 2026-08-01 | Tenancy + admin dashboard implemented (v3.0) | Migrations 000011–000015 applied; TenantService, tenant-scoped AuthorizationService, `tenant` claim + `jwt.tenant`, login destination matrix, WebAdminController + `web.admin` middleware + `/admin/users`; `database.sql` rebuilt from migration schema (verified structural parity) |
| 2026-08-01 | Admin dashboard v2 implemented | Tenant CRUD (`/admin/tenants/*`), groups + per-group permissions, member add/remove, suspend/activate; `admin-dashboard.md` promoted to Final |
| 2026-09-18 | Consult: Laravel owns SSO auth (cert pattern) | `callback`/`refresh`/`logout` + `jwt.auth`/`jwt.endpoint` + `loa_connect_refresh` cookie; mirrors `loa-cert-platform` |
| 2026-09-18 | Consult: frontend untouched, scan-only inventory | `access-config`/`user-permissions` observed but auth-owned; no frontend changes |
| 2026-09-18 | Consult: reports deferred to Phase E | No REST report routes exist; Server Components keep computing reports |
| 2026-09-18 | Consult: modular 10-file spec savepoint | Ground truth = `route.ts` scan (112 files, ~142 combos; 118 migrating), not `endpoint-catalog.md` (drift recorded) |
| 2026-09-18 | Consult: hybrid users approach | Users cache table with no FK relationships; all consult user-ref columns = opaque Auth sub TEXT; eliminates circular FK; users upserted from JWT on login |
| 2026-09-18 | Consult: Laravel scaffold done | Real Laravel 12.12 via composer, PHP 8.3 pinned, HealthTest green in Docker |
| 2026-09-22 | Consult: port plan filed in spec | User decision: §11 sequenced landing lives in `auth-integration.md` (v1.4 Draft); re-promotion to Final gates auth-layer code per Rule 0 |
| 2026-09-22 | Consult: no `app_users` — students/employees first-class | User decision: `app_users` duplicated Auth + Identity Kernel; `auth-integration.md` v1.3 §7/§10 + `consult-readiness.md` v1.2 §9 aligned to `data-model.md` Final v1.3; Auth Platform = sole identity authority |
| 2026-09-22 | Consult: `data-model.md` Final = shape ≠ codeable | User decision (Option A): §3 Status column gates implementability (6 Implemented / 3 Delta pending / 17 Specified—not migrated); `audit_logs` not codeable until migration lands |
| 2026-09-22 | Consult: Step 2 services trio landed | `JWTService`/`EncryptionService`/`AuditLogger` verbatim from cert; HealthTest green. `AuditLogger` residual (no model/table, org-FK vs L109) opens at Step 6 |
| 2026-09-22 | Consult: Step 3 JwtMiddleware landed | Cert port with consult re-points only; 401/403 shapes verbatim; Unit tests green. Routes remain open until Step 7 gate |
