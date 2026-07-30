# Quotation MVP
## Product Assembly Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Product Assembly
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Quotation MVP Product Assembly composes the minimum viable set of Business Contexts to deliver a digital quotation application for the automotive industry.

It assembles existing Business Contexts into a deployable product without owning any business logic.

The Quotation MVP answers:

> **"What is the simplest product that enables digital automotive quotations?"**

---

# 2. Business Contexts Included

The Quotation MVP includes the following Business Contexts:

```
Commercial
    ↕
    CRM
```

---

# 3. What the Quotation MVP Owns

The Quotation MVP owns:

- deployment configuration
- integration wiring
- feature flags
- product-specific configuration
- UI composition

The Quotation MVP does not own any business logic.

---

# 4. What the Quotation MVP Does NOT Own

The Quotation MVP does not own:

- Customer management
- Quotation creation
- Commercial workflows
- Pricing
- Vehicle management
- Tax rules
- Payment processing

Those belong to the included Business Contexts.

---

# 5. Included Business Contexts

## Commercial

Owns the complete commercial lifecycle:

- quotations
- approvals
- commercial workflows
- pricing rules
- discount management

## CRM

Owns customer and lead management:

- customers
- leads
- opportunities
- communications
- interactions

---

# 6. Excluded Business Contexts

The Quotation MVP explicitly excludes:

```
Workshop

Inventory

Procurement

Fleet

Finance
```

These can be added in future product evolutions.

---

# 7. Platform Dependencies

The Quotation MVP relies on Platform Kernels for:

```
Party

Workflow

Document

Events

Configuration

Identity

Organization
```

Platform Kernels are shared across all products.

---

# 8. Industry Dependencies

The Quotation MVP relies on Automotive Industry Domains for:

```
Vehicle

Catalog

Pricing

Labor

Tax
```

Industry Domains provide reusable automotive knowledge.

---

# 9. Services Dependencies

The Quotation MVP may consume:

```
PDF Service

Notification Service

Storage Service
```

Services are optional and configured per deployment.

---

# 10. Deployment

The Quotation MVP is deployed as a single application.

Deployment options:

- Cloud-hosted
- On-premise
- Mobile-first
- Web application

---

# 11. Future Evolution

The Quotation MVP may evolve to include:

```
+ Inventory

= Workshop Suite

+ Procurement

= Workshop with Procurement

+ Fleet

= Fleet Management

+ Finance

= Full Automotive ERP
```

Architectural evolution is incremental.

---

# 12. Anti-Patterns

The following are architectural violations.

## Business Logic Ownership

```
Quotation MVP

implements quotation logic
```

Quotation logic belongs to the Commercial Business Context.

---

## Domain Ownership

```
Quotation MVP

defines pricing rules
```

Pricing belongs to the Pricing Domain.

---

## Kernel Ownership

```
Quotation MVP

manages Party data
```

Party belongs to Platform Kernels.

---

# 13. Guiding Principle

The Quotation MVP is a thin composition layer.

It wires together existing Business Contexts.

It contains no business logic.

Business logic lives in Business Contexts, Domains, and Kernels.

Assemblies are composable products.

Products grow by adding Business Contexts.
