# principles.md

# Automotive Business Platform
## Architectural Principles

**Version:** 1.0  
**Status:** Approved  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the fundamental architectural principles that govern the Automotive Business Platform.

These principles are mandatory for every Platform Kernel, Core Business Domain, Platform Service, and Business Context.

All architectural and implementation decisions must align with these principles.

---

# 2. Core Philosophy

The platform is built around a simple philosophy:

> Build reusable business capabilities first. Assemble products from those capabilities.

The platform is the long-lived asset.

Business solutions are temporary compositions of the platform.

Every architectural decision should increase reuse, reduce coupling, and preserve clear ownership.

---

# 3. Architectural Principles

---

## 3.1 Single Ownership

### Statement

Every business concept must have exactly one owner.

### Rationale

Ownership eliminates ambiguity, duplicate logic, and conflicting implementations.

Only the owning component is responsible for:

- Business rules
- Validation
- Lifecycle
- Persistence
- Domain events

Other components may reference the concept but must never redefine it.

### Example

Correct

```
Party Kernel

owns

Customer
```

```
Commercial Context

references Customer
```

Incorrect

```
CRM owns Customer

Commercial owns Customer

Service owns Customer
```

---

## 3.2 Separation of Concerns

### Statement

Each architectural layer has a single responsibility.

### Rationale

Mixing responsibilities increases coupling and reduces maintainability.

Responsibilities are divided as follows:

| Layer | Responsibility |
|---------|----------------|
| Platform Kernels | Foundational business concepts |
| Core Business Domains | Shared business knowledge |
| Platform Services | Cross-cutting technical capabilities |
| Business Contexts | Business workflows and transactions |

No layer should perform the responsibility of another.

---

## 3.3 Reference by Identity

### Statement

Business Contexts reference shared entities by identity.

### Rationale

Shared entities evolve independently.

Embedding external entities creates duplication and synchronization problems.

### Example

Correct

```
CustomerId

VehicleId

BranchId
```

Incorrect

```
Customer
{
    Name
    Address
    Phone
}
```

---

## 3.4 Composition over Duplication

### Statement

Applications are assembled from reusable capabilities.

Business logic should never be copied between contexts.

### Rationale

Reuse reduces maintenance effort and ensures consistent business behavior.

If functionality is reusable, extract it to the appropriate architectural layer.

---

## 3.5 Event-Driven Collaboration

### Statement

Business Contexts communicate through events.

### Rationale

Contexts should remain independent.

Direct dependencies create coupling and reduce modularity.

### Example

Preferred

```
Commercial

publishes

QuotationApproved
```

Service subscribes to

```
QuotationApproved
```

Not

```
Commercial

↓

Service
```

---

## 3.6 Dependency Inversion

### Statement

Depend on contracts rather than implementations.

### Rationale

Business Contexts should remain independent of infrastructure.

Implementations may change without affecting business logic.

### Example

Correct

```
IPriceResolver
```

Incorrect

```
VendorPricingService
```

---

## 3.7 Stable Foundations

### Statement

Lower architectural layers should change less frequently than higher layers.

### Rationale

Foundational concepts should remain stable while business workflows evolve.

Expected stability:

```
Most Stable

Platform Kernels

↓

Core Business Domains

↓

Platform Services

↓

Business Contexts

Least Stable
```

---

## 3.8 Plugin-First Design

### Statement

Replace implementations, not business logic.

### Rationale

Business workflows should remain unchanged regardless of implementation.

Examples include:

- Pricing Providers
- Vendor APIs
- Payment Providers
- Notification Providers
- Tax Providers

Business Contexts depend on contracts.

Implementations are interchangeable.

---

## 3.9 Immutable Business Transactions

### Statement

Published business transactions are immutable.

### Rationale

Historical records must remain accurate even when business rules change.

Examples include:

- Quotations
- Job Orders
- Invoices
- Payments

Corrections should create new versions rather than modifying history.

---

## 3.10 Evolutionary Architecture

### Statement

Design for extension rather than prediction.

### Rationale

Future requirements are uncertain.

The platform should support incremental growth without redesign.

Examples

Manual Pricing

↓

Pricing Engine

↓

Vendor Pricing

↓

Fleet Pricing

The Commercial Context remains unchanged.

---

# 4. Decision Hierarchy

When introducing new functionality, architectural decisions must follow this order.

```
1. Can an existing Platform Kernel own it?

↓

2. Can an existing Core Business Domain own it?

↓

3. Can an existing Platform Service provide it?

↓

4. Does it belong inside an existing Business Context?

↓

5. Is a new Business Context required?
```

New functionality should always be implemented at the highest reusable layer possible.

---

# 5. Architecture Checklist

Before introducing any new component, verify the following:

- Does it have a single owner?
- Is it implemented at the highest reusable architectural layer?
- Does it duplicate an existing concept?
- Does it introduce unnecessary dependencies?
- Can it be reused by another Business Context?
- Does it communicate through events or contracts?
- Is it replaceable through abstraction?
- Does it preserve historical business data?
- Does it follow the dependency rules defined by the platform?

If any answer is **No**, the design should be reconsidered.

---

# 6. Guiding Principle

The platform favors clarity over convenience.

Business concepts should have clear ownership.

Dependencies should remain directional.

Reusable capabilities should be extracted before duplication occurs.

Every architectural decision should make the platform easier to extend, easier to maintain, and easier to understand.

When in doubt:

- Prefer reuse over duplication.
- Prefer composition over modification.
- Prefer events over direct dependencies.
- Prefer stable foundations over short-term convenience.