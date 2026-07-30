# domains/automotive/pricing.md

# Pricing Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Pricing Domain defines how automotive products and services are valued.

It owns pricing models, pricing policies, pricing calculations, and pricing decisions.

The Pricing Domain determines the monetary value of offerings without owning the offerings themselves.

It provides a reusable pricing capability that can be consumed by multiple Business Contexts.

---

# 2. Responsibilities

The Pricing Domain is responsible for:

- pricing strategies
- price lists
- pricing rules
- pricing calculations
- pricing adjustments
- discounts
- markups
- taxes (when applicable)
- promotional pricing
- contract pricing
- effective dates
- pricing validation
- pricing events

---

# 3. What the Pricing Domain Owns

Examples include:

- Price
- Price List
- Price Rule
- Pricing Strategy
- Discount
- Markup
- Promotion
- Tax Rule
- Currency
- Effective Period

These concepts belong exclusively to the Pricing Domain.

---

# 4. What the Pricing Domain Does NOT Own

The Pricing Domain does not own:

- Products
- Services
- Labor Operations
- Inventory
- Suppliers
- Quotations
- Invoices
- Payments
- Purchase Orders

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Pricing Domain owns:

- pricing entities
- value objects
- calculations
- pricing policies
- validation
- pricing lifecycle
- domain events
- public contracts

Business Contexts consume pricing decisions but never implement pricing logic.

---

# 6. Core Concepts

The primary aggregate is:

```
Pricing Rule
```

Supporting concepts may include:

```
Price

Price List

Pricing Strategy

Discount

Markup

Promotion

Tax

Currency

Effective Period
```

---

# 7. Pricing Strategies

The Pricing Domain supports multiple pricing strategies.

Examples include:

```
Manual Price

Fixed Price

Formula Price

Vendor Cost + Markup

Contract Price

Fleet Pricing

Promotional Pricing

Dynamic Pricing
```

Business Contexts should request pricing.

They should never determine pricing themselves.

---

# 8. Relationships

Pricing may consume information from other Domains.

Examples:

```
Catalog

↓

Pricing
```

```
Labor

↓

Pricing
```

```
Vehicle

↓

Pricing
```

Pricing evaluates these inputs to produce pricing decisions.

Pricing never owns those Domains.

---

# 9. Business Rules

Examples include:

- Every pricing decision should be traceable.
- Price Lists may overlap only when explicitly allowed.
- Pricing Rules may have effective dates.
- Multiple pricing strategies may coexist.
- Pricing calculations should be deterministic.
- Discounts may be constrained by business policy.
- Pricing should remain independent of quotation workflows.

Business Contexts may select pricing strategies but never redefine pricing behavior.

---

# 10. Lifecycle

Pricing artifacts typically follow:

```
Draft

↓

Pending Approval

↓

Active

↓

Expired

↓

Archived
```

Business Contexts determine when transitions occur.

The Pricing Domain defines only the lifecycle.

---

# 11. Domain Events

Examples include:

```
PriceCalculated

PriceListActivated

PricingRuleCreated

PricingRuleUpdated

PromotionActivated

PromotionExpired
```

---

# 12. Public Contracts

The Pricing Domain should expose stable contracts for:

- calculating prices
- validating pricing
- retrieving active prices
- retrieving price lists
- retrieving applicable discounts
- publishing pricing events

Business Contexts consume pricing services rather than implementing pricing calculations.

---

# 13. Consumers

Expected consumers include:

```
Commercial

Workshop

Inventory

Procurement

Fleet

Finance
```

The Pricing Domain remains unaware of these consumers.

---

# 14. Anti-Patterns

The following are architectural violations.

## Catalog Determines Price

```
Catalog

stores selling price
```

Catalog defines offerings.

Pricing defines value.

---

## Commercial Calculates Prices

```
Quotation

calculates totals
```

Commercial requests pricing.

Pricing performs calculations.

---

## Inventory Calculates Selling Price

```
Inventory

applies markup
```

Pricing owns pricing policy.

---

## Vendor API Determines Architecture

```
Pricing

depends directly on Vendor API
```

Vendor integrations belong to Platform Services or Integration adapters.

Pricing consumes pricing data through stable contracts.

---

# 15. Future Evolution

The Pricing Domain may evolve to support:

- OEM pricing feeds
- supplier pricing APIs
- AI-assisted pricing
- regional pricing
- customer-specific pricing
- contract pricing
- loyalty pricing
- seasonal pricing
- demand-based pricing

The Pricing Domain should evolve without requiring changes to Business Contexts.

---

# 16. Guiding Principle

The Pricing Domain answers one question:

> **"What is the correct price for this offering under these conditions?"**

It does not decide:

- whether the item should be sold
- whether the customer accepts the quote
- whether inventory exists
- whether work is performed

Those decisions belong to Business Contexts.

Pricing provides pricing knowledge.

Business Contexts provide business decisions.