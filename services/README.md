# services/README.md

# Platform Services
## Platform Services Layer Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Services
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the role of Platform Services within the Business Platform.

Platform Services provide reusable technical capabilities that perform work without owning business entities.

They execute cross-cutting concerns shared by multiple Business Contexts while remaining independent of business logic.

Individual Platform Service specifications inherit the architectural rules defined in this document.

---

# 2. What is a Platform Service?

A Platform Service is a reusable capability that performs a specific technical function.

A Platform Service:

- performs work
- does not own business entities
- is replaceable through contracts
- is accessed through interfaces
- is reusable by multiple Business Contexts
- remains independent of business workflows

A Platform Service is **not**:

- a Platform Kernel
- an Industry Domain
- a Business Context
- a Product Assembly
- a business entity owner
- a business workflow

---

# 3. Responsibilities

Platform Services are responsible for performing technical work.

Examples include:

| Platform Service | Responsibility |
|------------------|---------------|
| CORS | Which origins may call LOA APIs from a browser? |
| Notification | How do we deliver messages to users? |
| Storage | How do we persist and retrieve files? |
| Search | How do we find information quickly? |
| Reporting | How do we present operational information? |
| PDF Generation | How do we produce business documents? |
| Integration | How do we communicate with external systems? |

Each Platform Service owns one technical capability.

---

# 4. Design Characteristics

Every Platform Service must satisfy the following characteristics.

## Performant

Platform Services execute work efficiently.

---

## Stateless

Platform Services do not own business state.

---

## Replaceable

Platform Service implementations may be swapped without affecting Business Contexts.

---

## Contract-Based

Business Contexts depend on contracts rather than implementations.

---

## Independent

Platform Services remain independent of Business Contexts, Industry Domains, and Platform Kernels.

---

## Scalable

Platform Services may scale independently based on demand.

---

# 5. Ownership Model

A Platform Service owns:

- technical logic
- implementation details
- performance characteristics
- integration contracts
- service events

A Platform Service does not own:

- business entities
- business rules
- business workflows
- business transactions
- business validation

---

# 6. Dependency Rules

Platform Services may depend on:

- Platform Kernels
- Infrastructure
- External Providers

Platform Services must never depend on:

- Industry Domains
- Business Contexts
- Product Assemblies

Circular dependencies are prohibited.

---

# 7. Relationship with Platform Kernels

Platform Kernels provide foundational business concepts.

Platform Services provide technical capabilities.

Platform Services may reference Platform Kernels but never redefine them.

---

# 8. Relationship with Business Contexts

Business Contexts consume Platform Services.

Platform Services must never consume Business Contexts.

Business Contexts depend on contracts.

Platform Services provide implementations.

---

# 9. Contract Model

Platform Services expose contracts.

Business Contexts depend on contracts.

```
Business Context
        ↓
   Contract (interface)
        ↓
Platform Service Implementation
```

Implementations may be replaced without affecting Business Contexts.

---

# 10. Extension Rules

New Platform Services should be introduced only when:

- the capability is technical rather than business
- multiple Business Contexts require it
- no existing Platform Service provides it
- the capability is expected to remain reusable

Business-specific capabilities belong in Business Contexts.

---

# 11. Anti-Patterns

The following are considered architectural violations.

## Business Logic Inside Services

```
Notification

calculates pricing
```

Business logic belongs to Business Contexts or Industry Domains.

---

## Business Entity Ownership

```
Storage

owns documents
```

Document ownership belongs to the Document Kernel.

---

## Direct Business Context Dependencies

```
PDF Service

depends on Commercial
```

Platform Services must never depend on Business Contexts.

---

## Duplicate Ownership

```
Notification

implements email AND SMS AND push
```

These may be separate implementations behind a single contract.

---

# 12. Platform Service Catalog

The Business Platform currently defines the following Platform Services.

```
Platform Services

├── CORS
├── Notification
├── Storage
├── PDF Generation
├── Reporting
├── Integration
└── Search (future)
```

Additional Platform Services should be introduced only after architectural review.

---

# 13. Guiding Principle

Platform Services transform reusable technical capabilities into executable services.

They perform work without owning business entities.

Business Contexts depend on contracts.

Platform Services provide implementations.

When introducing a new technical capability, always ask:

> **Does this belong to an existing Platform Service before creating a new one?**
