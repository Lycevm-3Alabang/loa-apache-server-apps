# domains/automotive/catalog.md

# Catalog Domain
## Domain Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Industry Domain  
**Industry Pack:** Automotive  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Catalog Domain defines the canonical representation of products, services, packages, and other sellable offerings within the Automotive Domain Pack.

It provides a centralized catalog of offerings that may be referenced by multiple Business Contexts.

The Catalog Domain defines what the business can offer.

It does not determine how offerings are priced, sold, or fulfilled.

---

# 2. Responsibilities

The Catalog Domain is responsible for:

- catalog items
- products
- services
- service packages
- bundles
- item categorization
- item hierarchy
- item specifications
- item lifecycle
- item availability
- item relationships
- catalog validation
- catalog domain events

---

# 3. What the Catalog Domain Owns

Examples include:

- Product
- Service
- Package
- Bundle
- Catalog Item
- Category
- Brand
- Manufacturer Reference
- SKU
- Unit of Measure

These concepts belong exclusively to the Catalog Domain.

---

# 4. What the Catalog Domain Does NOT Own

The Catalog Domain does not own:

- Prices
- Discounts
- Taxes
- Inventory Levels
- Suppliers
- Purchase Orders
- Quotations
- Invoices
- Labor Standards
- Vehicle Information

These belong to other Domains or Business Contexts.

---

# 5. Ownership

The Catalog Domain owns:

- entities
- value objects
- classifications
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts consume catalog information but never redefine catalog items.

---

# 6. Core Concepts

The primary aggregate is:

```
Catalog Item
```

Supporting concepts may include:

```
Product

Service

Package

Bundle

Category

Brand

Unit of Measure
```

Catalog concepts remain internal unless exposed through public contracts.

---

# 7. Relationships

The Catalog Domain may reference Platform Kernels where appropriate.

Examples:

```
Catalog Item

↓

Document
```

The Catalog Domain may reference other Automotive Domains when necessary.

Examples:

```
Catalog Item

↓

Labor
```

```
Catalog Item

↓

Vehicle
```

Relationships should represent compatibility rather than ownership.

For example:

A service may be compatible with specific vehicle types.

A product may be applicable to specific manufacturers.

Compatibility does not imply ownership.

---

# 8. Business Rules

Examples include:

- Every catalog item has a unique identity.
- A catalog item may be active or inactive.
- A service may require labor.
- A package may contain multiple catalog items.
- Bundles may contain products and services.
- Catalog items may define compatibility rules.
- Catalog items should remain reusable across applications.

Business Contexts may extend behavior but may not redefine catalog concepts.

---

# 9. Lifecycle

A typical lifecycle may include:

```
Draft

↓

Active

↓

Inactive

↓

Archived
```

Business Contexts determine when transitions occur.

The Catalog Domain defines only the available lifecycle.

---

# 10. Domain Events

Examples include:

```
CatalogItemCreated

CatalogItemUpdated

CatalogItemActivated

CatalogItemArchived

CatalogItemRetired
```

Events communicate changes without introducing coupling.

---

# 11. Public Contracts

The Catalog Domain should expose stable contracts for:

- querying catalog items
- retrieving categories
- retrieving compatibility
- validating catalog references
- publishing catalog events

Business Contexts consume these contracts rather than accessing internal implementation.

---

# 12. Consumers

The following Business Contexts are expected to consume the Catalog Domain.

```
Commercial

Workshop

Inventory

Procurement

Fleet
```

The Catalog Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Catalog

calculates selling price
```

Pricing belongs to the Pricing Domain.

---

## Inventory Ownership

```
Catalog

tracks stock quantity
```

Inventory belongs to the Inventory Context.

---

## Procurement Ownership

```
Catalog

creates Purchase Orders
```

Procurement owns purchasing workflows.

---

## Commercial Ownership

```
Catalog

creates Quotations
```

Quotation creation belongs to the Commercial Context.

---

# 14. Future Evolution

The Catalog Domain may evolve to include:

- OEM catalogs
- aftermarket catalogs
- supplier catalogs
- digital services
- subscription offerings
- configurable products
- serialized products
- compatibility matrices

Future additions should remain focused on catalog knowledge rather than business workflows.

---

# 15. Guiding Principle

The Catalog Domain is the canonical source of everything the business can offer.

It defines what products and services exist.

It does not determine:

- how much they cost
- who purchases them
- when they are sold
- how they are fulfilled

Those responsibilities belong to other Domains and Business Contexts.

Business Contexts compose Catalog with other Domains to deliver business capabilities.