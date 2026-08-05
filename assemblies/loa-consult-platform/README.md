# LOA Consult Platform
## Product Assembly Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The LOA Consult Platform Product Assembly composes Business Contexts to deliver a consultation booking and faculty evaluation application for Lyceum of Alabang.

It assembles existing Business Contexts into a deployable API application without owning any business logic.

The LOA Consult Platform answers:

> **"How do students book consultations and evaluate faculty?"**

It does not own user authentication, certificate generation, or PDF rendering.

---

# 2. Business Contexts Included

The LOA Consult Platform includes the following Business Contexts:

```
Consultation
     ↓
Evaluation
     ↓
Academic (shared infrastructure)
```

---

# 3. What the LOA Consult Platform Owns

The LOA Consult Platform owns:

- API routing and middleware
- JWT validation (via shared secret)
- role-based access enforcement
- request/response transformation
- API documentation
- deployment configuration

The LOA Consult Platform does not own any business logic.

---

# 4. What the LOA Consult Platform Does NOT Own

The LOA Consult Platform does not own:

- user registration or authentication
- JWT token issuance
- appointment business rules
- evaluation business rules
- rubric definitions
- academic infrastructure
- certificate generation
- PDF rendering

Those belong to the Auth Platform, Business Contexts, or Cert Platform.

---

# 5. Included Business Contexts

## Consultation

Owns the consultation booking lifecycle:

- appointment creation and management
- faculty availability rules
- time slot management
- conflict detection
- booking validation
- consultation status transitions
- attendee management
- file attachments

## Evaluation

Owns the faculty evaluation lifecycle:

- evaluation periods (semesters)
- rubric management (groups, categories, items)
- student evaluations (ratings, comments)
- evaluation result computation
- sentiment analysis
- evaluation visibility controls

## Academic

Owns academic infrastructure:

- departments
- department courses
- subjects
- sections
- faculty-subject assignments
- student enrollments

---

# 6. Excluded Business Contexts

The LOA Consult Platform explicitly excludes:

```
Certificate
Commercial
CRM
Workshop
Inventory
Fleet
Finance
```

---

# 7. Platform Dependencies

The LOA Consult Platform relies on Platform Kernels for:

```
Identity (user data via Auth API)
Organization (departments, courses)
```

Platform Kernels are consumed via the Auth API, not direct database access.

---

# 8. Services Dependencies

The LOA Consult Platform may consume:

```
Notification Service (email reminders)
PDF Service (report generation)
Storage Service (file attachments)
```

Services are optional and configured per deployment.

---

# 9. API Surface

The LOA Consult Platform exposes the following API groups:

```
# Appointments
POST   /api/v1/appointments
POST   /api/v1/appointments/batch
GET    /api/v1/appointments
GET    /api/v1/appointments/{id}
PUT    /api/v1/appointments/{id}/accept
PUT    /api/v1/appointments/{id}/decline
PUT    /api/v1/appointments/{id}/complete
PUT    /api/v1/appointments/{id}/cancel

# Availability
GET    /api/v1/availability-rules
POST   /api/v1/availability-rules
PUT    /api/v1/availability-rules/{id}
DELETE /api/v1/availability-rules/{id}

# Evaluations
GET    /api/v1/semesters
GET    /api/v1/evaluation-periods
GET    /api/v1/evaluation-periods/{id}/rubric
POST   /api/v1/evaluations
PUT    /api/v1/evaluations/{id}/ratings
PUT    /api/v1/evaluations/{id}/comments
POST   /api/v1/evaluations/{id}/submit
GET    /api/v1/evaluations/pending

# Academic
GET    /api/v1/admin/departments
GET    /api/v1/admin/subjects
GET    /api/v1/admin/sections

# Reports
GET    /api/v1/reports/health
GET    /api/v1/reports/backlog
GET    /api/v1/reports/coverage
GET    /api/v1/reports/demand
GET    /api/v1/reports/distribution
GET    /api/v1/reports/responsiveness
GET    /api/v1/reports/sentiment
```

---

# 10. Deployment

The LOA Consult Platform is deployed as a standalone Laravel 12 application.

Deployment configuration:

- cPanel hosting
- PHP 8.2+
- MySQL 8 database
- Subdomain: aces-api.lyceumalabang.edu.ph
- Document root: public/

---

# 11. Cross-App Integration

The LOA Consult Platform integrates with:

```
LOA Consult Platform ──JWT──► LOA Auth Platform (token validation)
LOA Consult Platform ──HTTP──► LOA Auth Platform (user lookup)
LOA Consult Platform ──Event──► LOA Cert Platform (future: evaluation → certificate)
```

Cross-app communication uses HTTP with Bearer tokens.

---

# 12. Future Evolution

The LOA Consult Platform may evolve to support:

- Microsoft Teams meeting integration
- iCal calendar export
- real-time notifications via WebSocket
- mobile companion API
- push notifications
- consultation analytics dashboard
- automated evaluation reminders
- bulk evaluation import

Future additions should continue to represent consultation and evaluation workflows.

---

# 13. Anti-Patterns

The following are architectural violations.

## Authentication Ownership

```
LOA Consult Platform

issues JWT tokens
```

JWT issuance belongs to the Auth Platform.

---

## Direct Database Access

```
LOA Consult Platform

reads Auth database for user data
```

User data is accessed via the Auth API. Each app owns its database.

---

## Certificate Logic

```
LOA Consult Platform

generates certificates for evaluation completion
```

Certificate generation belongs to the Cert Platform.

---

# 14. Guiding Principle

The LOA Consult Platform is a thin composition layer.

It wires together Business Contexts for consultation and evaluation.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
