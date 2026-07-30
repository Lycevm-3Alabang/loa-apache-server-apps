# Quotation
## Aggregate Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Business Context  
**Business Context:** Commercial  
**Aggregate:** Quotation  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Quotation aggregate represents a commercial proposal offered to a customer.

It is the central aggregate of the Commercial Business Context and owns the complete commercial proposal, including its lifecycle, pricing snapshot, customer acceptance, and supporting commercial information.

The Quotation aggregate answers:

> **"What is being proposed to the customer?"**

It does not own customers, vehicles, catalog items, pricing rules, service definitions, or inventory.

---

# 2. Responsibilities

The Quotation aggregate is responsible for:

- quotation creation
- quotation lifecycle
- quotation composition
- commercial validation
- pricing snapshots
- quotation totals
- quotation approval state
- quotation versioning
- customer acceptance
- quotation events

---

# 3. What the Quotation Aggregate Owns

Examples include:

- Quotation
- Quotation Number
- Quotation Status
- Valid Until
- Currency
- Exchange Rate Snapshot
- Pricing Snapshot
- Commercial Terms
- Totals
- Customer Acceptance

The Quotation aggregate owns these concepts completely.

---

# 4. What the Quotation Aggregate Does NOT Own

The Quotation aggregate does not own:

- Customer
- Organization
- Vehicle
- Catalog Items
- Service Definitions
- Pricing Rules
- Labor Standards
- Inventory
- Work Orders
- Appointments
- Invoices
- Payments

These belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Quotation aggregate owns:

- aggregate state
- business invariants
- child entities
- lifecycle
- validation
- commercial calculations
- domain events

The Quotation aggregate is the consistency boundary for all quotation operations.

---

# 6. Aggregate Structure

```
Quotation
│
├── Quotation Item
├── Quotation Item
├── Approval History
├── Acceptance
├── Attachments
└── Notes
```

All child entities exist only within the lifetime of a Quotation.

---

# 7. Relationships

The Quotation aggregate references:

```
Party

Vehicle

Catalog

Service

Pricing

Labor

Scheduling

Document
```

These references provide business context.

Ownership remains with their respective Platform Kernels and Industry Domains.

---

# 8. Business Rules

Examples include:

- Every quotation has a unique quotation number.
- Every quotation belongs to exactly one customer.
- A quotation contains one or more quotation items.
- Every quotation item references a valid service or catalog item.
- Pricing is captured as a snapshot when the quotation is calculated.
- Commercial totals are derived from quotation items.
- Accepted quotations become immutable.
- Expired quotations cannot be accepted.
- Cancelled quotations cannot be modified.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Under Review

↓

Approved

↓

Presented

↓

Accepted
        │
        ├── Rejected
        ├── Expired
        └── Cancelled
```

State transitions are governed by Commercial policies.

---

# 10. Domain Events

Examples include:

```
QuotationCreated

QuotationUpdated

QuotationCalculated

QuotationSubmitted

QuotationApproved

QuotationPresented

QuotationAccepted

QuotationRejected

QuotationExpired

QuotationCancelled
```

---

# 11. Public Contracts

The Quotation aggregate should expose stable contracts for:

- creating quotations
- updating quotations
- calculating totals
- validating quotations
- approving quotations
- presenting quotations
- accepting quotations
- cancelling quotations

---

# 12. Consumers

Quotation information may be consumed by:

- CRM
- Workshop
- Inventory
- Accounting
- Reporting
- Customer Portal

Consumers interact through published contracts.

---

# 13. Aggregate Invariants

The following invariants must always hold:

- A quotation always has a customer.
- A quotation always has at least one quotation item.
- Totals always equal the sum of quotation items.
- Currency remains fixed after approval.
- Pricing snapshots remain immutable after approval.
- Accepted quotations cannot be modified.
- Every state transition must be valid.

These invariants are enforced by the aggregate root.

---

# 14. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Quotation

owns Customer
```

Customer belongs to the Party Kernel.

---

## Vehicle Ownership

```
Quotation

defines Vehicle
```

Vehicle belongs to the Vehicle Domain.

---

## Pricing Ownership

```
Quotation

defines Pricing Rules
```

Pricing rules belong to the Pricing Domain.

---

## Inventory Ownership

```
Quotation

reserves Stock
```

Inventory owns stock allocation.

---

## Workshop Ownership

```
Quotation

creates Work Orders
```

Work execution belongs to the Workshop Business Context.

---

# 15. Future Evolution

The Quotation aggregate may evolve to support:

- configurable quotation templates
- financing quotations
- insurance quotations
- trade-in valuations
- digital signatures
- customer negotiations
- multi-currency quotations
- configurable commercial policies

Future additions should continue to represent commercial transactions without assuming ownership of shared business concepts.

---

# 16. Guiding Principle

The Quotation aggregate is the canonical representation of a commercial proposal.

It owns:

- the commercial offer
- its lifecycle
- its composition
- its pricing snapshot
- its approval state
- its acceptance

It references, but never owns:

- customers
- vehicles
- services
- pricing rules
- inventory
- appointments

Those responsibilities remain with Platform Kernels, Industry Domains, and other Business Contexts.