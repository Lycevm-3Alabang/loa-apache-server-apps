# Certificate
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Certificate
**Aggregate:** Certificate
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Certificate aggregate represents a digital certificate issued to a recipient.

It is the central aggregate of the Certificate Business Context and owns the complete certificate lifecycle, including issuance, PDF generation, email delivery, and verification.

The Certificate aggregate answers:

> **"What certificate was issued to whom?"**

It does not own users, templates, events, or PDF rendering.

---

# 2. Responsibilities

The Certificate aggregate is responsible for:

- certificate creation
- certificate lifecycle
- certificate number generation
- PDF file management
- email delivery tracking
- revocation management
- verification
- certificate events

---

# 3. What the Certificate Aggregate Owns

Examples include:

- Certificate
- Certificate Number
- Recipient Name
- Recipient Email
- Issued At
- Expires At
- Revoked At
- Revoke Reason
- File Path
- Metadata
- Certificate Status

The Certificate aggregate owns these concepts completely.

---

# 4. What the Certificate Aggregate Does NOT Own

The Certificate aggregate does not own:

- Users
- Organizations
- Certificate Templates
- Events
- Event Attendees
- Certificate Sequences
- PDF Rendering
- Email Delivery
- QR Code Generation

These belong to Platform Kernels, other aggregates, or Platform Services.

---

# 5. Ownership

The Certificate aggregate owns:

- aggregate state
- business invariants
- lifecycle
- validation
- domain events

The Certificate aggregate is the consistency boundary for all certificate operations.

---

# 6. Aggregate Structure

```
Certificate
│
├── Certificate Number (unique)
├── Recipient Info
├── PDF File Reference
├── Email Delivery Records
└── Revocation Info
```

---

# 7. Relationships

The Certificate aggregate references:

```
Identity (recipient)
Organization
Event
Certificate Template
```

These references provide business context.

Ownership remains with their respective aggregates.

---

# 8. Business Rules

Examples include:

- Every certificate has a unique certificate number.
- Every certificate belongs to an organization.
- Certificate numbers are generated atomically.
- One certificate per email per event.
- Revoked certificates include a reason.
- PDFs are stored with file path references.
- Emails are tracked for delivery status.
- Expired certificates are marked accordingly.

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

State transitions are governed by Certificate policies.

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

The Certificate aggregate should expose stable contracts for:

- issuing certificates
- revoking certificates
- deleting certificates
- verifying certificates
- retrieving certificates
- downloading PDFs
- sending emails

---

# 12. Aggregate Invariants

The following invariants must always hold:

- A certificate always has a unique certificate number.
- A certificate always belongs to an organization.
- One certificate per email per event.
- Revoked certificates have a reason.
- Active certificates have not been revoked.

These invariants are enforced by the aggregate root.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Certificate

stores User credentials
```

User credentials belong to the Identity Kernel. Reference by User ID.

---

## Template Ownership

```

Certificate

defines Template rendering
```

Template rendering is a separate concern managed by the Template aggregate.

---

## Event Ownership

```

Certificate

manages Event lifecycle
```

Event management belongs to the Event aggregate.

---

# 14. Guiding Principle

The Certificate aggregate is the canonical representation of a digital certificate.

It owns:

- the certificate record
- its number
- its recipient info
- its PDF file
- its status

It references, but never owns:

- users
- organizations
- templates
- events
- PDF rendering

Those responsibilities remain with Platform Kernels, other aggregates, and Platform Services.
