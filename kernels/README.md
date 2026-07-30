# Automotive Business Platform
## Platform Kernels Specification

**Version:** 1.1  
**Status:** Approved  
**Layer:** Platform Kernel  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the role, responsibilities, and architectural rules governing Platform Kernels within the Automotive Business Platform.

Platform Kernels represent the most stable architectural layer of the platform. They establish the canonical business concepts upon which all higher layers are built.

Every Platform Kernel owns a single foundational business concept with a clearly defined ownership boundary.

Individual kernel specifications (e.g. `party.md`, `workflow.md`) inherit the principles defined in this document.

---

# 2. Position in the Architecture

```
+------------------------------------------------------+
|                Product Assemblies                    |
+------------------------------------------------------+
                       ▲
+------------------------------------------------------+
|                Business Contexts                     |
+------------------------------------------------------+
                       ▲
+------------------------------------------------------+
|                Platform Services                     |
+------------------------------------------------------+
                       ▲
+------------------------------------------------------+
|              Core Business Domains                   |
+------------------------------------------------------+
                       ▲
+------------------------------------------------------+
|                Platform Kernels                      |
+------------------------------------------------------+
```

Platform Kernels are the foundation of the platform.

Every higher architectural layer depends upon them.

Platform Kernels never depend on higher layers.

---

# 3. What is a Platform Kernel?

A Platform Kernel is a foundational business capability shared across the entire platform.

A Platform Kernel:

- owns canonical business concepts
- owns the lifecycle of those concepts
- exposes stable public contracts
- publishes business events
- is reusable across every Business Context
- evolves slowly
- remains independent of business workflows

A Platform Kernel is **not**:

- a utility library
- an infrastructure component
- a Platform Service
- a Business Context
- an application feature
- a complete business process

---

# 4. Architectural Philosophy

Every Platform Kernel exists to answer exactly one foundational business question.

| Kernel | Foundational Question |
|----------|----------------------|
| Identity | Who is interacting with the platform? |
| Organization | Which business entity owns this information? |
| Party | Who does the business interact with? |
| Workflow | What lifecycle state is this object currently in? |
| Document | What business document exists? |
| Activity | What meaningful business interaction occurred? |
| Audit | Who changed what, when, and how? |
| Events | What happened that other components should know about? |
| Configuration | How should the platform behave? |
| Offline | How does the platform continue operating while disconnected? |

If a kernel cannot be described by a single foundational question, its responsibilities should be reconsidered.

---

# 5. Design Characteristics

Every Platform Kernel must satisfy the following characteristics.

## Stable

Platform Kernels change infrequently.

Changes affect the entire platform.

---

## Reusable

Platform Kernels support multiple Business Contexts.

They must never contain business logic specific to a single application.

---

## Independent

Platform Kernels remain independent of:

- Commercial
- CRM
- Workshop
- Inventory
- Procurement
- Fleet
- Finance

---

## Canonical

Every shared business concept has exactly one authoritative owner.

Higher layers may reference that concept but must never redefine or duplicate it.

---

## Technology Agnostic

Platform Kernels define business concepts rather than implementation details.

They are independent of:

- databases
- cloud providers
- UI frameworks
- messaging technologies
- storage implementations

---

# 6. Ownership Model

Every Platform Kernel completely owns its concepts.

Ownership includes:

- concepts
- lifecycle
- validation
- public contracts
- domain events

Higher layers may reference these concepts but never redefine them.

Example

```
Party Kernel

owns

Party
```

Commercial Context

```
interprets Party as Customer
```

Procurement Context

```
interprets Party as Supplier
```

Fleet Context

```
interprets Party as Fleet Client
```

The Party Kernel never owns business roles.

Business Contexts assign meaning to shared concepts.

---

# 7. Kernel Dependency Rules

Platform Kernels may depend only on other Platform Kernels.

They must never depend on:

- Core Business Domains
- Platform Services
- Business Contexts
- Product Assemblies

Circular dependencies are prohibited.

Dependencies should always point toward more fundamental concepts.

---

# 8. Kernel Relationship Principles

Dependencies should represent genuine business relationships.

Examples

Identity

↓

Party

(optional)

A Party may have an Identity.

A Party may also exist without one.

---

Organization

↓

Party

(optional)

A Party may belong to an Organization.

Organizations do not own Parties.

---

Document

↓

Party

(optional)

Documents may reference Parties.

Documents never own them.

---

Workflow

↓

Events

(optional)

Workflow transitions may publish Events.

Workflow remains independent of event consumers.

---

Workflow

↓

Audit

(optional)

Workflow execution may produce audit records.

Workflow does not own auditing.

---

Activity

↓

Party

(optional)

Activities may reference Parties.

Activities never own them.

---

# 9. Behavioral Separation

Several Platform Kernels appear similar but serve fundamentally different purposes.

| Concern | Platform Kernel |
|----------|-----------------|
| Lifecycle progression | Workflow |
| Business timeline | Activity |
| Historical evidence | Audit |
| Platform integration | Events |

A single business action may produce all four.

Example

```
Quotation Submitted

↓

Workflow
State changed

↓

Activity
Timeline updated

↓

Audit
Status modification recorded

↓

Events
QuotationSubmitted published
```

Each kernel owns its respective concern.

Responsibilities must never overlap.

---

# 10. Dependency Philosophy

Dependencies represent business reality rather than implementation convenience.

Example

```
Party

↓

Identity
```

A customer may have a login.

Therefore Party may reference Identity.

However

```
Identity

↓

Party
```

is prohibited.

Authentication does not require knowledge of business parties.

Dependencies should always point toward more fundamental concepts.

---

# 11. Extension Rules

A new Platform Kernel should be introduced only when:

- the concept is foundational
- multiple Business Contexts require it
- no existing Platform Kernel owns it
- it is expected to remain stable over time

Business-specific capabilities belong in:

- Core Business Domains
- Platform Services
- Business Contexts

not Platform Kernels.

---

# 12. Anti-Patterns

The following are architectural violations.

## Duplicate Ownership

Two kernels own the same concept.

---

## Business Logic Leakage

Business workflows implemented inside Platform Kernels.

---

## Upward Dependencies

A Platform Kernel depending on a Business Context.

---

## Infrastructure Leakage

A Platform Kernel depending directly on storage, messaging, cloud providers, or databases.

---

## Role Ownership

A Platform Kernel owning business roles.

Incorrect

```
Party owns Customer
```

Correct

```
Party owns Party

Commercial defines Customer
```

---

# 13. Platform Kernel Catalog

The Automotive Business Platform currently defines the following Platform Kernels.

```
Platform Kernels

├── Identity
├── Organization
├── Party
├── Workflow
├── Document
├── Activity
├── Audit
├── Events
├── Configuration
└── Offline
```

Additional kernels should be introduced only after architectural review.

---

# 14. Guiding Principle

Platform Kernels establish the common language of the platform.

They answer foundational business questions, define stable ownership boundaries, and provide reusable concepts that every higher architectural layer depends upon.

Business Contexts should never reinvent concepts already owned by a Platform Kernel.

When introducing a new capability, always ask:

> **Does an existing Platform Kernel already own this concept?**

If the answer is yes, extend or consume that kernel rather than creating a new one.