# Consultation Business Context
## Business Context Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Capability:** Consultation
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Consultation Business Context manages the complete consultation booking lifecycle between students and faculty at Lyceum of Alabang.

It owns appointments, faculty availability, time slot management, conflict detection, booking validation, and consultation status transitions.

The Consultation Business Context answers:

> **"How do students book consultations with faculty?"**

It does not own users, authentication, academic infrastructure, evaluations, or certificates.

---

# 2. Responsibilities

The Consultation Business Context is responsible for:

- appointment creation and management
- faculty availability rules
- time slot management
- conflict detection
- booking validation
- consultation status transitions
- attendee management
- file attachments
- internal meeting management
- Microsoft Teams integration
- consultation events

---

# 3. What the Consultation Business Context Owns

Examples include:

- Appointment
- Appointment Time Slot
- Appointment Attendee
- Appointment File
- Faculty Availability Rule
- Consultation Status
- Booking Conflict

These concepts belong exclusively to the Consultation Business Context.

---

# 4. What the Consultation Business Context Does NOT Own

The Consultation Business Context does not own:

- Users
- Authentication
- JWT Tokens
- Departments
- Courses
- Subjects
- Sections
- Semesters
- Enrollments
- Evaluations
- Rubrics
- Certificates
- Reports

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Consultation Business Context owns:

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
Appointment
```

Supporting aggregates include:

```
Appointment Time Slot
Appointment Attendee
Appointment File
Faculty Availability Rule
```

---

# 7. Relationships

The Consultation Business Context references:

```
Identity (users via Auth API)
Organization (departments via Auth API)
Subject (education domain)
Section (education domain)
Enrollment (education domain)
Semester (education domain)
```

Consultation composes these concepts to manage appointment booking.

Ownership remains with their respective Platform Kernels and Education Domains.

---

# 8. Business Rules

Examples include:

- Every appointment belongs to a student and a faculty member.
- Appointments can be CONSULTATION or INTERNAL type.
- Time slots must not overlap with existing bookings.
- Faculty availability rules constrain bookable time slots.
- Appointments follow a status workflow: PENDING → APPROVED → COMPLETED.
- Students can cancel pending appointments.
- Faculty can accept, decline, or complete appointments.
- Conflicts are detected across student and faculty schedules.
- Batch appointments support multi-slot bookings.
- File attachments are limited to specific types and sizes.

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

---

# 10. Domain Events

Examples include:

```
AppointmentCreated

AppointmentApproved

AppointmentRejected

AppointmentCompleted

AppointmentCancelled

AvailabilityRuleCreated

AvailabilityRuleUpdated

AvailabilityRuleDeleted
```

---

# 11. Public Contracts

The Consultation Business Context should expose stable contracts for:

- creating appointments
- batch creating appointments
- listing appointments
- accepting appointments
- declining appointments
- completing appointments
- cancelling appointments
- managing availability rules
- detecting booking conflicts
- uploading appointment files

---

# 12. Consumers

Expected consumers include:

- Consult Platform (API layer)
- Evaluation Context (consultation history)
- Reporting (consultation analytics)
- Auth Platform (user lookup)

The Consultation Business Context remains unaware of implementation details within these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Consultation

stores User credentials
```

User credentials belong to the Identity Kernel (Auth Platform).

---

## Academic Ownership

```

Consultation

manages Department and Course data
```

Academic infrastructure belongs to the Education Domain.

---

## Evaluation Ownership

```

Consultation

computes Evaluation results
```

Evaluation computation belongs to the Evaluation Business Context.

---

## Certificate Ownership

```
Consultation

issues Certificates for completed consultations
```

Certificate issuance belongs to the Certificate Business Context.

---

# 14. Future Evolution

The Consultation Business Context may evolve to support:

- Microsoft Teams meeting creation
- iCal calendar export
- recurring consultation schedules
- consultation reminders
- consultation notes and summaries
- consultation analytics
- waitlist management
- priority booking

Future additions should continue to represent consultation workflows.

---

# 15. Guiding Principle

The Consultation Business Context is the canonical owner of consultation booking.

It defines:

- how appointments are created
- how availability is managed
- how conflicts are detected
- how consultations transition through states

It does not define:

- who the users are
- what academic programs exist
- how faculty are evaluated
- what certificates are issued

Those responsibilities belong to Platform Kernels, Education Domains, or other Business Contexts.
