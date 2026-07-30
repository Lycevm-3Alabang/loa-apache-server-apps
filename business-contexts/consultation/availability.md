# Faculty Availability Rule
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Consultation
**Aggregate:** Faculty Availability Rule
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Faculty Availability Rule aggregate defines when faculty members are available for consultations.

It owns availability schedules, time ranges, date ranges, and blocking rules.

The Faculty Availability Rule aggregate answers:

> **"When is this faculty member available?"**

It does not own appointments, users, academic infrastructure, or evaluations.

---

# 2. Responsibilities

The Faculty Availability Rule aggregate is responsible for:

- defining available days of the week
- defining time ranges per day
- defining date ranges for availability
- blocking specific time slots
- validating bookings against availability
- availability rule lifecycle

---

# 3. What the Faculty Availability Rule Aggregate Owns

Examples include:

- Faculty Availability Rule
- Day of Week
- Start Time
- End Time
- Start Date
- End Date
- Is Blocked Flag

The Faculty Availability Rule aggregate owns these concepts completely.

---

# 4. What the Faculty Availability Rule Aggregate Does NOT Own

The Faculty Availability Rule aggregate does not own:

- Appointments
- Users
- Departments
- Subjects
- Sections
- Semesters
- Evaluations
- Certificates

These belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Faculty Availability Rule aggregate owns:

- aggregate state
- business invariants
- lifecycle
- validation
- domain events

The Faculty Availability Rule aggregate is the consistency boundary for availability management.

---

# 6. Aggregate Structure

```
Faculty Availability Rule

    (one per faculty member per day of week per date range)
```

Each rule is independent. Rules are evaluated collectively when checking availability.

---

# 7. Relationships

The Faculty Availability Rule aggregate references:

```
Identity (faculty member via Auth API)
```

This reference provides business context.

Ownership remains with the Identity Kernel.

---

# 8. Business Rules

Examples include:

- Each rule applies to one faculty member.
- Each rule covers one day of the week.
- Rules may define a time range (start/end time).
- Rules may be blocking (isBlocked = true).
- Start date is required; end date is optional.
- Rules with past end dates are inactive.
- Multiple rules can exist per faculty member for different days.
- Conflicting rules for the same day are detected.

---

# 9. Lifecycle

Typical lifecycle:

```
Created

↓

Active

↓

Expired
```

---

# 10. Domain Events

Examples include:

```
AvailabilityRuleCreated

AvailabilityRuleUpdated

AvailabilityRuleDeleted
```

---

# 11. Public Contracts

The Faculty Availability Rule aggregate should expose stable contracts for:

- creating availability rules
- updating availability rules
- deleting availability rules
- checking if faculty is available at a given time
- listing faculty availability rules

---

# 12. Anti-Patterns

The following are architectural violations.

## Appointment Ownership

```
Availability Rule

creates Appointments
```

Appointment creation belongs to the Appointment aggregate.

---

## User Ownership

```
Availability Rule

stores Faculty profile data
```

Faculty identity belongs to the Identity Kernel.

---

# 13. Guiding Principle

The Faculty Availability Rule aggregate is the canonical representation of faculty availability.

It defines:

- when faculty are available
- which time slots are bookable
- which slots are blocked

It does not define:

- what appointments are booked
- who the faculty member is
- what subjects are taught

Those responsibilities remain with other aggregates, Domains, and Business Contexts.
