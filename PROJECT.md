# LOA Platform Revamp
## Project Tracker

**Started:** 2026-07-30
**Last Updated:** 2026-07-30
**Target:** cPanel (PHP 8.2+ / MySQL 8)

---

# Architecture Status

| Layer | Component | Spec | Status |
|-------|-----------|------|--------|
| Assembly | LOA Auth Platform | `assemblies/loa-auth-platform/README.md` | ✅ Draft |
| Assembly | LOA Consult Platform | `assemblies/loa-consult-platform/README.md` | ✅ Draft |
| Assembly | LOA Cert Platform | `assemblies/loa-cert-platform/README.md` | ✅ Draft |
| Domain | Education Pack | `domains/education/README.md` | ✅ Draft |
| Domain | Course | `domains/education/course.md` | ✅ Draft |
| Domain | Semester | `domains/education/semester.md` | ✅ Draft |
| Domain | Subject | `domains/education/subject.md` | ✅ Draft |
| Domain | Section | `domains/education/section.md` | ✅ Draft |
| Domain | Enrollment | `domains/education/enrollment.md` | ✅ Draft |
| Context | Consultation | `business-contexts/consultation/README.md` | ✅ Draft |
| Context | Appointment | `business-contexts/consultation/appointment.md` | ✅ Draft |
| Context | Availability | `business-contexts/consultation/availability.md` | ✅ Draft |
| Context | Evaluation | `business-contexts/evaluation/README.md` | ✅ Draft |
| Context | Evaluation (aggregate) | `business-contexts/evaluation/evaluation.md` | ✅ Draft |
| Context | Rubric | `business-contexts/evaluation/rubric.md` | ✅ Draft |
| Context | Certificate | `business-contexts/certificate/README.md` | ✅ Draft |
| Context | Certificate (aggregate) | `business-contexts/certificate/certificate.md` | ✅ Draft |
| Context | Certificate Template | `business-contexts/certificate/template.md` | ✅ Draft |

---

# Implementation Status

## Phase 1: Auth Service

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ⬜ Not started | |
| User model + migrations | ⬜ Not started | |
| Role model + migrations | ⬜ Not started | |
| Department model + migrations | ⬜ Not started | |
| JWT service (pure PHP) | ⬜ Not started | HMAC-SHA256, no packages |
| Register endpoint | ⬜ Not started | |
| Login endpoint | ⬜ Not started | Returns access + refresh tokens |
| Refresh endpoint | ⬜ Not started | |
| Logout endpoint | ⬜ Not started | Revokes refresh token |
| Password reset flow | ⬜ Not started | |
| User profile endpoint | ⬜ Not started | |
| User list endpoint (admin) | ⬜ Not started | |
| CORS configuration | ⬜ Not started | |
| Deploy to auth.loa.edu.ph | ⬜ Not started | |

## Phase 2: Consult App

| Task | Status | Notes |
|------|--------|-------|
| Laravel project scaffold | ⬜ Not started | |
| JWT middleware | ⬜ Not started | Validate token from auth app |
| Role middleware | ⬜ Not started | RequireRole(ADMIN, DEAN, FACULTY, STUDENT) |
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
| Role middleware | ⬜ Not started | RequireRole(ADMIN, STAFF, PARTICIPANT) |
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
├── assemblies/
│   ├── loa-auth-platform/README.md     # Auth assembly spec
│   ├── loa-consult-platform/README.md  # Consult assembly spec
│   └── loa-cert-platform/README.md     # Cert assembly spec
├── domains/
│   └── education/
│       ├── README.md                   # Education domain pack
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
├── kernels/                            # Existing template kernels
├── services/                           # Existing template services
└── ...                                 # Existing template files
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
