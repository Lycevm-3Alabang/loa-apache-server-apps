# Appointment
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Consultation
**Aggregate:** Appointment
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Appointment aggregate represents a scheduled consultation between a student and a faculty member.

It is the central aggregate of the Consultation Business Context and owns the complete consultation lifecycle, including time slots, attendees, status transitions, and file attachments.

The Appointment aggregate answers:

> **"What consultation is being booked?"**

It does not own users, availability rules, academic infrastructure, evaluations, or certificates.

---

# 2. Responsibilities

The Appointment aggregate is responsible for:

- appointment creation
- appointment lifecycle
- time slot management
- attendee management
- status transitions
- conflict validation
- file attachment management
- appointment events

---

# 3. What the Appointment Aggregate Owns

Examples include:

- Appointment
- Appointment Type (CONSULTATION, INTERNAL)
- Appointment Status
- Date and Time
- Title and Description
- Action Taken
- Additional Remarks
- Teams Link
- Teams Sync Status
- Requested At
- Updated At

The Appointment aggregate owns these concepts completely.

---

# 4. What the Appointment Aggregate Does NOT Own

The Appointment aggregate does not own:

- Users
- Faculty Availability Rules
- Departments
- Subjects
- Sections
- Semesters
- Evaluations
- Certificates

These belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Appointment aggregate owns:

- aggregate state
- business invariants
- child entities
- lifecycle
- validation
- domain events

The Appointment aggregate is the consistency boundary for all appointment operations.

---

# 6. Aggregate Structure

```
Appointment
│
├── Appointment Time Slot
│   └── Appointment Time Slot
├── Appointment Attendee
│   └── Appointment Attendee
├── Appointment File
│   └── Appointment File
└── Notes
```

All child entities exist only within the lifetime of an Appointment.

---

# 7. Relationships

The Appointment aggregate references:

```
Identity (student, faculty)
Subject
Section
Semester
```

These references provide business context.

Ownership remains with their respective Platform Kernels and Education Domains.

---

# 8. Business Rules

Examples include:

- Every appointment has a student and a faculty member.
- Every appointment has a type (CONSULTATION or INTERNAL).
- Every appointment has a status.
- Time slots must not overlap with existing bookings.
- Conflicts are detected across student and faculty schedules.
- Batch appointments create multiple time slots.
- File attachments have size and type limits.
- Status transitions follow the defined lifecycle.

---

# 9. Lifecycle

Typical lifecycle:

```
Pending

↓

Approved

↓

Completed

        │
        ├── Rejected
        └── Cancelled
```

State transitions are governed by Consultation policies.

---

# 10. Domain Events

Examples include:

```
AppointmentCreated

AppointmentApproved

AppointmentRejected

AppointmentCompleted

AppointmentCancelled

AppointmentFileUploaded
```

---

# 11. Public Contracts

The Appointment aggregate should expose stable contracts for:

- creating appointments
- batch creating appointments
- listing appointments
- accepting appointments
- declining appointments
- completing appointments
- cancelling appointments
- uploading files

---

# 12. Aggregate Invariants

The following invariants must always hold:

- An appointment always has a student.
- An appointment always has a faculty member.
- An appointment always has at least one time slot.
- Time slots do not overlap within an appointment.
- Status transitions follow the defined lifecycle.
- Completed appointments cannot be modified.
- Cancelled appointments cannot be reactivated.

These invariants are enforced by the aggregate root.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Appointment

stores User credentials
```

User credentials belong to the Identity Kernel.

---

## Availability Ownership

```

Appointment

defines Faculty availability rules
```

Availability rules are a separate aggregate.

---

## Evaluation Ownership

```
Appointment

triggers Evaluation creation
```

Evaluation creation belongs to the Evaluation Business Context.

---

# 14. Guiding Principle

The Appointment aggregate is the canonical representation of a consultation booking.

It owns:

- the booking details
- its lifecycle
- its time slots
- its attendees
- its status

It references, but never owns:

- users
- availability rules
- academic infrastructure
- evaluations
- certificates

Those responsibilities remain with Platform Kernels, Education Domains, and other Business Contexts.
