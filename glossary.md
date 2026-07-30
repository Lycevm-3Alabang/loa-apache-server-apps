# glossary.md

# Automotive Business Platform
## Architecture Glossary

**Version:** 1.0  
**Status:** Approved  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This glossary defines the architectural terminology used throughout the Automotive Business Platform.

Every architectural document, design discussion, and implementation should use these terms consistently.

Where a term is defined here, its definition takes precedence over informal usage.

---

# 2. Architecture Terms

## Architecture

The overall structure of the platform, including its layers, ownership boundaries, dependencies, communication model, and extension strategy.

Architecture defines *how* the platform is organized, not *how* individual features are implemented.

---

## Platform

The complete reusable software foundation from which products and business solutions are assembled.

The platform consists of:

- Platform Kernels
- Core Business Domains
- Platform Services
- Business Contexts

---

## Product Assembly

A deployable solution composed from one or more Business Contexts together with the required shared platform components.

Examples include:

- Quotation Solution
- Workshop Management Solution
- Fleet Management Solution
- Enterprise Platform

A Product Assembly owns no business logic.

It is a composition of reusable components.

---

# 3. Architectural Layers

## Platform Kernel

A foundational business capability shared by the entire platform.

Characteristics

- owns canonical entities
- owns lifecycle
- owns validation
- highly stable
- reusable by all Business Contexts

Examples

- Party
- Asset
- Identity
- Organization
- Workflow
- Documents

---

## Core Business Domain

A reusable business domain that contains shared business knowledge.

Core Business Domains own reusable business concepts but do not implement complete business workflows.

Examples

- Catalog
- Pricing
- Tax
- Scheduling

---

## Platform Service

A reusable capability that performs work without owning business entities.

Platform Services are replaceable implementations.

Examples

- Notification
- Search
- PDF Rendering
- Approval
- Integration

---

## Business Context

A bounded business area responsible for a complete business workflow.

Business Contexts own transactional business logic.

Examples

- Commercial
- CRM
- Service
- Inventory
- Procurement
- Fleet

---

# 4. Domain-Driven Design Terms

## Entity

An object with a stable identity throughout its lifecycle.

Examples

- Customer
- Vehicle
- Quotation

Entities are compared by identity rather than values.

---

## Value Object

An object defined entirely by its values.

Value Objects have no identity.

Examples

- Money
- Address
- VIN
- Email Address

Replacing a Value Object creates a new value.

---

## Aggregate

A consistency boundary containing one or more related entities and value objects.

Only the Aggregate Root may be referenced externally.

Examples

Quotation

```
Quotation

├── Items

├── Totals

├── Versions

└── Terms
```

---

## Aggregate Root

The primary entity responsible for maintaining aggregate consistency.

External components interact only with the Aggregate Root.

---

## Repository

A persistence abstraction responsible for loading and saving aggregates.

Repositories belong to the owning context.

Repositories must never expose another context's aggregates.

---

## Domain Event

A record describing something that has already occurred within a Business Context.

Examples

- QuotationCreated
- JobCompleted
- CustomerRegistered

Domain Events are immutable.

---

## Integration Event

A Domain Event intended for consumption by other contexts or external systems.

Integration Events are the preferred communication mechanism between Business Contexts.

---

# 5. Ownership Terms

## Ownership

The exclusive responsibility for an architectural concept.

Ownership includes:

- business rules
- validation
- persistence
- lifecycle
- events

Every concept has exactly one owner.

---

## Reference

A relationship to an entity owned elsewhere.

References do not transfer ownership.

Preferred

```
CustomerId
```

Not

```
Customer
```

---

## Canonical Model

The authoritative representation of a business concept.

Every shared business concept has one canonical model.

Examples

Customer

Vehicle

Part

Supplier

---

# 6. Dependency Terms

## Dependency

A compile-time or runtime reliance on another architectural component.

Dependencies must follow the platform dependency rules.

---

## Coupling

The degree to which two components rely upon one another.

Lower coupling results in better maintainability.

---

## Cohesion

The degree to which responsibilities belong together.

Higher cohesion is preferred.

Each component should solve one business responsibility.

---

## Contract

A stable interface describing how one component interacts with another.

Examples

```
IPriceResolver

INotificationProvider

IVendorCatalogProvider
```

Business logic depends on contracts rather than implementations.

---

## Plugin

A replaceable implementation of a contract.

Examples

```
SMS Notification Provider

Email Notification Provider

Vendor A Pricing Provider

Vendor B Pricing Provider
```

Business logic remains unchanged when plugins change.

---

# 7. Business Terms

## Transaction

A business operation that changes business state.

Examples

- Create Quotation
- Approve Job
- Receive Inventory
- Issue Invoice

Transactions belong to Business Contexts.

---

## Snapshot

A historical copy of external information stored within a transaction.

Snapshots preserve historical accuracy.

Example

Quotation stores

```
Resolved Price

Resolved Tax

Resolved Discount
```

rather than recalculating values later.

---

## Workflow

The progression of a business process through defined states.

Examples

```
Draft

↓

Submitted

↓

Approved

↓

Completed
```

Workflow definitions belong to the Workflow Kernel.

Business Contexts define their own states.

---

# 8. Composition Terms

## Composition

The process of assembling a Product Assembly from reusable platform components.

Composition does not introduce new ownership.

---

## Extension

Adding functionality without modifying existing architectural boundaries.

Preferred approaches

- new Platform Kernel
- new Core Business Domain
- new Platform Service
- new Business Context
- plugin implementation

---

## Configuration

Behavior changes achieved through configuration rather than code changes.

Configuration should not alter ownership boundaries.

---

# 9. AI Development Terms

## Architectural Drift

The gradual erosion of architectural boundaries caused by inconsistent implementation.

Examples

- duplicate entities
- duplicate business rules
- unnecessary dependencies
- ownership violations

Architectural drift should be corrected immediately.

---

## Ownership Violation

Any implementation that introduces business logic into a component that does not own it.

Example

Commercial calculating inventory availability.

Inventory owns inventory.

Commercial references inventory.

---

## Layer Violation

Any dependency that contradicts the dependency hierarchy defined by the platform.

Example

```
Commercial

↓

Service
```

This is a layer violation.

---

# 10. Guiding Vocabulary

The following terminology should be used consistently throughout the platform.

| Preferred Term | Avoid |
|----------------|-------|
| Business Context | Module, Feature |
| Platform Kernel | Shared Model |
| Core Business Domain | Shared Library |
| Platform Service | Utility |
| Product Assembly | Application Suite |
| Contract | Concrete Service |
| Plugin | Custom Implementation |
| Reference | Embedded Object |
| Ownership | Shared Responsibility |

---

# 11. Guiding Principle

A shared vocabulary is essential for a shared architecture.

When every architect, engineer, and AI agent uses the same terminology, architectural intent becomes explicit, communication improves, and implementation remains consistent across the platform.