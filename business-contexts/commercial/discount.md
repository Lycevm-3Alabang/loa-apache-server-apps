# Discount
## Business Capability Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Commercial
**Capability:** Discount
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Discount capability defines how commercial discounts are represented, validated, approved, and applied within the Commercial Business Context.

It owns discount requests, discount values, discount justification, approval requirements, and discount application.

The Discount capability answers:

> **"What commercial discount is being applied, and why?"**

It does not own pricing rules, customer information, quotations, or taxation.

---

# 2. Responsibilities

The Discount capability is responsible for:

- discount requests
- discount calculation inputs
- discount validation
- discount approval requirements
- discount application
- discount justification
- discount lifecycle
- discount events

---

# 3. What Discount Owns

Examples include:

- Discount
- Discount Type
- Discount Value
- Discount Percentage
- Discount Amount
- Discount Reason
- Discount Justification
- Discount Status

These concepts belong exclusively to the Discount capability.

---

# 4. What Discount Does NOT Own

The Discount capability does not own:

- Pricing Rules
- Price Lists
- Tax Rules
- Quotations
- Customers
- Services
- Catalog Items
- Commercial Policies

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Discount capability owns:

- discount lifecycle
- validation
- discount application
- approval requirements
- business invariants
- domain events

Discounts are always applied within a Quotation.

---

# 6. Relationships

Discount references:

```
Quotation

Quotation Item

Pricing
```

These references provide business context only.

Ownership remains with their respective aggregates and domains.

---

# 7. Business Rules

Examples include:

- Every discount belongs to a quotation or quotation item.
- Discounts may be fixed amounts or percentages.
- Discount values must never exceed configured commercial limits.
- Discounts require justification when exceeding policy thresholds.
- Discounts requiring approval cannot become effective until approved.
- Discount calculations must preserve quotation consistency.

---

# 8. Lifecycle

Typical lifecycle:

```
Requested

↓

Validated

↓

Approved

↓

Applied
        │
        ├── Rejected
        └── Cancelled
```

---

# 9. Domain Events

Examples include:

```
DiscountRequested

DiscountValidated

DiscountApproved

DiscountApplied

DiscountRejected

DiscountCancelled
```

---

# 10. Public Contracts

The Discount capability should expose stable contracts for:

- requesting discounts
- validating discounts
- approving discounts
- applying discounts
- retrieving discount history
- publishing discount events

---

# 11. Consumers

Discount information may be consumed by:

- Commercial
- CRM
- Accounting
- Reporting

Consumers interact through published contracts.

---

# 12. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Discount

defines Pricing Rules
```

Pricing belongs to the Pricing Domain.

---

## Customer Ownership

```
Discount

owns Customer
```

Customer belongs to the Party Kernel.

---

## Quotation Ownership

```
Discount

owns Quotation
```

Quotation remains the Aggregate Root.

---

## Tax Ownership

```
Discount

calculates Taxes
```

Tax calculation belongs to the appropriate Tax capability.

---

# 13. Future Evolution

The Discount capability may evolve to support:

- promotional discounts
- campaign discounts
- loyalty discounts
- volume discounts
- bundle discounts
- manufacturer incentives
- coupon codes
- configurable discount policies

Future additions should continue to represent commercial discounting without assuming ownership of pricing or taxation.

---

# 14. Guiding Principle

The Discount capability is the canonical representation of commercial discounts.

It defines:

- what discount is applied
- how much is applied
- why it is applied
- who approved it
- when it becomes effective

It does not define:

- pricing rules
- customers
- quotations
- taxes

Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.