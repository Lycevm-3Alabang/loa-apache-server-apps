# Event
## Aggregate Specification

**Version:** 1.0
**Status:** Final
**Layer:** Business Context
**Business Context:** Certificate
**Aggregate:** Event
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Event aggregate represents a certificate-issuing event (e.g., graduation, seminar, training).

It owns event metadata, attendee management, certificate issuance rules, and event lifecycle.

The Event aggregate answers:

> **"What event is being certified, and who attended?"**

It does not own users, templates, certificates, or PDF rendering.

---

# 2. Responsibilities

The Event aggregate is responsible for:

- event creation and management
- attendee registration and tracking
- bulk attendee import (CSV)
- certificate issuance per event
- bulk certificate issuance
- certificate reissue
- expired certificate revocation
- template assignment (certificate + email)
- event statistics
- event lifecycle

---

# 3. What the Event Aggregate Owns

Examples include:

- Event Name
- Event Description
- Event Date
- Location
- Organizer
- Certificate Title
- Certificate Number Pattern
- Valid Until
- Status (draft, active, archive)
- Template References (certificate + email)
- Created At
- Updated At

The Event aggregate owns these concepts completely.

---

# 4. What the Event Aggregate Does NOT Own

The Event aggregate does not own:

- Users
- Organizations
- Certificate Templates
- Certificates
- Certificate Numbers
- PDF Rendering
- Email Delivery
- QR Code Generation

These belong to Platform Kernels, other aggregates, or Platform Services.

---

# 5. Ownership

The Event aggregate owns:

- aggregate state
- business invariants
- attendee lifecycle
- certificate issuance rules
- validation
- domain events

The Event aggregate is the consistency boundary for all event and attendee operations.

---

# 6. Aggregate Structure

```
Event
│
├── Event Metadata
│   ├── Name
│   ├── Description
│   ├── Event Date
│   ├── Location
│   └── Organizer
│
├── Certificate Configuration
│   ├── Certificate Title
│   ├── Certificate Number Pattern
│   └── Valid Until
│
├── Template References
│   ├── Certificate Template
│   └── Email Template
│
├── Attendees (collection)
│   └── Event Attendee
│
└── Status
    └── draft → active → archive
```

---

# 7. Relationships

The Event aggregate references:

```
Organization (owner)
Certificate Template (certificate)
Certificate Template (email)
Event Attendees (children)
Certificate (issued per event)
```

These references provide business context.

Ownership remains with their respective aggregates.

---

# 8. Business Rules

Examples include:

- Every event belongs to an organization.
- Event names are unique within an organization.
- Event status must be one of: draft, active, archive.
- Certificate title defaults to "Certificate of Participation".
- Certificate number pattern defaults to "EPOCH".
- One certificate per email per event.
- Attendees are unique per event (by email).
- Bulk issue only processes attendees without existing certificates.
- Reissue only processes attendees with existing certificates.
- Revoke-expired only affects certificates past their expires_at.
- Template changes do not affect already-issued certificates.
- Deleting an event cascades to attendees and unlinking certificates.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Active

↓

Archive
```

State transitions:

- **draft → active**: Event is open for attendee registration and certificate issuance.
- **active → archive**: Event is closed. No new attendees or certificates.
- **draft → archive**: Skip active (cancel event).

---

# 10. Domain Events

Examples include:

```
EventCreated
EventUpdated
EventDeleted
EventArchived
EventActivated
AttendeeAdded
AttendeeRemoved
AttendeesBulkImported
CertificatesIssued
CertificatesBulkIssued
CertificatesReissued
ExpiredCertificatesRevoked
```

---

# 11. Public Contracts

The Event aggregate should expose stable contracts for:

- creating events
- updating events
- deleting events
- listing events (paginated, filtered)
- retrieving event details with stats
- adding attendees
- updating attendees
- removing attendees
- bulk importing attendees (CSV)
- issuing certificates for an event
- bulk issuing certificates
- reissuing certificates
- revoking expired certificates
- cloning templates for events

---

# 12. Aggregate Invariants

The following invariants must always hold:

- An event always belongs to an organization.
- An event has a valid status (draft, active, archive).
- One certificate per email per event.
- Attendees are unique per event (by email).
- Bulk issue only processes attendees without certificates.
- Reissue only processes attendees with certificates.
- Certificate number pattern is non-empty.

These invariants are enforced by the aggregate root.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Event
stores User credentials
```

User credentials belong to the Identity Kernel. Reference by User ID.

---

## Template Ownership

```
Event
defines Template rendering
```

Template rendering is a separate concern managed by the Template aggregate.

---

## Certificate Ownership

```
Event
manages Certificate lifecycle
```

Certificate lifecycle (revocation, verification) belongs to the Certificate aggregate. Events trigger issuance but do not manage certificate state.

---

# 14. Guiding Principle

The Event aggregate is the canonical representation of a certificate-issuing event.

It owns:

- event metadata
- attendee registration
- certificate issuance rules
- event lifecycle

It references, but never owns:

- users
- organizations
- templates
- certificates
- PDF rendering

Those responsibilities remain with Platform Kernels, other aggregates, and Platform Services.
