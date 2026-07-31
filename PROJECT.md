# LOA Platform Revamp
## Project Tracker

**Started:** 2026-07-30
**Last Updated:** 2026-07-31 18:00
**Target:** cPanel (PHP 8.2+ / MySQL 8)

---

# ⛔ MANDATORY: Specs Before Code

**No code may be written, modified, or refactored until the relevant spec `.md` file exists and is Final.**

- Missing spec → write it first, or ask
- Draft spec → complete it first
- Final spec → code exactly to it

This rule is enforced in `AGENT.md`, `AI-GUIDE.md`, and `AI-RULES.md`. Violations are failures.

---

# Architecture Status

## Identity Kernel (v2.0)

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Kernel | Identity | `kernels/identity/README.md` | ✅ v2.0 (UserGroup model) |
| Kernel | User | `kernels/identity/entities/user.md` | ✅ Draft |
| Kernel | UserGroup | `kernels/identity/entities/user-group.md` | ✅ Draft |
| Kernel | Permission | `kernels/identity/entities/permission.md` | ✅ Draft |
| Kernel | LoginAttempt | `kernels/identity/entities/login-attempt.md` | ✅ Draft |
| Kernel | PasswordResetToken | `kernels/identity/entities/password-reset-token.md` | ✅ Draft |
| Kernel | RefreshToken | `kernels/identity/entities/refresh-token.md` | ✅ Draft |
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

## Product Assemblies

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Service | CORS | `services/cors/README.md` | ✅ Draft |
| Assembly | LOA Auth Platform | `assemblies/loa-auth-platform/README.md` | ✅ Scaffolded |
| Assembly | LOA Auth Web UI | `assemblies/loa-auth-platform/web-ui.md` | ✅ Draft |
| Assembly | LOA Consult Platform | `assemblies/loa-consult-platform/README.md` | ✅ Draft |
| Assembly | LOA Cert Platform | `assemblies/loa-cert-platform/README.md` | ✅ Draft |

---

# Implementation Status

## Phase 1: Auth Service

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ✅ Done | Laravel 11 skeleton, cPanel-ready |
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
| Login endpoint | ✅ Done | Returns access + refresh tokens, 423 on lock |
| Refresh endpoint | ✅ Done | Rotates token pair |
| Logout endpoint | ✅ Done | 204 response |
| Password reset flow | ✅ Done | Forgot + reset endpoints, hashed tokens |
| User profile endpoint | ✅ Done | `GET /auth/me` + `PUT /auth/password` |
| User list endpoint (admin) | ✅ Done | Requires `users.view` permission |
| Verify endpoint | ✅ Done | Public token validation for consumers |
| Refresh token revocation | 🔶 Spec'd, impl pending | Spec: `kernels/identity/entities/refresh-token.md` + `RefreshTokenRepository` contract; needs model, migration, IdentityService/TokenService wiring |
| CORS configuration | ✅ Done | `config/cors.php` per `services/cors/README.md`, LOA subdomains + env override |
| Auth Web UI spec | ✅ Done | `assemblies/loa-auth-platform/web-ui.md` — login redirect, forgot/change password, email |
| Login page (web) | ⬜ Not started | Blade form; post-login redirect via fragment per web-ui.md |
| Forgot password page (web) | ⬜ Not started | Email form + reset link email |
| Change password page (web) | ⬜ Not started | Shared `/reset-password` form, token-validated |
| SMTP/mail config + email templates | ⬜ Not started | MAIL_* env, reset-password + change-password Blade templates |
| Deploy to auth.loa.edu.ph | ⬜ Not started | |

## Phase 2: Consult App

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ⬜ Not started | |
| JWT middleware | ⬜ Not started | Validate token from auth app |
| Permission middleware | ⬜ Not started | Check UserGroup permissions |
| Appointment model + migrations | ⬜ Not started | |
| TimeSlot model + migrations | ⬜ Not started | |
| Attendee model + migrations | ⬜ Not started | |
| AvailabilityRule model + migrations | ⬜ Not started | |
| Appointment CRUD endpoints | ⬜ Not started | |
| Batch appointment creation | ⬜ Not started | |
| Conflict detection service | ⬜ Not started | |
| Accept/decline/complete endpoints | ⬜ Not started | |
| Semester model + migrations | ⬜ Not started | |
| Rubric models + migrations | ⬜ Not started | |
| Evaluation model + migrations | ⬜ Not started | |
| Evaluation endpoints | ⬜ Not started | |
| Evaluation result computation | ⬜ Not started | |
| Subject/Section/Enrollment models | ⬜ Not started | |
| Academic infrastructure endpoints | ⬜ Not started | |
| Report endpoints (7 types) | ⬜ Not started | |
| CSV import | ⬜ Not started | |
| Email notifications | ⬜ Not started | |
| Deploy to consult.loa.edu.ph | ⬜ Not started | |

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
| Deploy to cert.loa.edu.ph | ⬜ Not started | |

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
| Auth | auth.loa.edu.ph | loa_auth | Laravel 11 | JWT token service, user management |
| Consult | consult.loa.edu.ph | loa_consult | Laravel 11 | Consultation booking, faculty evaluation |
| Cert | cert.loa.edu.ph | loa_cert | Laravel 11 | Certificate issuance, verification |

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
├── AGENT.md                            # AI agent instructions
├── AI-GUIDE.md                         # Architecture guide
├── AI-RULES.md                         # Naming & coding rules
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
│   │   ├── app/Http/Controllers/
│   │   ├── app/Models/                 # User, UserGroup, Permission, etc.
│   │   ├── app/Services/               # JWTService, IdentityService, AuthorizationService
│   │   ├── database/migrations/        # 8 migrations
│   │   ├── routes/api.php
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
| 2026-07-30 | Subdomains for each app | auth/consult/cert.loa.edu.ph |
| 2026-07-30 | Education domain pack | New industry pack for LOA |
| 2026-07-31 | UserGroup model (replaces Role) | Flexible grouping, multi-department support |
| 2026-07-31 | Department in Education Domain | Education-specific, not a canonical kernel |
| 2026-07-31 | Spec-first development | Design before code, catch issues early |
| 2026-07-31 | Specs-before-code is MANDATORY | No code without a Final spec — enforced in AGENT.md, AI-GUIDE.md, AI-RULES.md |
| 2026-07-31 | Identity Kernel v2.0 | Universal grouping, not role-based |
| 2026-07-31 | Event spec files (15) | Per-event spec under kernels/identity/events/ |
| 2026-07-31 | Business rule spec files (8) | Per-rule spec under kernels/identity/rules/ |
| 2026-07-31 | RefreshToken entity spec | DB-backed refresh tokens (jti hashed, single-use, rotation + revocation) per token-lifecycle.md |
| 2026-07-31 | Auth Web UI spec | Login page + redirect (fragment token handoff, allowlist), unified forgot/change password flow, SMTP email |
