# Stock Item
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Inventory
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Stock Item entity represents a physical part or product within the Inventory Business Context.

It captures part information, quantity on hand, location, and stock status.

The Stock Item answers:

> **"How many of this part do we have and where is it?"**

---

# 2. Responsibilities

The Stock Item entity is responsible for:

- tracking part quantities
- managing stock locations
- recording stock status
- calculating reorder points
- tracking batch/serial numbers

---

# 3. What the Stock Item Entity Owns

Examples include:

- Part Number
- Part Description
- Quantity On Hand
- Quantity Reserved
- Quantity Available
- Location
- Minimum Stock Level
- Reorder Point

These concepts belong exclusively to the Stock Item entity.

---

# 4. What the Stock Item Entity Does NOT Own

The Stock Item entity does not own:

- Part specifications (Catalog Domain)
- Pricing data (Pricing Domain)
- Work order consumption (Workshop Context)
- Purchase orders (Procurement Context)

Those belong to other components.

---

# 5. Business Rules

Examples include:

- Every stock item has a unique part number.
- Available quantity = On Hand - Reserved.
- Stock items below minimum level trigger alerts.
- Stock movements are recorded.
- Batch/serial numbers are tracked if applicable.

---

# 6. Lifecycle

Typical lifecycle:

```
Received

↓

Available

↓

Reserved

↓

Dispatched

        ├── Returned
        └── Scrapped
```

---

# 7. Anti-Patterns

The following are architectural violations.

## Catalog Ownership

```
Stock Item

defines Part specifications
```

Part specifications belong to the Catalog Domain.

---

## Workshop Ownership

```
Stock Item

manages Work Orders
```

Work order consumption belongs to the Workshop Context.

---

# 8. Guiding Principle

The Stock Item entity is the canonical representation of physical inventory.

It owns:

- stock quantities
- stock locations
- stock status

It references, but never owns:

- part specifications
- pricing
- work orders
