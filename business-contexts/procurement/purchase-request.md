# Purchase Request
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Procurement
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Purchase Request entity represents a request to acquire goods or services within the Procurement Business Context.

It captures the items needed, quantities, justification, and approval status.

The Purchase Request answers:

> **"What do we need to buy and why?"**

---

# 2. Responsibilities

The Purchase Request entity is responsible for:

- recording items needed
- tracking quantities
- capturing justification
- managing approval workflow
- converting to purchase orders

---

# 3. What the Purchase Request Entity Owns

Examples include:

- Request Number
- Request Date
- Requestor
- Items
- Justification
- Approval Status
- Priority

These concepts belong exclusively to the Purchase Request entity.

---

# 4. What the Purchase Request Entity Does NOT Own

The Purchase Request entity does not own:

- Vendor data
- Purchase orders
- Invoice data
- Stock levels

Those belong to other components.

---

# 5. Business Rules

Examples include:

- Every request has a unique number.
- Requests must be approved before converting to PO.
- Items reference catalog parts by PartNumber.
- Justification explains the business need.
- Priority determines processing order.

---

# 6. Lifecycle

Typical lifecycle:

```
Draft

↓

Submitted

↓

Approved

↓

Converted to PO

        ├── Rejected
        └── Cancelled
```

---

# 7. Anti-Patterns

The following are architectural violations.

## Purchase Order Ownership

```
Purchase Request

creates Purchase Orders
```

Purchase orders are created after approval.

---

## Inventory Ownership

```
Purchase Request

manages Stock
```

Stock management belongs to the Inventory Context.

---

# 8. Guiding Principle

The Purchase Request entity is the canonical representation of a buying request.

It owns:

- request state
- items
- justification

It references, but never owns:

- vendors
- stock items
- purchase orders
