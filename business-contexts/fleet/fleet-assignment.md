# Fleet Assignment
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Fleet
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Fleet Assignment entity represents the allocation of a fleet vehicle to an operation or driver within the Fleet Business Context.

It captures who has the vehicle, for what purpose, and for how long.

The Fleet Assignment answers:

> **"Who has which vehicle and why?"**

---

# 2. Responsibilities

The Fleet Assignment entity is responsible for:

- recording vehicle assignments
- tracking assignment duration
- managing driver allocation
- recording assignment purpose
- tracking vehicle return

---

# 3. What the Fleet Assignment Entity Owns

Examples include:

- Assignment Number
- Fleet Vehicle Reference
- Driver Reference
- Assignment Purpose
- Start Date
- End Date
- Assignment Status

These concepts belong exclusively to the Fleet Assignment entity.

---

# 4. Business Rules

Examples include:

- Every assignment has a unique number.
- A vehicle can only be assigned to one operation at a time.
- Assignments have a defined start and end date.
- Vehicle return is recorded when assignment ends.
- Assignment history is preserved.

---

# 5. Lifecycle

Typical lifecycle:

```
Requested

↓

Approved

↓

Active

↓

Completed

        ├── Extended
        └── Cancelled
```

---

# 6. Anti-Patterns

The following are architectural violations.

## Driver Ownership

```
Fleet Assignment

manages Driver profiles
```

Driver profiles belong to Party or a Driver Context.

---

## Workshop Ownership

```
Fleet Assignment

creates Work Orders
```

Work orders belong to the Workshop Context.

---

# 7. Guiding Principle

The Fleet Assignment entity is the canonical representation of vehicle allocation.

It owns:

- assignment state
- driver reference
- duration

It references, but never owns:

- fleet vehicles
- drivers
- work orders
