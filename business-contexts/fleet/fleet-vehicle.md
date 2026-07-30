# Fleet Vehicle
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Fleet
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Fleet Vehicle entity represents a vehicle managed within the Fleet Business Context.

It captures fleet-specific information like assignment, maintenance schedule, and operational status.

The Fleet Vehicle answers:

> **"Which vehicles are in our fleet and what is their status?"**

---

# 2. Responsibilities

The Fleet Vehicle entity is responsible for:

- tracking fleet vehicles
- managing assignments
- scheduling maintenance
- recording operational status
- tracking mileage

---

# 3. What the Fleet Vehicle Entity Owns

Examples include:

- Fleet Vehicle Number
- Vehicle Reference (by VehicleId)
- Assignment Status
- Maintenance Schedule
- Current Mileage
- Operational Status

These concepts belong exclusively to the Fleet Vehicle entity.

---

# 4. What the Fleet Vehicle Entity Does NOT Own

The Fleet Vehicle entity does not own:

- Vehicle specifications (Vehicle Domain)
- Work orders (Workshop Context)
- Invoices (Finance Context)
- Parts (Inventory Context)

Those belong to other components.

---

# 5. Business Rules

Examples include:

- Every fleet vehicle has a unique number.
- Fleet vehicles reference Vehicle Domain by VehicleId.
- Maintenance is scheduled by mileage or time.
- Operational status tracks availability.
- Assignments are recorded for accountability.

---

# 6. Lifecycle

Typical lifecycle:

```
Registered

↓

Assigned

↓

Active

↓

Maintenance Required

↓

Maintenance Completed

        ├── Decommissioned
        └── Reassigned
```

---

# 7. Anti-Patterns

The following are architectural violations.

## Vehicle Ownership

```
Fleet Vehicle

defines Vehicle specifications
```

Vehicle specifications belong to the Vehicle Domain.

---

## Workshop Ownership

```
Fleet Vehicle

creates Work Orders
```

Work orders belong to the Workshop Context.

---

# 8. Guiding Principle

The Fleet Vehicle entity is the canonical representation of a fleet vehicle.

It owns:

- fleet vehicle state
- assignment status
- maintenance schedule

It references, but never owns:

- vehicle specifications
- work orders
- invoices
