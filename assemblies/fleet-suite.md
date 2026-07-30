# Fleet Suite
## Product Assembly Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Fleet Suite Product Assembly composes Business Contexts to deliver a fleet management application for the automotive industry.

It assembles existing Business Contexts into a deployable product without owning any business logic.

The Fleet Suite answers:

> **"What is the complete product for managing automotive fleets?"**

---

# 2. Business Contexts Included

The Fleet Suite includes the following Business Contexts:

```
Fleet
    ↕
Workshop
    ↕
Inventory
    ↕
Commercial
    ↕
CRM
    ↕
Finance
```

---

# 3. What the Fleet Suite Owns

The Fleet Suite owns:

- deployment configuration
- integration wiring
- feature flags
- product-specific configuration
- UI composition

The Fleet Suite does not own any business logic.

---

# 4. What the Fleet Suite Does NOT Own

The Fleet Suite does not own:

- fleet vehicles
- fleet operations
- work orders
- stock management
- commercial transactions
- financial processing
- customer management

Those belong to the included Business Contexts.

---

# 5. Included Business Contexts

## Fleet

Owns fleet operations:

- fleet vehicles
- fleet assignments
- fleet maintenance
- fleet tracking

## Workshop

Owns workshop operations:

- work orders
- repair activities
- inspections
- maintenance

## Inventory

Owns stock management:

- parts
- stock levels
- reservations
- transfers

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

## Finance

Owns financial processing:

- invoicing
- payments
- accounts
- reports

---

# 6. Excluded Business Contexts

The Fleet Suite explicitly excludes:

```
Procurement
```

This can be added in future product evolutions.

---

# 7. Platform Dependencies

The Fleet Suite relies on Platform Kernels for:

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

The Fleet Suite relies on Automotive Industry Domains for:

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

The Fleet Suite may consume:

```
PDF Service

Notification Service

Storage Service

Reporting Service

Integration Service
```

---

# 10. Deployment

The Fleet Suite is deployed as a complete fleet management system.

Deployment options:

- Cloud-hosted
- On-premise
- Mobile-first
- Web application

---

# 11. Future Evolution

The Fleet Suite may evolve to include:

```
+ Procurement

= Full Procurement Integration

+ Additional Fleet Features

= Enhanced Fleet Management
```

---

# 12. Anti-Patterns

The following are architectural violations.

## Business Logic Ownership

```
Fleet Suite

implements fleet operations
```

Fleet operations belong to the Fleet Business Context.

---

## Domain Ownership

```
Fleet Suite

defines maintenance schedules
```

Maintenance belongs to the Maintenance Domain.

---

## Kernel Ownership

```
Fleet Suite

manages Party data
```

Party belongs to Platform Kernels.

---

# 13. Guiding Principle

The Fleet Suite is a thin composition layer.

It wires together existing Business Contexts.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
