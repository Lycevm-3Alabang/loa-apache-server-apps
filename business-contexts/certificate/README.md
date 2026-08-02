# Certificate Business Context
## Business Context Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Capability:** Certificate
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Certificate Business Context manages the complete digital certificate lifecycle at Lyceum of Alabang.

It owns certificate issuance, template management, certificate number generation, PDF rendering, email delivery, and public verification.

The Certificate Business Context answers:

> **"How do we issue, manage, and verify digital certificates?"**

It does not own users, authentication, appointments, evaluations, or academic infrastructure.

---

# 2. Responsibilities

The Certificate Business Context is responsible for:

- certificate issuance
- certificate revocation
- certificate deletion
- certificate verification
- certificate number generation
- template management
- template rendering
- PDF generation
- QR code generation
- email delivery with PDF attachment
- audit trail
- event management
- attendee management
- bulk operations

---

# 3. What the Certificate Business Context Owns

Examples include:

- Certificate
- Certificate Template
- Certificate Number
- Certificate Email
- Certificate Sequence
- Organization
- User Membership
- Event
- Event Attendee
- Template Type
- Certificate Status
- Verification URL

These concepts belong exclusively to the Certificate Business Context.

---

# 4. What the Certificate Business Context Does NOT Own

The Certificate Business Context does not own:

- Users (identity belongs to Identity Kernel)
- Authentication
- JWT Tokens
- Departments
- Courses
- Subjects
- Sections
- Semesters
- Enrollments
- Appointments
- Faculty Availability
- Evaluations
- Rubrics

Those belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Certificate Business Context owns:

- business workflows
- aggregates
- commands
- business policies
- validation
- transaction boundaries
- lifecycle rules
- domain events
- public contracts

It references shared concepts without redefining them.

---

# 6. Core Aggregates

Primary aggregates include:

```
Certificate
```

Supporting aggregates include:

```
Certificate Template
Certificate Number
Certificate Email
Certificate Sequence
Event
Event Attendee
Organization
User Membership
```

Entity specifications are in `entities/`:

| Aggregate | Spec |
|-----------|------|
| Certificate | `entities/certificate.md` |
| Certificate Template | `entities/template.md` |

---

# 7. Relationships

The Certificate Business Context references:

```
Identity (recipient via Auth API)
Organization (multi-tenant support)
```

Certificate composes these concepts to manage digital certificates.

Ownership remains with their respective Platform Kernels.

---

# 8. Business Rules

Examples include:

- Every certificate has a unique certificate number.
- Every certificate belongs to an organization.
- Certificates may be linked to an event.
- Certificate numbers are generated atomically.
- One certificate per email per event.
- Revoked certificates include a reason.
- Templates support placeholder variables.
- PDFs are generated from templates.
- QR codes encode verification URLs.
- Emails include PDF attachments.
- Public verification requires no authentication.

---

# 9. Lifecycle

Typical lifecycle:

```
Issued

↓

Active

        │
        ├── Expired
        └── Revoked
```

---

# 10. Domain Events

Examples include:

```
CertificateIssued

CertificateRevoked

CertificateDeleted

CertificateEmailSent

CertificateVerified
```

---

# 11. Public Contracts

The Certificate Business Context should expose stable contracts for:

- issuing certificates
- bulk issuing certificates
- revoking certificates
- deleting certificates
- verifying certificates
- retrieving certificates
- downloading PDFs
- sending emails
- managing templates
- managing events
- managing attendees

---

# 12. Consumers

Expected consumers include:

- Cert Platform (API layer)
- Consult Platform (future: evaluation → certificate)
- Auth Platform (user lookup)

The Certificate Business Context remains unaware of implementation details within these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Certificate

stores User credentials
```

User credentials belong to the Identity Kernel (Auth Platform).

---

## Evaluation Ownership

```

Certificate

computes Evaluation results
```

Evaluation computation belongs to the Evaluation Business Context.

---

## PDF Ownership by Template

```

Certificate

defines PDF rendering engine
```

PDF rendering is a Platform Service. Templates define content, not rendering.

---

## Consultation Ownership

```

Certificate

manages Consultation bookings
```

Consultation management belongs to the Consultation Business Context.

---

# 14. Future Evolution

The Certificate Business Context may evolve to support:

- visual template editor (canvas-based)
- bulk certificate issuance workflow
- QR code verification
- certificate sharing via public URL
- certificate revocation list
- batch email delivery
- certificate analytics
- API key authentication for external systems
- webhook notifications
- certificate templates marketplace

Future additions should continue to represent certificate management workflows.

---

# 15. Guiding Principle

The Certificate Business Context is the canonical owner of digital certificates.

It defines:

- how certificates are issued
- how templates are managed
- how PDFs are generated
- how certificates are verified

It does not define:

- who the users are
- what evaluations are completed
- what appointments are booked
- what academic programs exist

Those responsibilities belong to Platform Kernels, Education Domains, or other Business Contexts.
