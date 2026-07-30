# Workshop Suite
## Product Assembly Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Workshop Suite Product Assembly composes Business Contexts to deliver a complete workshop management application for the automotive industry.

It assembles existing Business Contexts into a deployable product without owning any business logic.

The Workshop Suite answers:

> **"What is the complete product for managing automotive workshops?"**

---

# 2. Business Contexts Included

The Workshop Suite includes the following Business Contexts:

```
Workshop
    ↕
Inventory
    ↕
Commercial
    ↕
CRM
```

---

# 3. What the Workshop Suite Owns

The Workshop Suite owns:

- deployment configuration
- integration wiring
- feature flags
- product-specific configuration
- UI composition

The Workshop Suite does not own any business logic.

---

# 4. What the Workshop Suite Does NOT Own

The Workshop Suite does not own:

- work orders
- repair activities
- inspection management
- maintenance scheduling
- stock management
- parts purchasing
- quotations
- customer management

Those belong to the included Business Contexts.

---

# 5. Included Business Contexts

## Workshop

Owns workshop operations:

- work orders
- repair activities
- inspections
- maintenance
- scheduling

## Inventory

Owns stock management:

- parts
- stock levels
- reservations
- transfers
- warehouses

## Commercial

Owns commercial transactions:

- quotations
- approvals
- workflows
- pricing

## CRM

Owns customer management:

- customers
- leads
- communications
- interactions

---

# 6. Excluded Business Contexts

The Workshop Suite explicitly excludes:

```
Fleet

Finance

Procurement
```

These can be added in future product evolutions.

---

# 7. Platform Dependencies

The Workshop Suite relies on Platform Kernels for:

```
Party

Workflow

Document

Events

Configuration

Identity

Organization

Activity

Audit
```

---

# 8. Industry Dependencies

The Workshop Suite relies on Automotive Industry Domains for:

```
Vehicle

Catalog

Pricing

Labor

Tax

Maintenance

Inspection

Warranty
```

---

# 9. Services Dependencies

The Workshop Suite may consume:

```
PDF Service

Notification Service

Storage Service

Reporting Service

Integration Service
```

---

# 10. Deployment

The Workshop Suite is deployed as a complete workshop management system.

Deployment options:

- Cloud-hosted
- On-premise
- Mobile-first
- Web application
- Desktop application

---

# 11. Future Evolution

The Workshop Suite may evolve to include:

```
+ Fleet

= Fleet Management Integration

+ Procurement

= Full Procurement Integration

+ Finance

= Complete Automotive ERP
```

---

# 12. Anti-Patterns

The following are architectural violations.

## Business Logic Ownership

```
Workshop Suite

implements work order logic
```

Work order logic belongs to the Workshop Business Context.

---

## Domain Ownership

```
Workshop Suite

defines maintenance schedules
```

Maintenance belongs to the Maintenance Domain.

---

## Kernel Ownership

```
Workshop Suite

manages Party data
```

Party belongs to Platform Kernels.

---

# 13. Guiding Principle

The Workshop Suite is a thin composition layer.

It wires together existing Business Contexts.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
