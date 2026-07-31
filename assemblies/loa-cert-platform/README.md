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

# Admin
GET    /api/v1/admin/audit
GET    /api/v1/admin/dashboard
GET    /api/v1/admin/users
PUT    /api/v1/admin/users/{id}/role
```

---

# 11. Deployment

The LOA Cert Platform is deployed as a standalone Laravel 12 application.

Deployment configuration:

- cPanel hosting
- PHP 8.2+
- MySQL 8 database
- Subdomain: cert.loa.edu.ph
- Document root: public/

---

# 12. Cross-App Integration

The LOA Cert Platform integrates with:

```
LOA Cert Platform ──JWT──► LOA Auth Platform (token validation)
LOA Cert Platform ──HTTP──► LOA Auth Platform (user lookup)
LOA Consult Platform ──Event──► LOA Cert Platform (future: evaluation → certificate)
```

Cross-app communication uses HTTP with Bearer tokens.

---

# 13. Future Evolution

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

# 14. Anti-Patterns

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

# 15. Guiding Principle

The LOA Cert Platform is a thin composition layer.

It wires together Business Contexts for certificate management.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
