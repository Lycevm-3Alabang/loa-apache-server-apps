# Business Contexts
## Business Context Architecture

**Version:** 1.0  
**Status:** Approved  
**Layer:** Business Context  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the role of Business Contexts within the platform architecture.

Business Contexts implement business capabilities by orchestrating Platform Kernels and Industry Domains into complete business workflows.

A Business Context owns application-specific business processes, transactions, user interactions, and orchestration logic.

Individual Business Context specifications (for example, `commercial/README.md` and `crm/README.md`) inherit the architectural rules defined in this document.

---

# 2. What is a Business Context?

A Business Context is an autonomous business capability that delivers a complete business function.

A Business Context:

- owns business workflows
- owns business transactions
- owns process orchestration
- composes Platform Kernels and Industry Domains
- exposes stable public contracts
- encapsulates application-specific business rules

A Business Context is **not**:

- a Platform Kernel
- an Industry Domain
- an infrastructure component
- a shared library
- a user interface

---

# 3. Responsibilities

Business Contexts are responsible for answering business process questions.

Examples include:

| Business Context | Responsibility |
|------------------|---------------|
| CRM | How do we manage customer relationships? |
| Commercial | How do we prepare and manage quotations and sales? |
| Workshop | How is automotive work executed? |
| Inventory | How are stock movements managed? |
| Fleet | How are fleets operated and maintained? |
| Procurement | How are goods and services acquired? |
| Accounting | How are financial transactions recorded? |
| Reporting | How is operational information presented? |

Each Business Context owns one business capability.

---

# 4. Design Characteristics

Every Business Context must satisfy the following characteristics.

## Autonomous

A Business Context owns its own workflows, business rules, and transaction boundaries.

---

## Composable

Business Contexts build solutions by composing Platform Kernels and Industry Domains.

They should never duplicate concepts already owned elsewhere.

---

## Business-Oriented

Business Contexts implement business processes rather than foundational business knowledge.

---

## Independent

Business Contexts should minimize direct dependencies on other Business Contexts.

Communication should occur through published contracts, APIs, or domain events.

---

## Evolvable

Business Contexts should evolve independently without requiring changes to Platform Kernels or Industry Domains.

---

# 5. Ownership Model

A Business Context owns:

- business workflows
- business transactions
- aggregates
- commands
- application-specific policies
- orchestration
- process state
- user interactions
- public APIs
- integration events

A Business Context does not own foundational business concepts already defined elsewhere.

Example

```
Commercial

owns

Quotation
```

Commercial references:

```
Party

Vehicle

Pricing

Service
```

Commercial never redefines these concepts.

---

# 6. Dependency Rules

Business Contexts may depend on:

- Platform Kernels
- Industry Domains
- Shared Platform Services

Business Contexts should avoid direct dependencies on other Business Contexts.

When collaboration is required, use:

- published APIs
- domain events
- integration contracts

Circular dependencies are prohibited.

---

# 7. Relationship Principles

Business Contexts orchestrate business knowledge.

They do not own it.

Example

```
Commercial

↓

Party

Vehicle

Pricing

Service
```

Commercial creates quotations.

It does not define customers, vehicles, pricing rules, or service definitions.

---

# 8. Transaction Boundaries

Every Business Context owns its own transaction boundaries.

Business transactions should not span multiple Business Contexts.

Cross-context collaboration should occur through eventual consistency where appropriate.

---

# 9. Public Contracts

Every Business Context should expose stable contracts for external consumers.

Examples include:

- APIs
- Commands
- Queries
- Events
- Integration Messages

Consumers should interact through contracts rather than internal implementations.

---

# 10. Extension Rules

New Business Contexts should be introduced only when:

- a distinct business capability exists
- ownership cannot be assigned to an existing Business Context
- the capability represents a cohesive workflow
- independent evolution is expected

Business Contexts should not be created merely to organize source code.

---

# 11. Anti-Patterns

The following are considered architectural violations.

## Duplicate Ownership

```
Commercial

owns Customer
```

Customer belongs to the Party Kernel.

---

## Domain Duplication

```
Workshop

defines Vehicle
```

Vehicle belongs to the Automotive Domain.

---

## Workflow Leakage

```
Vehicle

creates Quotations
```

Business workflows belong to Business Contexts.

---

## Tight Coupling

```
Commercial

↓

Workshop

↓

Inventory

↓

Commercial
```

Circular dependencies between Business Contexts are prohibited.

---

# 12. Business Context Catalog

The platform currently defines the following Business Contexts.

```
Business Contexts

├── CRM
├── Commercial
├── Workshop
├── Inventory
├── Fleet
├── Procurement
├── Accounting
└── Reporting
```

Additional Business Contexts should be introduced only after architectural review.

---

# 13. Guiding Principle

Business Contexts transform reusable business knowledge into executable business capabilities.

They compose Platform Kernels and Industry Domains to deliver complete business workflows while preserving clear ownership boundaries.

When designing new functionality, always ask:

> **Does this belong in an existing Business Context, or is it foundational knowledge that belongs in a Platform Kernel or Industry Domain instead?**