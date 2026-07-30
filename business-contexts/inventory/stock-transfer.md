# Stock Transfer
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Inventory
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Stock Transfer entity represents the movement of parts between locations within the Inventory Business Context.

It captures the source, destination, quantities, and status of the transfer.

The Stock Transfer answers:

> **"How do we move parts between locations?"**

---

# 2. Responsibilities

The Stock Transfer entity is responsible for:

- recording stock movements
- tracking transfer status
- validating quantities
- updating stock levels at both locations

---

# 3. What the Stock Transfer Entity Owns

Examples include:

- Transfer Number
- Source Location
- Destination Location
- Part Number
- Quantity
- Transfer Status
- Requested Date
- Completed Date

These concepts belong exclusively to the Stock Transfer entity.

---

# 4. Business Rules

Examples include:

- Every transfer has a unique number.
- Source must have sufficient available stock.
- Destination receives the transferred quantity.
- Transfers are tracked from request to completion.
- Partial transfers are supported.

---

# 5. Lifecycle

Typical lifecycle:

```
Requested

↓

Approved

↓

In Transit

↓

Received

        ├── Partially Received
        └── Rejected
```

---

# 6. Anti-Patterns

The following are architectural violations.

## Warehouse Ownership

```
Stock Transfer

manages Warehouses
```

Warehouse definitions belong to infrastructure.

---

## Workshop Ownership

```
Stock Transfer

fulfills Work Orders
```

Work order fulfillment belongs to the Workshop Context.

---

# 7. Guiding Principle

The Stock Transfer entity is the canonical representation of stock movement.

It owns:

- transfer state
- quantities
- status

It references, but never owns:

- stock items
- locations
- work orders
