# Negotiation
## Business Capability Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Commercial
**Capability:** Negotiation
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Negotiation capability governs the iterative refinement of commercial proposals between the business and the customer.

It owns negotiation sessions, proposals, counter-proposals, negotiation outcomes, and negotiation history.

The Negotiation capability answers:

> **"How does a quotation evolve until a commercial agreement is reached?"**

It does not own quotations, customers, pricing rules, or approval workflows.

---

# 2. Responsibilities

The Negotiation capability is responsible for:

- negotiation sessions
- proposal revisions
- counter-offers
- negotiation history
- negotiation outcomes
- negotiation validation
- negotiation lifecycle
- negotiation events

---

# 3. What Negotiation Owns

Examples include:

- Negotiation Session
- Proposal Revision
- Counter Offer
- Negotiation Status
- Negotiation Outcome
- Negotiation History
- Negotiation Notes

These concepts belong exclusively to the Negotiation capability.

---

# 4. What Negotiation Does NOT Own

The Negotiation capability does not own:

- Quotations
- Customers
- Pricing Rules
- Discounts
- Vehicles
- Services
- Catalog Items
- Commercial Policies

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

Negotiation owns:

- negotiation lifecycle
- negotiation history
- negotiation decisions
- negotiation validation
- business invariants
- domain events

Negotiations always occur within the context of a Quotation.

---

# 6. Relationships

Negotiation references:

```
Quotation

Party

Discount

Approval
```

These references provide business context only.

Ownership remains with their respective aggregates and domains.

---

# 7. Business Rules

Examples include:

- Every negotiation belongs to one quotation.
- A negotiation may contain multiple proposal revisions.
- Counter-offers must reference a previous proposal.
- Negotiation history is immutable.
- Accepted negotiations conclude the negotiation session.
- Cancelled quotations terminate active negotiations.

---

# 8. Lifecycle

Typical lifecycle:

```
Started

↓

In Progress

↓

Agreement Reached
        │
        ├── Cancelled
        ├── Expired
        └── Failed
```

---

# 9. Domain Events

Examples include:

```
NegotiationStarted

CounterOfferSubmitted

ProposalRevised

AgreementReached

NegotiationCancelled

NegotiationExpired
```

---

# 10. Public Contracts

The Negotiation capability should expose stable contracts for:

- starting negotiations
- submitting counter-offers
- revising proposals
- retrieving negotiation history
- concluding negotiations
- publishing negotiation events

---

# 11. Consumers

Negotiation information may be consumed by:

- Commercial
- CRM
- Reporting
- Customer Portal

Consumers interact through published contracts.

---

# 12. Anti-Patterns

The following are architectural violations.

## Quotation Ownership

```
Negotiation

owns Quotation
```

Quotation remains the Aggregate Root.

---

## Pricing Ownership

```
Negotiation

defines Pricing Rules
```

Pricing belongs to the Pricing Domain.

---

## Customer Ownership

```
Negotiation

owns Customer
```

Customer belongs to the Party Kernel.

---

## Approval Ownership

```
Negotiation

approves Quotations
```

Approval decisions belong to the Approval capability.

---

# 13. Future Evolution

The Negotiation capability may evolve to support:

- collaborative negotiations
- digital negotiations
- customer self-service negotiations
- AI-assisted negotiations
- expiration policies
- automated proposal recommendations

Future additions should continue to represent commercial negotiations without assuming ownership of quotations or pricing.

---

# 14. Guiding Principle

The Negotiation capability is the canonical representation of commercial discussions.

It defines:

- proposal revisions
- counter-offers
- negotiation history
- negotiation outcomes
- commercial agreement

It does not define:

- quotations
- pricing rules
- customers
- approval decisions

Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.