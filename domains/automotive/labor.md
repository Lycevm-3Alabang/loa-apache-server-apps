# domains/automotive/labor.md

# Labor Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Labor Domain defines the canonical representation of labor operations within the Automotive Domain Pack.

It owns labor definitions, standard durations, skill requirements, and labor classifications.

The Labor Domain answers:

> **"What work is performed and what is the expected effort required?"**

It does not determine pricing, scheduling, or execution.

---

# 2. Responsibilities

The Labor Domain is responsible for:

- labor operations
- standard labor times
- labor classifications
- technician skill requirements
- estimated effort
- labor units
- labor validation
- labor dependencies
- labor domain events

---

# 3. What the Labor Domain Owns

Examples include:

- Labor Operation
- Labor Code
- Standard Time
- Labor Category
- Skill Requirement
- Estimated Duration
- Labor Unit

These concepts belong exclusively to the Labor Domain.

---

# 4. What the Labor Domain Does NOT Own

The Labor Domain does not own:

- Prices
- Quotations
- Jobs
- Work Orders
- Technicians
- Appointments
- Inventory
- Products
- Vehicles

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Labor Domain owns:

- entities
- value objects
- validation
- standard labor definitions
- labor calculations
- lifecycle rules
- domain events
- public contracts

Business Contexts consume labor information but never redefine labor standards.

---

# 6. Core Concepts

The primary aggregate is:

```
Labor Operation
```

Supporting concepts include:

```
Labor Code

Standard Time

Labor Category

Skill Requirement

Estimated Duration

Labor Unit
```

---

# 7. Relationships

The Labor Domain may reference Platform Kernels.

The Labor Domain may also reference other Automotive Domains.

Examples:

```
Catalog

↓

Labor
```

A service may require one or more labor operations.

```
Vehicle

↓

Labor
```

Certain labor operations may only apply to specific vehicle types.

These relationships represent applicability rather than ownership.

---

# 8. Business Rules

Examples include:

- Every labor operation has a unique identifier.
- Standard labor time represents an estimate under normal conditions.
- Labor operations may require specific technician skills.
- Labor operations may depend upon other operations.
- Labor standards are reusable across applications.
- Actual execution time may differ from standard time.

Business Contexts determine actual execution.

The Labor Domain defines the standard.

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

Business Contexts determine when transitions occur.

The Labor Domain defines only the available lifecycle.

---

# 10. Domain Events

Examples include:

```
LaborOperationCreated

LaborOperationUpdated

LaborStandardChanged

LaborOperationRetired
```

Events communicate changes without introducing coupling.

---

# 11. Public Contracts

The Labor Domain should expose stable contracts for:

- retrieving labor operations
- retrieving standard times
- validating labor codes
- determining skill requirements
- publishing labor events

Business Contexts consume these contracts rather than implementing labor definitions.

---

# 12. Consumers

Expected consumers include:

```
Commercial

Workshop

Scheduling

Fleet

Reporting
```

The Labor Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Labor

calculates selling price
```

Pricing belongs to the Pricing Domain.

---

## Workshop Ownership

```
Workshop

defines labor standards
```

Workshop executes labor.

Labor defines labor.

---

## Scheduling Ownership

```
Labor

assigns technicians
```

Scheduling determines resource allocation.

Labor defines required effort.

---

## Commercial Ownership

```
Quotation

defines labor operations
```

Commercial references labor operations.

Labor owns labor definitions.

---

# 14. Future Evolution

The Labor Domain may evolve to support:

- OEM labor guides
- manufacturer standard times
- technician certification levels
- labor complexity factors
- EV-specific labor operations
- diagnostic labor
- condition-based labor estimation

Future additions should continue to represent labor knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Labor Domain defines **what work is performed** and **how much effort it should require**.

It does not determine:

- who performs the work
- when it is performed
- how much it costs
- whether it is approved
- whether it is completed

Those responsibilities belong to other Domains and Business Contexts.

The Labor Domain provides reusable labor knowledge for the entire Automotive Platform.