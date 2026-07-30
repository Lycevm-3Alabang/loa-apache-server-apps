# Quotation Item
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Commercial
**Parent Aggregate:** Quotation
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Quotation Item represents an individual commercial offering within a quotation.

It identifies what is being offered, the commercial quantities, pricing snapshot, discounts, taxes, and totals for a single line.

The Quotation Item answers:

> **"What is this line proposing to the customer?"**

A Quotation Item cannot exist independently of a Quotation.

---

# 2. Responsibilities

The Quotation Item is responsible for:

- identifying the offered item
- maintaining line quantities
- maintaining pricing snapshots
- calculating line totals
- applying discounts
- applying taxes
- commercial validation

---

# 3. What the Quotation Item Owns

Examples include:

- Line Number
- Item Type
- Quantity
- Unit of Measure
- Unit Price Snapshot
- Discount Amount
- Discount Percentage
- Tax Amount
- Tax Percentage
- Line Total
- Remarks

These concepts belong exclusively to the Quotation Item.

---

# 4. What the Quotation Item Does NOT Own

The Quotation Item does not own:

- Catalog Items
- Service Definitions
- Labor Operations
- Pricing Rules
- Tax Rules
- Inventory
- Customers
- Vehicles

These belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Quotation Item owns:

- line state
- pricing snapshot
- calculated totals
- validation
- business invariants

The parent Quotation Aggregate owns the lifecycle of every Quotation Item.

---

# 6. Relationships

A Quotation Item may reference:

```
Catalog Item

Service Definition

Labor Operation

Vehicle
```

These references provide business context only.

Ownership remains with their respective Domains.

---

# 7. Business Rules

Examples include:

- Every quotation item belongs to exactly one quotation.
- Every quotation item has a unique line number within its quotation.
- Quantity must be greater than zero.
- Unit price is captured as a pricing snapshot.
- Discounts apply only to the quotation item.
- Taxes are calculated from the pricing snapshot.
- Line totals are derived values.
- Deleted quotation items remain traceable through quotation version history.

---

# 8. Pricing Snapshot

Quotation Items capture pricing at the time the quotation is calculated.

Examples include:

- Unit Price
- Discount
- Tax
- Currency
- Exchange Rate

Subsequent changes to Pricing Rules do not automatically modify existing quotations.

---

# 9. Validation

The Quotation Item validates:

- referenced item exists
- quantity is valid
- pricing snapshot is complete
- discounts comply with commercial policies
- calculated totals are internally consistent

---

# 10. Consumers

Quotation Items may be consumed by:

- Commercial
- Workshop
- Inventory
- Accounting
- Reporting

Consumers should treat Quotation Items as read-only commercial records.

---

# 11. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Quotation Item

defines Pricing Rules
```

Pricing Rules belong to the Pricing Domain.

---

## Catalog Ownership

```
Quotation Item

defines Service Definitions
```

Service Definitions belong to the Service Domain.

---

## Inventory Ownership

```
Quotation Item

reserves Stock
```

Inventory owns stock allocation.

---

## Workshop Ownership

```
Quotation Item

creates Work Orders
```

Repair execution belongs to the Workshop Business Context.

---

# 12. Future Evolution

Quotation Items may evolve to support:

- bundled items
- configurable products
- package pricing
- optional items
- alternative items
- promotional items
- financing line items

Future additions should continue to represent individual commercial line items without assuming ownership of shared business concepts.

---

# 13. Guiding Principle

The Quotation Item is the canonical representation of a single commercial line within a quotation.

It owns:

- the offered line
- commercial quantities
- pricing snapshots
- discounts
- taxes
- line totals

It references, but never owns:

- products
- services
- labor definitions
- pricing rules
- inventory

The Quotation Aggregate remains the consistency boundary for all Quotation Items.