# Full Platform
## Product Assembly Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Full Platform Product Assembly composes all Business Contexts to deliver a complete automotive ERP application.

It assembles all available Business Contexts into a deployable product without owning any business logic.

The Full Platform answers:

> **"What is the complete product for managing an automotive business?"**

---

# 2. Business Contexts Included

The Full Platform includes all Business Contexts:

```
Workshop
    ↕
Inventory
    ↕
Procurement
    ↕
Commercial
    ↕
CRM
    ↕
Fleet
    ↕
Finance
```

---

# 3. What the Full Platform Owns

The Full Platform owns:

- deployment configuration
- integration wiring
- feature flags
- product-specific configuration
- UI composition
- cross-context orchestration

The Full Platform does not own any business logic.

---

# 4. What the Full Platform Does NOT Own

The Full Platform does not own:

- work orders
- repair activities
- stock management
- purchasing
- quotations
- customer management
- fleet operations
- financial processing

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

## Procurement

Owns purchasing operations:

- purchase requests
- purchase orders
- vendor management
- contracts
- receiving

## Commercial

Owns commercial transactions:

- quotations
- approvals
- workflows
- pricing
- discounts

## CRM

Owns customer management:

- customers
- leads
- opportunities
- communications
- interactions

## Fleet

Owns fleet operations:

- fleet vehicles
- fleet assignments
- fleet maintenance
- fleet tracking

## Finance

Owns financial processing:

- invoicing
- payments
- accounts receivable
- accounts payable
- financial reporting

---

# 6. Excluded Business Contexts

The Full Platform excludes no Business Contexts.

All available Business Contexts are included.

---

# 7. Platform Dependencies

The Full Platform relies on Platform Kernels for:

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

The Full Platform relies on Automotive Industry Domains for:

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

The Full Platform may consume:

```
PDF Service

Notification Service

Storage Service

Reporting Service

Integration Service
```

---

# 10. Deployment

The Full Platform is deployed as a complete automotive ERP system.

Deployment options:

- Cloud-hosted
- On-premise
- Mobile-first
- Web application
- Desktop application

---

# 11. Future Evolution

The Full Platform may evolve to include:

```
+ Additional Business Contexts

= Extended ERP

+ Industry Packs

= Industry-Specific ERP

+ Marketplace Integrations

= Connected Platform
```

---

# 12. Anti-Patterns

The following are architectural violations.

## Business Logic Ownership

```
Full Platform

implements business logic
```

Business logic belongs to Business Contexts.

---

## Domain Ownership

```
Full Platform

defines domain rules
```

Domain rules belong to Industry Domains.

---

## Kernel Ownership

```
Full Platform

manages Party data
```

Party belongs to Platform Kernels.

---

# 13. Guiding Principle

The Full Platform is a thin composition layer.

It wires together all available Business Contexts.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
