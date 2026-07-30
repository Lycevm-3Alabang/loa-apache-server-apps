# Vendor
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Procurement
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Vendor entity represents a supplier of goods or services within the Procurement Business Context.

It captures vendor information, contact details, and performance metrics.

The Vendor answers:

> **"Who do we buy from?"**

---

# 2. Responsibilities

The Vendor entity is responsible for:

- recording vendor details
- tracking contact information
- managing vendor performance
- storing payment terms
- recording certifications

---

# 3. What the Vendor Entity Owns

Examples include:

- Vendor Number
- Vendor Name
- Contact Information
- Payment Terms
- Performance Rating
- Certifications

These concepts belong exclusively to the Vendor entity.

---

# 4. What the Vendor Entity Does NOT Own

The Vendor entity does not own:

- Purchase orders
- Invoices
- Parts specifications
- Pricing rules

Those belong to other components.

---

# 5. Business Rules

Examples include:

- Every vendor has a unique number.
- Vendor performance is tracked over time.
- Certifications must be valid.
- Payment terms are defined per vendor.

---

# 6. Lifecycle

Typical lifecycle:

```
Registered

↓

Approved

↓

Active

        ├── Suspended
        └── Deactivated
```

---

# 7. Anti-Patterns

The following are architectural violations.

## Procurement Ownership

```
Vendor

creates Purchase Orders
```

Purchase orders reference vendors but are owned by the Procurement Context.

---

## Finance Ownership

```
Vendor

manages Invoices
```

Invoices belong to the Finance Context.

---

# 8. Guiding Principle

The Vendor entity is the canonical representation of a supplier.

It owns:

- vendor details
- contact information
- performance metrics

It references, but never owns:

- purchase orders
- invoices
- parts
