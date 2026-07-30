# dependency-rules.md

# Automotive Business Platform
## Dependency Rules Specification

**Version:** 1.0  
**Status:** Approved  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the dependency rules for the Automotive Business Platform.

Its purpose is to ensure that architectural boundaries remain consistent as the platform evolves.

All Platform Kernels, Core Business Domains, Platform Services, and Business Contexts must comply with these rules.

Violating these rules is considered an architectural defect.

---

# 2. Dependency Hierarchy

The platform is organized into four architectural layers.

```
Business Contexts
        │
        ▼
Core Business Domains
        │
        ▼
Platform Services
        │
        ▼
Platform Kernels
```

Dependencies always flow downward.

No dependency may point upward.

---

# 3. Layer Dependency Matrix

| From | To | Allowed |
|-------|----|----------|
| Business Context | Business Context | ❌ |
| Business Context | Core Business Domain | ✅ |
| Business Context | Platform Service | ✅ |
| Business Context | Platform Kernel | ✅ |
| Core Business Domain | Core Business Domain | ✅ (when justified) |
| Core Business Domain | Platform Service | ✅ |
| Core Business Domain | Platform Kernel | ✅ |
| Core Business Domain | Business Context | ❌ |
| Platform Service | Platform Kernel | ✅ |
| Platform Service | Core Business Domain | ❌ |
| Platform Service | Business Context | ❌ |
| Platform Kernel | Any Higher Layer | ❌ |

---

# 4. Platform Kernels

Platform Kernels are the foundation of the platform.

Platform Kernels:

- may depend on other Platform Kernels when necessary
- must never depend on Core Business Domains
- must never depend on Platform Services
- must never depend on Business Contexts

Example

Allowed

```
Workflow

↓

Identity
```

Forbidden

```
Workflow

↓

Commercial
```

---

# 5. Core Business Domains

Core Business Domains provide reusable business knowledge.

They may depend on:

- Platform Kernels
- other Core Business Domains (only when ownership remains clear)
- Platform Services through contracts

They must never depend on Business Contexts.

Example

Allowed

```
Pricing

↓

Catalog
```

Forbidden

```
Pricing

↓

Commercial
```

---

# 6. Platform Services

Platform Services perform reusable work.

Platform Services may depend on:

- Platform Kernels
- infrastructure
- external providers

Platform Services must never contain business rules owned by Business Contexts or Core Business Domains.

Example

Allowed

```
Notification

↓

SMTP Provider
```

Forbidden

```
Notification

↓

Commercial
```

---

# 7. Business Contexts

Business Contexts implement business workflows.

Business Contexts may depend on:

- Platform Kernels
- Core Business Domains
- Platform Services

Business Contexts must never depend directly on another Business Context.

Example

Allowed

```
Commercial

↓

Pricing
```

Forbidden

```
Commercial

↓

Inventory
```

Forbidden

```
Commercial

↓

CRM
```

---

# 8. Cross-Context Communication

Business Contexts collaborate using published contracts.

Preferred mechanisms:

- Domain Events
- Integration Events
- Public Interfaces

Example

Commercial publishes

```
QuotationApproved
```

Service subscribes to

```
QuotationApproved
```

Business Contexts must not invoke each other's internal services.

---

# 9. Entity Ownership

Business Contexts may reference shared entities.

Business Contexts must never duplicate ownership.

Example

Correct

```
Party

owns Customer
```

Commercial

```
CustomerId
```

Incorrect

Commercial

```
Customer
{
    Name
    Address
}
```

---

# 10. Shared References

Cross-context references should use identifiers rather than object graphs.

Preferred

```
CustomerId

VehicleId

SupplierId

BranchId
```

Avoid

```
Customer
Vehicle
Supplier
```

This preserves context independence.

---

# 11. Event Dependencies

Events flow outward.

Dependencies do not.

Example

```
Commercial

publishes

QuotationApproved
```

```
Service

subscribes

QuotationApproved
```

Publishing an event does not create a compile-time dependency.

---

# 12. Infrastructure Dependencies

Infrastructure belongs outside business logic.

Examples include:

- SQL Server
- PostgreSQL
- Redis
- SMTP
- Azure Storage
- Vendor APIs

Business logic should depend on abstractions.

Example

```
IPriceProvider
```

not

```
VendorPriceApi
```

---

# 13. Circular Dependencies

Circular dependencies are prohibited.

Forbidden

```
Commercial

↓

Pricing

↓

Commercial
```

Forbidden

```
CRM

↓

Service

↓

CRM
```

The dependency graph must remain acyclic.

---

# 14. Allowed Extension Points

The preferred extension mechanisms are:

- New Platform Kernel
- New Core Business Domain
- New Platform Service
- New Business Context
- Plugin Implementation

Existing architectural boundaries should remain intact.

---

# 15. Architectural Violations

The following are considered architectural violations.

- Duplicate ownership
- Circular dependencies
- Direct Business Context dependencies
- Business rules inside Platform Services
- Business rules inside Platform Kernels
- Shared entities duplicated across contexts
- Infrastructure dependencies inside business logic
- Cross-context database access
- Cross-context repository access

Architectural violations should be corrected before introducing new functionality.

---

# 16. Guiding Rule

Dependencies express architectural responsibility.

If a dependency violates ownership or layer boundaries, the design should be reconsidered before implementation.

Business Contexts collaborate.

They do not control one another.