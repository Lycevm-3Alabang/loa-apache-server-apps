# Commercial Business Context
## Business Context Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Business Context  
**Business Capability:** Commercial  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Commercial Business Context manages the complete commercial lifecycle between the business and its customers.

It owns quotations, commercial approvals, customer offers, and commercial transactions from initial request through customer acceptance.

The Commercial Business Context answers:

> **"How do we transform customer demand into commercial transactions?"**

It does not own customers, vehicles, pricing models, services, or workshop execution.

---

# 2. Responsibilities

The Commercial Business Context is responsible for:

- quotation management
- quotation lifecycle
- quotation approval
- customer offers
- commercial negotiations
- commercial validation
- quotation versioning
- commercial events

---

# 3. What the Commercial Business Context Owns

Examples include:

- Quotation
- Quotation Item
- Quotation Version
- Quotation Approval
- Commercial Offer
- Commercial Terms
- Commercial Discount
- Customer Acceptance

These concepts belong exclusively to the Commercial Business Context.

---

# 4. What the Commercial Business Context Does NOT Own

The Commercial Business Context does not own:

- Customers
- Organizations
- Vehicles
- Service Definitions
- Catalog Items
- Pricing Rules
- Labor Standards
- Appointments
- Work Orders
- Inventory
- Invoices
- Payments

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Commercial Business Context owns:

- business workflows
- aggregates
- commands
- business policies
- validation
- transaction boundaries
- lifecycle rules
- domain events
- public contracts

It references shared concepts without redefining them.

---

# 6. Core Aggregates

Primary aggregates include:

```
Quotation
```

Supporting aggregates include:

```
Quotation Version

Quotation Item

Quotation Approval

Commercial Offer
```

---

# 7. Relationships

The Commercial Business Context references:

```
Party

Vehicle

Catalog

Service

Pricing

Labor

Scheduling

Document

Workflow
```

Commercial composes these concepts to produce quotations.

Ownership remains with their respective Platform Kernels and Industry Domains.

---

# 8. Business Rules

Examples include:

- Every quotation belongs to a customer.
- Every quotation references one or more vehicles when applicable.
- Every quotation contains one or more quotation items.
- Quotation items reference catalog items or service definitions.
- Pricing is obtained from the Pricing Domain.
- Commercial discounts follow commercial policies.
- Quotations are versioned.
- Approved quotations become immutable.
- Accepted quotations may initiate downstream business processes.

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
        ├── Expired
        ├── Rejected
        └── Cancelled
```

---

# 10. Domain Events

Examples include:

```
QuotationCreated

QuotationUpdated

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

The Commercial Business Context should expose stable contracts for:

- creating quotations
- updating quotations
- retrieving quotations
- validating quotations
- approving quotations
- accepting quotations
- publishing commercial events

---

# 12. Consumers

Expected consumers include:

- CRM
- Workshop
- Inventory
- Accounting
- Reporting
- Customer Portal

The Commercial Business Context remains unaware of implementation details within these consumers.

---

# 13. Integrations

The Commercial Business Context composes information from:

```
Party
        │
Vehicle │
Catalog │
Pricing │
Service │
Labor   │
        ▼
Commercial
        │
        ▼
Quotation
```

Commercial never owns or duplicates these concepts.

---

# 14. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Commercial

owns Customer
```

Customer belongs to the Party Kernel.

---

## Vehicle Ownership

```
Commercial

defines Vehicle
```

Vehicle belongs to the Vehicle Domain.

---

## Pricing Ownership

```
Commercial

calculates Pricing Rules
```

Pricing belongs to the Pricing Domain.

---

## Workshop Ownership

```
Commercial

creates Work Orders
```

Workshop owns repair execution.

---

## Inventory Ownership

```
Commercial

manages Stock
```

Inventory owns stock management.

---

# 15. Future Evolution

The Commercial Business Context may evolve to support:

- sales orders
- customer approvals
- digital signatures
- promotional campaigns
- financing quotations
- trade-in valuations
- insurance quotations
- multi-currency quotations
- configurable commercial policies

Future additions should continue to represent commercial workflows rather than foundational business knowledge.

---

# 16. Guiding Principle

The Commercial Business Context is the canonical owner of commercial transactions.

It defines:

- how quotations are created
- how quotations evolve
- how quotations are approved
- how quotations are accepted

It does not define:

- who the customer is
- what a vehicle is
- what services exist
- how pricing is determined
- how work is executed

Those responsibilities belong to Platform Kernels, Industry Domains, or other Business Contexts.