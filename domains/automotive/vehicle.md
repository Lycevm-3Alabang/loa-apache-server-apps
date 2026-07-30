# domains/automotive/vehicle.md

# Vehicle Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Vehicle Domain defines the canonical representation of vehicles within the Automotive Domain Pack.

It owns vehicle identity, technical specifications, classification, and lifecycle.

The Vehicle Domain answers:

> **"What vehicle is this?"**

It does not determine ownership, maintenance history, commercial transactions, or operational workflows.

---

# 2. Responsibilities

The Vehicle Domain is responsible for:

- vehicle identity
- vehicle specifications
- vehicle classification
- vehicle attributes
- vehicle applicability
- vehicle lifecycle
- vehicle validation
- vehicle events

---

# 3. What the Vehicle Domain Owns

Examples include:

- Vehicle
- VIN
- Registration
- Make
- Model
- Variant
- Model Year
- Engine Specification
- Transmission Specification
- Fuel Type
- Odometer
- Vehicle Status

These concepts belong exclusively to the Vehicle Domain.

---

# 4. What the Vehicle Domain Does NOT Own

The Vehicle Domain does not own:

- Customers
- Vehicle Ownership
- Quotations
- Work Orders
- Service History
- Maintenance Plans
- Warranty Claims
- Appointments
- Inventory
- Billing

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Vehicle Domain owns:

- entities
- value objects
- validation
- classification rules
- lifecycle rules
- domain events
- public contracts

Business Contexts reference vehicles but never redefine vehicle identity or specifications.

---

# 6. Core Concepts

The primary aggregate is:

```
Vehicle
```

Supporting concepts include:

```
VIN

Registration

Vehicle Specification

Engine Specification

Transmission Specification

Fuel Type

Vehicle Status

Model Year
```

---

# 7. Relationships

The Vehicle Domain may reference Platform Kernels.

Examples:

```
Vehicle

↓

Party
```

A vehicle may be associated with one or more parties such as:

- Registered Owner
- Driver
- Fleet Operator
- Lessee

The Vehicle Domain does not own these relationships.

The Vehicle Domain may also be referenced by:

- Catalog
- Service
- Inspection
- Maintenance
- Warranty
- Commercial
- Workshop

---

# 8. Business Rules

Examples include:

- Every vehicle has a unique identity.
- VIN uniquely identifies a vehicle where available.
- Registration identifiers may change over time.
- Vehicle specifications should remain immutable after publication unless corrected.
- Odometer readings should not decrease.
- Vehicle classification must be valid.
- A vehicle may exist before any customer relationship exists.
- A vehicle may exist without service history.

---

# 9. Lifecycle

Typical lifecycle:

```
Registered

↓

Active

↓

Inactive

↓

Retired

↓

Archived
```

Business Contexts determine operational status.

The Vehicle Domain defines only the vehicle lifecycle.

---

# 10. Domain Events

Examples include:

```
VehicleRegistered

VehicleUpdated

VehicleRetired

VehicleArchived
```

---

# 11. Public Contracts

The Vehicle Domain should expose stable contracts for:

- retrieving vehicles
- validating vehicle identity
- retrieving vehicle specifications
- determining vehicle applicability
- publishing vehicle events

---

# 12. Consumers

Expected consumers include:

- CRM
- Commercial
- Workshop
- Inspection
- Maintenance
- Warranty
- Fleet
- Reporting

The Vehicle Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Vehicle

owns Customer
```

Customers belong to the Party Kernel.

---

## Workshop Ownership

```
Vehicle

stores Service History
```

Service history belongs to the Workshop Business Context.

---

## Commercial Ownership

```
Vehicle

creates Quotations
```

Commercial owns quotations.

---

## Warranty Ownership

```
Vehicle

determines Warranty Coverage
```

Warranty belongs to the Warranty Domain.

---

# 14. Future Evolution

The Vehicle Domain may evolve to support:

- EV specifications
- Battery specifications
- Charging capabilities
- OEM metadata
- Recall information
- Connected vehicle identifiers
- Telematics integration
- Autonomous vehicle attributes

Future additions should continue to represent vehicle knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Vehicle Domain is the canonical source of vehicle information.

It defines:

- what a vehicle is
- how a vehicle is identified
- how a vehicle is classified
- the vehicle's technical characteristics

It does not determine:

- who owns the vehicle
- what work has been performed
- what services are recommended
- what warranties apply
- what commercial transactions involve the vehicle

Those responsibilities belong to Platform Kernels, other Domains, or Business Contexts.