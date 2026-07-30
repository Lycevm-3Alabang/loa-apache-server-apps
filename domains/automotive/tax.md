# domains/automotive/tax.md

# Tax Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Tax Domain defines the canonical representation of taxation within the Automotive Domain Pack.

It owns tax rules, tax calculations, tax exemptions, tax jurisdictions, and tax reporting requirements.

The Tax Domain answers:

> **"What tax applies to this transaction under these conditions?"**

It does not determine pricing, commercial terms, invoicing, or payment processing.

---

# 2. Responsibilities

The Tax Domain is responsible for:

- tax rules
- tax rates
- tax calculations
- tax exemptions
- tax jurisdictions
- tax categories
- tax validation
- tax lifecycle
- tax domain events

---

# 3. What the Tax Domain Owns

Examples include:

- Tax Rule
- Tax Rate
- Tax Category
- Tax Exemption
- Tax Jurisdiction
- Tax Calculation
- Tax Code
- Tax Certificate

These concepts belong exclusively to the Tax Domain.

---

# 4. What the Tax Domain Does NOT Own

The Tax Domain does not own:

- Products
- Services
- Pricing Rules
- Quotations
- Invoices
- Payments
- Inventory
- Customers
- Suppliers

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Tax Domain owns:

- entities
- value objects
- tax rules
- calculation logic
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts consume tax decisions but never implement tax logic.

---

# 6. Core Concepts

The primary aggregate is:

```
Tax Rule
```

Supporting concepts may include:

```
Tax Rate

Tax Category

Tax Exemption

Tax Jurisdiction

Tax Code

Tax Certificate
```

---

# 7. Relationships

Tax may consume information from other Domains.

Examples:

```
Catalog

↓

Tax
```

```
Pricing

↓

Tax
```

```
Vehicle

↓

Tax
```

Tax evaluates these inputs to produce tax decisions.

Tax never owns those Domains.

---

# 8. Business Rules

Examples include:

- Every tax rule has a unique identity.
- Tax rates are determined by jurisdiction.
- Tax exemptions must be validated before application.
- Tax calculations must be deterministic.
- Tax rules may have effective dates.
- Multiple tax rules may apply to a single transaction.
- Tax categories classify taxable items.
- Tax rules remain independent of pricing decisions.

---

# 9. Lifecycle

Tax artifacts typically follow:

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

The Tax Domain defines only the lifecycle.

---

# 10. Domain Events

Examples include:

```
TaxRuleCreated

TaxRuleUpdated

TaxRateChanged

TaxExemptionGranted

TaxExemptionRevoked

TaxRuleExpired
```

---

# 11. Public Contracts

The Tax Domain should expose stable contracts for:

- calculating tax
- validating tax exemptions
- retrieving applicable tax rates
- retrieving tax categories
- publishing tax events

Business Contexts consume tax services rather than implementing tax calculations.

---

# 12. Consumers

Expected consumers include:

```
Commercial

Workshop

Inventory

Procurement

Finance
```

The Tax Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Tax

determines selling price
```

Tax determines tax obligations.

Pricing determines value.

---

## Commercial Ownership

```
Tax

creates invoices
```

Invoicing belongs to the Finance Business Context.

---

## Inventory Ownership

```
Tax

tracks taxable inventory
```

Inventory belongs to the Inventory Business Context.

---

## Direct Vendor Dependency

```
Tax

depends directly on Tax Authority API
```

External integrations belong to Platform Services.

Tax consumes tax data through stable contracts.

---

# 14. Future Evolution

The Tax Domain may evolve to support:

- multi-jurisdiction taxation
- automated tax filing
- real-time tax calculation
- international tax rules
- tax incentive tracking
- digital tax reporting
- compliance automation
- AI-assisted tax classification

The Tax Domain should evolve without requiring changes to Business Contexts.

---

# 15. Guiding Principle

The Tax Domain answers one question:

> **"What tax applies to this transaction under these conditions?"**

It does not decide:

- what the selling price should be
- whether the transaction should proceed
- how payment should be collected
- how invoices should be formatted

Those decisions belong to other Domains and Business Contexts.

Tax provides tax knowledge.

Business Contexts provide business decisions.
