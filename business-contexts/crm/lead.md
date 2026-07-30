# Lead
## Aggregate Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** CRM
**Aggregate:** Lead
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Lead aggregate represents an individual or organization that has expressed interest in the business but has not yet been qualified.

It is the entry point of the customer relationship lifecycle and serves as the primary aggregate for lead management within the CRM Business Context.

The Lead aggregate answers:

> **"Who is this potential customer, and should we pursue the opportunity?"**

It does not own customers, quotations, pricing, or commercial transactions.

---

# 2. Responsibilities

The Lead aggregate is responsible for:

- lead registration
- lead qualification
- lead assignment
- lead status
- lead source
- lead scoring
- lead history
- lead conversion
- lead events

---

# 3. What the Lead Aggregate Owns

Examples include:

- Lead
- Lead Number
- Lead Status
- Lead Source
- Lead Owner
- Qualification Status
- Qualification Score
- Estimated Value
- Estimated Close Date
- Qualification Notes

The Lead aggregate owns these concepts completely.

---

# 4. What the Lead Aggregate Does NOT Own

The Lead aggregate does not own:

- Customer
- Organization
- Opportunity
- Quotations
- Vehicles
- Pricing
- Services
- Work Orders
- Inventory
- Payments

These belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The Lead aggregate owns:

- aggregate state
- lifecycle
- qualification
- business validation
- business invariants
- domain events

The Lead aggregate is the consistency boundary for all lead operations.

---

# 6. Aggregate Structure

```
Lead
│
├── Contact Information
├── Qualification
├── Assignment
├── Notes
└── Attachments
```

All child entities exist only within the lifetime of a Lead.

---

# 7. Relationships

The Lead aggregate references:

```
Party

Organization

Vehicle

Document
```

These references provide business context only.

Ownership remains with their respective Platform Kernels and Industry Domains.

---

# 8. Business Rules

Examples include:

- Every lead has a unique lead number.
- Every lead originates from a lead source.
- Every lead has an owner.
- A lead may have multiple contacts.
- A lead may reference one or more vehicles of interest.
- A lead may be qualified into a prospect.
- A converted lead becomes read-only.
- Closed leads cannot be modified except through approved administrative processes.

---

# 9. Lifecycle

Typical lifecycle:

```
New

↓

Assigned

↓

Qualified
        │
        ├── Disqualified
        ├── Converted
        └── Closed
```

Converted leads initiate the Prospect lifecycle.

---

# 10. Domain Events

Examples include:

```
LeadCreated

LeadAssigned

LeadQualified

LeadDisqualified

LeadConverted

LeadClosed
```

---

# 11. Public Contracts

The Lead aggregate should expose stable contracts for:

- creating leads
- updating leads
- assigning leads
- qualifying leads
- converting leads
- closing leads
- retrieving leads
- publishing lead events

---

# 12. Consumers

Lead information may be consumed by:

- CRM
- Reporting
- Marketing
- Commercial

Consumers interact through published contracts.

---

# 13. Aggregate Invariants

The following invariants must always hold:

- A lead always has an owner.
- A lead always has a source.
- Converted leads cannot be converted again.
- Disqualified leads cannot become qualified.
- Every state transition must be valid.
- Historical qualification information is preserved.

These invariants are enforced by the aggregate root.

---

# 14. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Lead

owns Customer
```

Customer belongs to the Party Kernel.

---

## Opportunity Ownership

```
Lead

owns Opportunity
```

Opportunity is a separate CRM aggregate.

---

## Commercial Ownership

```
Lead

creates Quotations
```

Commercial owns quotations.

---

## Pricing Ownership

```
Lead

defines Pricing Rules
```

Pricing belongs to the Pricing Domain.

---

# 15. Future Evolution

The Lead aggregate may evolve to support:

- AI lead scoring
- duplicate detection
- lead enrichment
- social profile integration
- campaign attribution
- automated qualification
- geographic routing
- SLA monitoring

Future additions should continue to represent lead management without assuming ownership of commercial or operational concepts.

---

# 16. Guiding Principle

The Lead aggregate is the canonical representation of an unqualified sales opportunity.

It owns:

- lead identity
- qualification state
- assignment
- lead history
- conversion

It references, but never owns:

- customers
- quotations
- pricing
- vehicles
- commercial transactions

Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.