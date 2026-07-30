# domains/automotive/scheduling.md

# Scheduling Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Scheduling Domain defines the canonical representation of appointments within the Automotive Domain Pack.

It owns appointment information, booking rules, availability constraints, and appointment lifecycle.

The Scheduling Domain answers:

> **"When has work been agreed to occur?"**

It does not determine how work is executed.

---

# 2. Responsibilities

The Scheduling Domain is responsible for:

- appointments
- appointment lifecycle
- booking rules
- business hours
- availability
- appointment validation
- appointment events

---

# 3. What the Scheduling Domain Owns

Examples include:

- Appointment
- Appointment Status
- Appointment Type
- Scheduled Date
- Scheduled Time
- Estimated Duration
- Business Hours
- Availability Rule

---

# 4. What the Scheduling Domain Does NOT Own

The Scheduling Domain does not own:

- Work Orders
- Quotations
- Repairs
- Technician Assignment
- Bay Assignment
- Workshop Execution
- Pricing
- Labor Standards
- Vehicles
- Customers

---

# 5. Ownership

The Scheduling Domain owns:

- entities
- value objects
- validation
- booking policies
- lifecycle rules
- domain events
- public contracts

---

# 6. Core Concepts

The primary aggregate is:

```
Appointment
```

Supporting concepts include:

```
Appointment Status

Appointment Type

Business Hours

Availability Rule

Estimated Duration
```

---

# 7. Relationships

The Scheduling Domain may reference:

```
Party

Vehicle

Service
```

These references provide context only.

Scheduling never owns them.

---

# 8. Business Rules

Examples include:

- Every appointment has a scheduled date and time.
- Appointments may only be booked during business hours.
- Appointments may be rescheduled.
- Appointments may be cancelled.
- Appointment duration must be positive.
- Availability rules determine whether a booking is allowed.

---

# 9. Lifecycle

Typical lifecycle:

```
Requested

↓

Confirmed

↓

Checked In

↓

Completed

↓

Cancelled
```

---

# 10. Domain Events

Examples include:

```
AppointmentRequested

AppointmentConfirmed

AppointmentRescheduled

AppointmentCancelled

AppointmentCompleted
```

---

# 11. Public Contracts

The Scheduling Domain should expose stable contracts for:

- creating appointments
- validating appointments
- checking availability
- rescheduling appointments
- cancelling appointments
- publishing appointment events

---

# 12. Consumers

Expected consumers include:

- CRM
- Commercial
- Workshop
- Fleet
- Customer Portal

---

# 13. Anti-Patterns

The following are architectural violations.

## Workshop Ownership

```
Scheduling

creates Work Orders
```

---

## Resource Ownership

```
Scheduling

assigns Technicians
```

---

## Commercial Ownership

```
Scheduling

creates Quotations
```

---

## Pricing Ownership

```
Scheduling

calculates Service Prices
```

---

# 14. Future Evolution

The Scheduling Domain may evolve to support:

- online booking
- recurring appointments
- appointment reminders
- customer self-service scheduling
- waitlists
- calendar integrations

Operational scheduling (resource optimization, technician assignment, bay allocation) belongs to Business Contexts and may consume this Domain.

---

# 15. Guiding Principle

The Scheduling Domain is the canonical source of appointment information.

It defines **when work is planned to occur**.

It does not define **how work is executed**.