# Maintenance Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Maintenance Domain defines the canonical representation of preventive and scheduled vehicle maintenance within the Automotive Domain Pack.

It owns maintenance programs, schedules, intervals, triggers, and maintenance recommendations.

The Maintenance Domain answers:

> **"What maintenance should be performed, and when should it be performed?"**

It does not perform maintenance, schedule appointments, execute repairs, or determine pricing.

---

# 2. Responsibilities

The Maintenance Domain is responsible for:

- maintenance programs
- maintenance schedules
- maintenance intervals
- maintenance triggers
- maintenance recommendations
- maintenance policies
- maintenance applicability
- maintenance lifecycle
- maintenance validation
- maintenance events

---

# 3. What the Maintenance Domain Owns

Examples include:

- Maintenance Program
- Maintenance Schedule
- Maintenance Interval
- Maintenance Trigger
- Maintenance Requirement
- Maintenance Recommendation
- Maintenance Policy

These concepts belong exclusively to the Maintenance Domain.

---

# 4. What the Maintenance Domain Does NOT Own

The Maintenance Domain does not own:

- Service Definitions
- Labor Standards
- Inspection Results
- Work Orders
- Appointments
- Pricing
- Warranty
- Quotations
- Invoices

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Maintenance Domain owns:

- entities
- value objects
- maintenance rules
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts consume maintenance information without redefining maintenance knowledge.

---

# 6. Core Concepts

The primary aggregate is:

```
Maintenance Program
```

Supporting concepts include:

```
Maintenance Schedule

Maintenance Interval

Maintenance Trigger

Maintenance Requirement

Maintenance Recommendation

Maintenance Policy
```

---

# 7. Relationships

The Maintenance Domain may reference:

```
Vehicle

Service
```

These references provide context only.

Maintenance never owns these concepts.

Maintenance recommendations may be consumed by:

- Commercial
- Workshop
- Fleet
- Customer Portal

---

# 8. Business Rules

Examples include:

- Every maintenance program applies to one or more vehicle types.
- Maintenance intervals may be based on mileage, time, operating hours, or usage.
- Multiple maintenance triggers may apply simultaneously.
- A maintenance recommendation may include one or more service definitions.
- Maintenance recommendations remain independent of pricing and scheduling.
- Maintenance definitions should remain reusable across Business Contexts.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Approved

↓

Active

↓

Retired

↓

Archived
```

---

# 10. Domain Events

Examples include:

```
MaintenanceProgramCreated

MaintenanceProgramUpdated

MaintenanceRecommendationGenerated

MaintenanceProgramRetired
```

---

# 11. Public Contracts

The Maintenance Domain should expose stable contracts for:

- retrieving maintenance programs
- determining due maintenance
- validating maintenance applicability
- retrieving maintenance recommendations
- publishing maintenance events

---

# 12. Consumers

Expected consumers include:

- Commercial
- Workshop
- Fleet
- CRM
- Customer Portal

The Maintenance Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Workshop Ownership

```
Maintenance

creates Work Orders
```

Work execution belongs to the Workshop Business Context.

---

## Scheduling Ownership

```
Maintenance

creates Appointments
```

Appointments belong to the Scheduling Domain.

---

## Pricing Ownership

```
Maintenance

calculates Service Prices
```

Pricing belongs to the Pricing Domain.

---

## Commercial Ownership

```
Maintenance

creates Quotations
```

Quotations belong to the Commercial Business Context.

---

# 14. Future Evolution

The Maintenance Domain may evolve to support:

- OEM maintenance schedules
- predictive maintenance
- connected vehicle maintenance
- condition-based maintenance
- AI-generated maintenance recommendations
- fleet maintenance policies

Future additions should continue to represent maintenance knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Maintenance Domain is the canonical source of maintenance knowledge.

It defines:

- what maintenance is required
- when maintenance becomes due
- which vehicles maintenance applies to
- which services satisfy maintenance requirements

It does not determine:

- how maintenance is performed
- when maintenance is booked
- how maintenance is priced
- who performs the maintenance

Those responsibilities belong to other Domains and Business Contexts.