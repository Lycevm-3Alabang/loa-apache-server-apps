# domains/automotive/service.md

# Service Domain
## Domain Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Industry Domain
**Industry Pack:** Automotive
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Service Domain defines the canonical representation of automotive service offerings within the Automotive Domain Pack.

It owns reusable service definitions, service composition, service applicability, and service lifecycle.

The Service Domain answers:

> **"What service is performed?"**

It does not determine pricing, scheduling, execution, or billing.

---

# 2. Responsibilities

The Service Domain is responsible for:

- service definitions
- service composition
- service packages
- service applicability
- service prerequisites
- service outcomes
- service lifecycle
- service validation
- service events

---

# 3. What the Service Domain Owns

Examples include:

- Service Definition
- Service Package
- Service Operation
- Service Category
- Service Checklist
- Service Outcome
- Service Applicability

These concepts belong exclusively to the Service Domain.

---

# 4. What the Service Domain Does NOT Own

The Service Domain does not own:

- Products
- Prices
- Labor Standards
- Quotations
- Work Orders
- Appointments
- Inventory
- Invoices
- Payments

Those belong to other Domains or Business Contexts.

---

# 5. Ownership

The Service Domain owns:

- entities
- value objects
- validation
- service composition
- lifecycle rules
- domain events
- public contracts

Business Contexts consume service definitions without redefining them.

---

# 6. Core Concepts

The primary aggregate is:

```
Service Definition
```

Supporting concepts include:

```
Service Package

Service Operation

Service Category

Service Checklist

Service Outcome
```

---

# 7. Relationships

The Service Domain may reference:

```
Vehicle

Catalog

Labor
```

These references provide context only.

The Service Domain never owns these concepts.

---

# 8. Business Rules

Examples include:

- Every service has a unique identity.
- A service may consist of multiple operations.
- A service may require one or more labor operations.
- A service may apply only to specific vehicle types.
- A service may define prerequisites.
- A service may define expected outcomes.
- Service definitions should remain reusable across Business Contexts.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Approved

↓

Active

↓

Retired

↓

Archived
```

---

# 10. Domain Events

Examples include:

```
ServiceCreated

ServiceUpdated

ServiceActivated

ServiceRetired
```

---

# 11. Public Contracts

The Service Domain should expose stable contracts for:

- retrieving service definitions
- retrieving service composition
- validating services
- determining service applicability
- publishing service events

---

# 12. Consumers

Expected consumers include:

- Commercial
- Workshop
- Maintenance
- Inspection
- Fleet

The Service Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Service

calculates prices
```

Pricing belongs to the Pricing Domain.

---

## Labor Ownership

```
Service

defines standard labor time
```

Labor belongs to the Labor Domain.

---

## Workshop Ownership

```
Service

creates Work Orders
```

Workshop owns work execution.

---

## Commercial Ownership

```
Service

creates Quotations
```

Commercial owns quotations.

---

# 14. Future Evolution

The Service Domain may evolve to support:

- manufacturer service definitions
- OEM service packages
- configurable service packages
- condition-based services
- EV-specific services
- bundled services

Future additions should continue to represent service knowledge rather than operational workflows.

---

# 15. Terminology

Within the Automotive Domain Pack, the term **Service** refers exclusively to an automotive service offering or service definition.

It does not refer to:

- application services
- domain services
- microservices
- web services
- service-oriented architecture (SOA)

Those concepts belong to the platform architecture.

---

# 16. Guiding Principle

The Service Domain is the canonical source of automotive service knowledge.

It defines **what a service is** and **what it consists of**.

It does not determine:

- how much the service costs
- when the service is scheduled
- who performs the service
- how the service is executed
- how the service is billed

Those responsibilities belong to other Domains and Business Contexts.