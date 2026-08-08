# Event Attendee
## Aggregate Specification

**Version:** 1.0
**Status:** Final
**Layer:** Business Context
**Business Context:** Certificate
**Aggregate:** Event Attendee
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Event Attendee aggregate represents a participant registered for an event.

It owns attendee information, attendance status, completion status, and certificate linkage.

The Event Attendee aggregate answers:

> **"Who participated in the event, and did they receive a certificate?"**

It does not own users, events, templates, or certificates.

---

# 2. Responsibilities

The Event Attendee aggregate is responsible for:

- attendee registration
- attendance tracking
- completion tracking
- certificate linkage
- bulk import (CSV)
- file data storage (uploaded certificates)
- attendee metadata

---

# 3. What the Event Attendee Aggregate Owns

Examples include:

- Attendee Name
- Attendee Email
- Attended (boolean)
- Completed (boolean)
- Attended At
- Completed At
- Certificate ID (reference)
- Certificate Number (reference)
- Metadata (file data, generation mode)
- Created At
- Updated At

The Event Attendee aggregate owns these concepts completely.

---

# 4. What the Event Attendee Aggregate Does NOT Own

The Event Attendee aggregate does not own:

- Users
- Organizations
- Events
- Certificate Templates
- Certificates
- PDF Rendering
- Email Delivery

These belong to Platform Kernels, other aggregates, or Platform Services.

---

# 5. Ownership

The Event Attendee aggregate owns:

- aggregate state
- business invariants
- attendance status
- completion status
- certificate linkage
- validation
- domain events

The Event Attendee aggregate is the consistency boundary for attendee operations.

---

# 6. Aggregate Structure

```
Event Attendee
│
├── Identity
│   ├── Name
│   └── Email
│
├── Status
│   ├── Attended (boolean)
│   ├── Completed (boolean)
│   ├── Attended At
│   └── Completed At
│
├── Certificate Link
│   ├── Certificate ID
│   └── Certificate Number
│
└── Metadata
    ├── Generation Mode (template|file)
    ├── File Data (base64)
    ├── File Name
    └── File Type
```

---

# 7. Relationships

The Event Attendee aggregate references:

```
Event (parent)
Organization (owner)
Certificate (issued)
```

These references provide business context.

Ownership remains with their respective aggregates.

---

# 8. Business Rules

Examples include:

- Every attendee belongs to an event.
- Every attendee belongs to an organization.
- Attendees are unique per event (by email).
- Attended defaults to false.
- Completed defaults to false.
- Attended At is set when attended becomes true.
- Completed At is set when completed becomes true.
- Certificate ID links to an issued certificate.
- Certificate Number is denormalized for quick lookup.
- Metadata stores file upload data when generation mode is "file".
- Bulk import creates attendees from CSV data.
- Removing an attendee with a certificate unlinks the certificate.
- File data (base64) is stored in metadata for uploaded certificates.

---

# 9. Lifecycle

Typical lifecycle:

```
Registered

↓

Attended

↓

Completed

↓

Certificate Issued
```

State transitions:

- **Registered → Attended**: Attendee checked in.
- **Attended → Completed**: Attendee finished the event.
- **Completed → Certificate Issued**: Certificate generated and linked.
- **Registered → Certificate Issued**: Direct issuance without attendance tracking.

---

# 10. Domain Events

Examples include:

```
AttendeeRegistered
AttendeeCheckedIn
AttendeeCompleted
AttendeeRemoved
AttendeeCertificateLinked
AttendeeCertificateUnlinked
AttendeesBulkImported
```

---

# 11. Public Contracts

The Event Attendee aggregate should expose stable contracts for:

- registering attendees
- updating attendee information
- checking in attendees
- marking attendees as completed
- linking certificates
- unlinking certificates
- removing attendees
- bulk importing attendees (CSV)
- retrieving attendee file data
- previewing attendee deletion impact

---

# 12. Aggregate Invariants

The following invariants must always hold:

- An attendee always belongs to an event.
- An attendee always belongs to an organization.
- Attendees are unique per event (by email).
- Completed implies Attended.
- Certificate ID, when set, references an existing certificate.
- Certificate Number, when set, matches the linked certificate.
- File data is only stored when generation mode is "file".

These invariants are enforced by the aggregate root.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Event Attendee
stores User credentials
```

User credentials belong to the Identity Kernel. Reference by email.

---

## Event Ownership

```
Event Attendee
manages Event lifecycle
```

Event management belongs to the Event aggregate.

---

## Certificate Ownership

```
Event Attendee
manages Certificate lifecycle
```

Certificate lifecycle belongs to the Certificate aggregate. Attendees reference certificates but do not manage them.

---

# 14. Guiding Principle

The Event Attendee aggregate is the canonical representation of an event participant.

It owns:

- attendee identity
- attendance status
- completion status
- certificate linkage
- file data

It references, but never owns:

- users
- organizations
- events
- certificates

Those responsibilities remain with Platform Kernels, other aggregates, and Platform Services.
