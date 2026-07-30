# Inspection Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Inspection Domain defines the canonical representation of vehicle inspections within the Automotive Domain Pack.

It owns inspection definitions, inspection execution models, findings, condition assessments, and recommendations.

The Inspection Domain answers:

> **"What is the condition of the vehicle?"**

It does not determine repair execution, pricing, maintenance planning, or commercial decisions.

---

# 2. Responsibilities

The Inspection Domain is responsible for:

- inspection definitions
- inspection templates
- inspection execution
- inspection items
- inspection findings
- condition assessments
- recommendations
- inspection lifecycle
- inspection validation
- inspection events

---

# 3. What the Inspection Domain Owns

Examples include:

- Inspection
- Inspection Template
- Inspection Item
- Inspection Result
- Finding
- Recommendation
- Condition Assessment
- Inspection Category

These concepts belong exclusively to the Inspection Domain.

---

# 4. What the Inspection Domain Does NOT Own

The Inspection Domain does not own:

- Work Orders
- Repairs
- Quotations
- Pricing
- Labor Standards
- Maintenance Schedules
- Warranty Claims
- Appointments
- Invoices

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Inspection Domain owns:

- entities
- value objects
- validation
- inspection rules
- lifecycle rules
- domain events
- public contracts

Business Contexts consume inspection results without redefining inspection concepts.

---

# 6. Core Concepts

The primary aggregate is:

```
Inspection
```

Supporting concepts include:

```
Inspection Template

Inspection Item

Inspection Result

Finding

Recommendation

Condition Assessment

Inspection Category
```

---

# 7. Relationships

The Inspection Domain may reference:

```
Vehicle

Service
```

Inspection never owns these concepts.

Inspection results may be consumed by:

- Maintenance
- Commercial
- Workshop
- Warranty

---

# 8. Business Rules

Examples include:

- Every inspection follows a defined inspection template.
- An inspection consists of one or more inspection items.
- Every inspection item produces an inspection result.
- Findings may generate recommendations.
- Recommendations do not automatically create repairs.
- Inspection results must remain traceable and immutable once completed.
- Vehicle condition is determined from inspection results.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

In Progress

↓

Completed

↓

Reviewed

↓

Archived
```

---

# 10. Domain Events

Examples include:

```
InspectionCreated

InspectionStarted

InspectionCompleted

FindingRecorded

RecommendationGenerated
```

---

# 11. Public Contracts

The Inspection Domain should expose stable contracts for:

- retrieving inspection templates
- creating inspections
- recording inspection results
- retrieving findings
- retrieving recommendations
- publishing inspection events

---

# 12. Consumers

Expected consumers include:

- Workshop
- Commercial
- Maintenance
- Warranty
- Fleet
- Customer Portal

The Inspection Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Repair Ownership

```
Inspection

creates Repairs
```

Repair execution belongs to the Workshop Business Context.

---

## Commercial Ownership

```
Inspection

creates Quotations
```

Quotations belong to the Commercial Business Context.

---

## Maintenance Ownership

```
Inspection

creates Maintenance Plans
```

Maintenance planning belongs to the Maintenance Domain.

---

## Pricing Ownership

```
Inspection

calculates Repair Costs
```

Pricing belongs to the Pricing Domain.

---

# 14. Future Evolution

The Inspection Domain may evolve to support:

- OEM inspection templates
- digital inspection forms
- photo and video evidence
- AI-assisted inspections
- predictive condition scoring
- regulatory inspections
- compliance inspections

Future additions should continue to represent inspection knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Inspection Domain is the canonical source of vehicle condition information.

It defines:

- what was inspected
- what was found
- the condition of the vehicle
- recommended actions

It does not determine:

- how repairs are performed
- how repairs are priced
- when repairs occur
- whether recommendations are accepted

Those responsibilities belong to other Domains and Business Contexts.