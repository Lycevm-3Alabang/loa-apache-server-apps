# CRM Business Context
## Business Context Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Business Context  
**Business Capability:** Customer Relationship Management (CRM)  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The CRM Business Context manages the complete customer relationship lifecycle before, during, and after commercial engagements.

It owns leads, prospects, opportunities, customer interactions, activities, communications, and relationship history.

The CRM Business Context answers:

> **"How do we build, maintain, and strengthen customer relationships?"**

It does not own quotations, pricing, inventory, workshop operations, or financial transactions.

---

# 2. Responsibilities

The CRM Business Context is responsible for:

- lead management
- prospect management
- opportunity management
- customer engagement
- customer communications
- relationship history
- customer activities
- sales pipeline
- follow-up management
- CRM events

---

# 3. What the CRM Business Context Owns

Examples include:

- Lead
- Prospect
- Opportunity
- Opportunity Stage
- Customer Interaction
- Communication
- Activity
- Follow-up
- Sales Pipeline
- Customer Timeline

These concepts belong exclusively to the CRM Business Context.

---

# 4. What the CRM Business Context Does NOT Own

The CRM Business Context does not own:

- Customers
- Organizations
- Vehicles
- Service Definitions
- Catalog Items
- Pricing Rules
- Quotations
- Work Orders
- Inventory
- Invoices
- Payments

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

The CRM Business Context owns:

- relationship workflows
- customer engagement processes
- pipeline management
- activities
- interactions
- communications
- lifecycle rules
- business validation
- domain events
- public contracts

It references shared business concepts without redefining them.

---

# 6. Core Aggregates

Primary aggregates include:

```
Lead

Prospect

Opportunity
```

Supporting aggregates include:

```
Customer Interaction

Communication

Activity

Follow-up
```

---

# 7. Relationships

The CRM Business Context references:

```
Party

Organization

Vehicle

Document

Workflow

Activity
```

CRM composes these concepts to manage customer relationships.

Ownership remains with their respective Platform Kernels and Industry Domains.

---

# 8. Business Rules

Examples include:

- Every lead has an identifiable source.
- A lead may become a prospect.
- A prospect may become an opportunity.
- Every opportunity belongs to a customer.
- Customer interactions are chronological.
- Activities are associated with a lead, prospect, opportunity, or customer.
- Closed opportunities remain part of the customer history.
- CRM never creates quotations directly.

---

# 9. Lifecycle

Typical relationship lifecycle:

```
Lead

↓

Prospect

↓

Opportunity

↓

Won
        │
        ├── Lost
        └── Cancelled
```

Winning an opportunity may initiate a Commercial process.

---

# 10. Domain Events

Examples include:

```
LeadCreated

LeadQualified

ProspectCreated

OpportunityCreated

OpportunityWon

OpportunityLost

ActivityRecorded

CommunicationLogged
```

---

# 11. Public Contracts

The CRM Business Context should expose stable contracts for:

- managing leads
- qualifying prospects
- managing opportunities
- recording customer interactions
- scheduling follow-ups
- retrieving customer timelines
- publishing CRM events

---

# 12. Consumers

Expected consumers include:

- Commercial
- Reporting
- Customer Portal
- Marketing
- Fleet

The CRM Business Context remains unaware of implementation details within these consumers.

---

# 13. Integrations

The CRM Business Context composes information from:

```
Party
        │
Organization
        │
Vehicle
        │
Document
        │
Workflow
        ▼
CRM
        │
        ▼
Customer Relationships
```

CRM never owns or duplicates these concepts.

---

# 14. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
CRM

owns Customer
```

Customer belongs to the Party Kernel.

---

## Quotation Ownership

```
CRM

owns Quotation
```

Quotation belongs to the Commercial Business Context.

---

## Pricing Ownership

```
CRM

defines Pricing Rules
```

Pricing belongs to the Pricing Domain.

---

## Workshop Ownership

```
CRM

creates Work Orders
```

Workshop owns repair execution.

---

## Inventory Ownership

```
CRM

manages Stock
```

Inventory owns stock management.

---

# 15. Future Evolution

The CRM Business Context may evolve to support:

- marketing campaigns
- customer segmentation
- lead scoring
- AI-assisted opportunity qualification
- customer satisfaction management
- customer loyalty programs
- omnichannel communications
- customer preference management

Future additions should continue to represent customer relationship management rather than commercial transactions or operational execution.

---

# 16. Guiding Principle

The CRM Business Context is the canonical owner of customer relationships.

It defines:

- how leads are managed
- how prospects are qualified
- how opportunities progress
- how customer interactions are recorded
- how relationships evolve over time

It does not define:

- who the customer is
- what a vehicle is
- how quotations are prepared
- how pricing is determined
- how work is executed

Those responsibilities belong to Platform Kernels, Industry Domains, or other Business Contexts.