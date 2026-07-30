# Repair Activity
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Workshop
**Parent Aggregate:** Work Order
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Repair Activity entity represents an individual unit of repair work performed on a vehicle within a Workshop Business Context.

It captures the specific repairs performed, parts consumed, labor applied, observations made, and completion status.

The Repair Activity entity answers:

> **"What specific repair work was performed on this vehicle?"**

A Repair Activity cannot exist independently of a Work Order.

---

# 2. Responsibilities

The Repair Activity entity is responsible for:

- recording repair work
- tracking parts consumption
- recording labor applied
- capturing observations
- recording completion status
- repair validation
- repair history

---

# 3. What the Repair Activity Entity Owns

Examples include:

- Repair Activity Number
- Activity Type
- Activity Status
- Parts Consumed
- Labor Applied
- Observations
- Start Time
- Completion Time
- Completion Notes
- Technician Notes

These concepts belong exclusively to the Repair Activity entity.

---

# 4. What the Repair Activity Entity Does NOT Own

The Repair Activity entity does not own:

- Work Orders
- Vehicles
- Service Definitions
- Inspection Definitions
- Maintenance Plans
- Warranty Policies
- Inventory
- Technicians
- Quotations
- Invoices

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Repair Activity entity owns:

- repair execution state
- parts consumption records
- labor records
- observation records
- completion data
- validation

The parent Work Order owns the lifecycle of every Repair Activity.

---

# 6. Relationships

A Repair Activity may reference:

```
Service

Inspection

Maintenance

Warranty

Technician Assignment

Inventory Reservation

Parts Catalog
```

These references provide operational context.

Ownership remains with their respective Domains and Business Contexts.

---

# 7. Business Rules

Examples include:

- Every repair activity belongs to one work order.
- Every repair activity has an execution status.
- Repair activities may reference service definitions.
- Parts consumption must be recorded.
- Labor time must be recorded.
- Completed repair activities become read-only.
- Cancelled repair activities cannot resume execution.
- Observations are preserved as part of repair history.

---

# 8. Lifecycle

Typical lifecycle:

```
Planned

↓

In Progress

↓

Completed

        ├── Cancelled
        └── Failed
```

---

# 9. Operational Records

A Repair Activity may record:

- parts consumed
- labor applied
- technician notes
- observations
- completion evidence
- photos
- customer observations
- warranty implications

Operational records become part of the permanent work history.

---

# 10. Public Contracts

The Repair Activity entity should expose stable contracts for:

- creating repair activities
- updating repair progress
- recording parts consumption
- recording labor
- completing repair activities
- retrieving repair history

---

# 11. Consumers

Repair Activity information may be consumed by:

- Workshop
- Inventory
- Warranty
- Reporting
- Accounting

Consumers interact through published contracts.

---

# 12. Entity Invariants

The following invariants must always hold:

- Every repair activity belongs to one work order.
- Every repair activity has a valid status.
- Completed repair activities are immutable.
- Every lifecycle transition must be valid.
- Operational history is preserved.

These invariants are enforced by the parent Work Order aggregate.

---

# 13. Anti-Patterns

The following are architectural violations.

## Vehicle Ownership

```
Repair Activity

owns Vehicle
```

Vehicle belongs to the Vehicle Domain.

---

## Service Ownership

```
Repair Activity

defines Service
```

Service belongs to the Service Domain.

---

## Inventory Ownership

```
Repair Activity

owns Stock
```

Inventory owns stock management.

---

## Financial Ownership

```
Repair Activity

creates Invoice
```

Accounting owns invoicing.

---

# 14. Future Evolution

The Repair Activity entity may evolve to support:

- digital repair checklists
- IoT diagnostics
- AI technician assistance
- predictive repair analysis
- remote repair guidance
- skill-based repair routing

Future additions should continue to represent repair execution without assuming ownership of shared business concepts.

---

# 15. Guiding Principle

The Repair Activity entity is the canonical representation of individual repair work performed within a Workshop Business Context.

It owns:

- repair execution
- parts consumption
- labor application
- observations
- completion status

It references, but never owns:

- vehicles
- services
- technicians
- inventory
- warranty policies

The Work Order aggregate remains the consistency boundary for all Repair Activities.
