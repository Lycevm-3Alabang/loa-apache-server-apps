# domains/automotive/README.md

# Automotive Domain Pack
## Automotive Industry Domain Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Industry Domain Pack  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the Automotive Domain Pack for the Business Platform.

The Automotive Domain Pack provides reusable business knowledge shared across automotive applications.

It establishes the canonical language, concepts, and business rules for the automotive industry without prescribing application workflows.

All Automotive Business Contexts consume these Domains rather than redefining them.

Individual Domain specifications (for example `vehicle.md` or `pricing.md`) inherit the architectural rules defined in this document.

---

# 2. Scope

The Automotive Domain Pack represents the business knowledge required to operate an automotive service, repair, maintenance, inspection, or fleet management business.

It intentionally excludes application behavior.

Examples include:

- customer management
- quotation workflows
- workshop operations
- inventory transactions
- invoicing

Those belong to Business Contexts.

---

# 3. Responsibilities

The Automotive Domain Pack is responsible for defining:

- automotive terminology
- reusable automotive entities
- automotive value objects
- automotive business rules
- calculations
- validation
- lifecycle rules
- domain events

It provides the shared language used throughout every Automotive Business Context.

---

# 4. Design Characteristics

Every Automotive Domain must satisfy the following principles.

## Automotive Specific

Domains represent knowledge unique to the automotive industry.

Generic business concepts belong to Platform Kernels.

---

## Reusable

Every Domain must be reusable by multiple Business Contexts.

Examples include:

- CRM
- Commercial
- Workshop
- Fleet
- Inventory
- Procurement
- Finance

---

## Stable

Automotive Domains evolve with industry knowledge rather than application requirements.

---

## Independent

Automotive Domains never depend upon:

- Business Contexts
- Product Assemblies
- User Interfaces
- Infrastructure

---

## Canonical

Every automotive concept has exactly one owner.

Duplicate ownership is prohibited.

---

# 5. Automotive Domain Catalog

The Automotive Domain Pack currently defines the following Domains.

```
Automotive

├── Vehicle
├── Catalog
├── Pricing
├── Labor
├── Service
├── Inspection
├── Maintenance
├── Warranty
└── Scheduling
```

Each Domain owns one architectural responsibility.

---

# 6. Domain Responsibilities

| Domain | Responsibility |
|----------|---------------|
| Vehicle | Represents vehicles and their technical identity. |
| Catalog | Defines products, services, packages, and sellable offerings. |
| Pricing | Determines how products and services are valued. |
| Labor | Defines labor operations, durations, and standards. |
| Service | Defines reusable service definitions and service composition. |
| Inspection | Represents inspection templates, findings, and recommendations. |
| Maintenance | Defines maintenance schedules and service intervals. |
| Warranty | Represents warranty coverage, eligibility, and constraints. |
| Scheduling | Represents appointments, time allocation, and resource availability. |

---

# 7. Relationship with Platform Kernels

Automotive Domains extend Platform Kernels.

Examples include:

```
Vehicle

↓

Party (Owner)

↓

Document

↓

Workflow

↓

Events
```

Platform Kernels remain unaware of automotive concepts.

Dependencies always point downward.

---

# 8. Relationship with Business Contexts

Business Contexts compose Automotive Domains to implement complete business capabilities.

Examples:

Commercial uses:

- Vehicle
- Catalog
- Pricing

Workshop uses:

- Vehicle
- Service
- Labor
- Inspection
- Warranty

Fleet uses:

- Vehicle
- Maintenance
- Scheduling

Automotive Domains never implement business workflows.

---

# 9. Ownership Rules

Each Domain owns its concepts completely.

Ownership includes:

- entities
- value objects
- terminology
- validation
- calculations
- lifecycle rules
- domain events
- public contracts

Business Contexts consume these concepts without redefining them.

---

# 10. Dependency Rules

Automotive Domains may depend upon:

- Platform Kernels
- Other Automotive Domains (only when architecturally justified)

Automotive Domains must never depend upon:

- Platform Services
- Business Contexts
- Product Assemblies

Circular dependencies are prohibited.

Dependencies must reflect genuine business relationships.

---

# 11. Extension Rules

Introduce a new Automotive Domain only when:

- the concept represents reusable automotive knowledge
- multiple Business Contexts require it
- no existing Domain owns it
- the concept is expected to remain stable

Application-specific concepts remain within Business Contexts.

---

# 12. Anti-Patterns

The following are considered architectural violations.

## Duplicate Ownership

```
Pricing owns pricing rules

Commercial owns pricing rules
```

---

## Workflow Leakage

```
Vehicle

creates Quotations
```

Quotation creation belongs to the Commercial Context.

---

## Context Leakage

```
Vehicle

references CRM
```

Domains never depend upon Business Contexts.

---

## Infrastructure Leakage

```
Warranty

references SQL Server
```

Domains remain technology agnostic.

---

# 13. Future Evolution

The Automotive Domain Pack is expected to evolve as new reusable concepts emerge.

Examples may include:

- Telematics
- Diagnostics
- EV Battery Management
- Tire Management
- Insurance
- Claims
- Regulatory Compliance

New Domains should be introduced only when they represent reusable industry knowledge rather than application behavior.

---

# 14. Guiding Principle

The Automotive Domain Pack defines the reusable language of the automotive industry.

Business Contexts orchestrate work.

Platform Kernels provide foundational concepts.

Automotive Domains provide automotive expertise.

Every automotive application should build upon these Domains rather than redefining them.

When introducing a new automotive concept, always ask:

> **Does this belong to an existing Automotive Domain before creating a new one?**