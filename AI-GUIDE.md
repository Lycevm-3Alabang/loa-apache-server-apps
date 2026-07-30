# AI-GUIDE.md

# Automotive Business Platform
## AI Development Guide

**Version:** 1.0  
**Audience:** AI Coding Agents, Engineers, Architects

---

# Purpose

This document defines the architectural rules that AI development agents must follow when generating, modifying, or refactoring code within the Automotive Business Platform.

The primary objective is to ensure that all generated code respects architectural boundaries, ownership, and dependency rules.

When uncertain, AI should preserve architectural integrity over implementation convenience.

---

# Core Philosophy

The platform is built using composition rather than duplication.

Business capabilities are assembled from reusable building blocks.

AI should always attempt to reuse existing concepts before introducing new ones.

Never create a new concept if an existing architectural layer already owns it.

---

# Architecture Layers

The platform is organized into the following layers.

```
Product Assemblies
        ▲
Business Contexts
        ▲
Industry Domains
        ▲
Platform Services
        ▲
Platform Kernels
```

Dependencies always point downward.

Higher layers consume lower layers.

Lower layers never depend on higher layers.

---

# Layer Responsibilities

## Platform Kernels

Purpose

Own canonical business concepts shared across every application.

Examples

- Identity
- Organization
- Party
- Workflow
- Document
- Activity
- Audit
- Events
- Configuration

Platform Kernels never contain automotive-specific concepts.

---

## Industry Domains

Purpose

Own reusable industry knowledge.

Examples (Automotive)

- Vehicle
- Catalog
- Pricing
- Labor
- Warranty
- Maintenance
- Inspection

Industry Domains may reference Platform Kernels.

Industry Domains never reference Business Contexts.

---

## Platform Services

Purpose

Provide reusable technical capabilities.

Examples

- Notification
- Storage
- Search
- Reporting
- PDF Generation
- Offline Synchronization
- Integration

Platform Services do not own business concepts.

---

## Business Contexts

Purpose

Implement complete business capabilities.

Examples

- CRM
- Commercial
- Workshop
- Inventory
- Procurement
- Finance
- Fleet

Business Contexts compose Domains and Platform Kernels.

Business Contexts never redefine Domain ownership.

---

## Product Assemblies

Purpose

Compose Business Contexts into deployable applications.

Examples

- CRM
- Quotation
- Workshop
- Fleet
- Automotive ERP

Assemblies contain no business logic.

---

# Ownership Rules

Every concept has exactly one owner.

Examples

Correct

```
Party owns Party

Commercial defines Customer Role
```

Incorrect

```
CRM owns Customer
```

Correct

```
Workflow owns lifecycle execution
```

Incorrect

```
Quotation owns workflow engine
```

If ownership already exists, reuse it.

Do not duplicate ownership.

---

# Dependency Rules

AI must obey the following dependency rules.

```
Assemblies
    ↓

Business Contexts
    ↓

Industry Domains
    ↓

Platform Services
    ↓

Platform Kernels
```

Never generate upward dependencies.

Never generate circular dependencies.

---

# Decision Tree

Before generating code, determine where the concept belongs.

## Is it a canonical business concept?

Examples

- Party
- Workflow
- Document

→ Platform Kernel

---

## Is it reusable industry knowledge?

Examples

- Vehicle
- Pricing
- Labor

→ Industry Domain

---

## Is it a reusable technical capability?

Examples

- Email
- Storage
- Search

→ Platform Service

---

## Is it a business process?

Examples

- Quotation
- Workshop
- Procurement

→ Business Context

---

## Is it a deployable application?

Examples

- CRM
- Quotation App
- Workshop App

→ Product Assembly

---

# Workflow vs Activity vs Audit vs Events

These concepts are distinct.

Workflow

Question answered

> What lifecycle state is this object in?

Examples

Draft

↓

Submitted

↓

Approved

---

Activity

Question answered

> What meaningful business interaction occurred?

Example

Quotation emailed to customer.

---

Audit

Question answered

> Who changed what, when, and how?

Example

Discount changed

10%

↓

15%

---

Events

Question answered

> What happened that other components should know about?

Example

QuotationApproved

---

A single business action may generate all four.

They are not interchangeable.

---

# Before Creating Anything New

AI must always ask:

1. Does this already exist?
2. Which layer owns it?
3. Can it be reused?
4. Is this business logic or technical capability?
5. Is this industry-specific?
6. Does this violate ownership?
7. Does this introduce an upward dependency?

Only if all answers are acceptable should new code be introduced.

---

# Common Anti-Patterns

Do not generate:

- duplicate business concepts
- duplicated validation
- duplicated workflows
- duplicated pricing logic
- duplicated customer models

Do not:

- place Vehicle inside CRM
- place Workflow inside Commercial
- place Notifications inside Business Contexts
- place infrastructure inside Platform Kernels

---

# Repository Navigation

Architecture

```
README.md
platform.md
principles.md
dependency-rules.md
glossary.md
```

Platform Kernels

```
kernels/
```

Industry Domains

```
domains/
```

Platform Services

```
services/
```

Business Contexts

```
contexts/
```

Product Assemblies

```
assemblies/
```

When generating code, consult the relevant architectural specification before introducing new concepts.

---

# Code Generation Guide

**This is not a code repository.** It is a reference that code repositories use.

---

## When Asked to Generate Code

Follow this decision tree:

```
1. What am I building?
   ├── A new entity/aggregate → Go to Step 2
   ├── A new API endpoint → Go to Step 3
   ├── A new business workflow → Go to Step 4
   ├── A new integration → Go to Step 5
   └── A new UI component → Go to Step 6

2. New Entity/Aggregate
   ├── Which layer does it belong to?
   │   ├── Kernel entity → kernels/{kernel-name}.md
   │   ├── Domain entity → domains/{industry}/{domain-name}.md
   │   ├── Context entity → business-contexts/{context-name}/{entity-name}.md
   │   └── Service entity → services/{service-name}/{entity-name}.md
   ├── Check ownership rules in dependency-rules.md
   └── Generate code following the spec in the .md file

3. New API Endpoint
   ├── Which context owns this endpoint?
   │   ├── Check business-contexts/{context-name}/ for ownership
   │   └── If shared → services/{service-name}/
   ├── Reference only contracts (interfaces), not implementations
   └── Follow dependency rules

4. New Business Workflow
   ├── Which context owns this workflow?
   │   ├── Check business-contexts/{context-name}/
   │   └── If spans multiple contexts → use events
   ├── Workflows never call other contexts directly
   └── Cross-context via events only

5. New Integration
   ├── Which service handles this?
   │   ├── Email/SMS → services/notification/
   │   ├── PDF generation → services/pdf/
   │   ├── File storage → services/storage/
   │   ├── External API → services/integration/
   │   └── Reporting → services/reporting/
   ├── Services reference only kernel contracts
   └── Services never reference contexts

6. New UI Component
   ├── Which assembly does this belong to?
   │   └── assemblies/{assembly-name}/
   ├── UI references context APIs, not context internals
   └── UI never directly accesses databases
```

---

## Step 1: Identify the Layer

| If Building... | Layer | Reference File |
|---|---|---|
| User authentication | Kernel | `kernels/identity.md` |
| Customer/supplier model | Kernel | `kernels/party.md` |
| Multi-tenant org | Kernel | `kernels/organization.md` |
| Workflow/state machine | Kernel | `kernels/workflow.md` |
| Document model | Kernel | `kernels/document.md` |
| Activity logging | Kernel | `kernels/activity.md` |
| Audit trail | Kernel | `kernels/audit.md` |
| Domain events | Kernel | `kernels/events.md` |
| Configuration | Kernel | `kernels/configuration.md` |
| Offline support | Kernel | `kernels/offline.md` |
| Vehicle data | Domain | `domains/automotive/vehicle.md` |
| Parts catalog | Domain | `domains/automotive/catalog.md` |
| Pricing rules | Domain | `domains/automotive/pricing.md` |
| Labor rates | Domain | `domains/automotive/labor.md` |
| Tax calculation | Domain | `domains/automotive/tax.md` |
| Warranty terms | Domain | `domains/automotive/warranty.md` |
| Maintenance schedules | Domain | `domains/automotive/maintenance.md` |
| Inspection checklists | Domain | `domains/automotive/inspection.md` |
| Appointment scheduling | Domain | `domains/automotive/scheduling.md` |
| Service definitions | Domain | `domains/automotive/service.md` |
| Customer management | Context | `business-contexts/crm/` |
| Quotation creation | Context | `business-contexts/commercial/` |
| Work order management | Context | `business-contexts/workshop/` |
| Fleet management | Context | `business-contexts/fleet/` |
| Stock management | Context | `business-contexts/inventory/` |
| Purchasing | Context | `business-contexts/procurement/` |
| Invoicing | Context | `business-contexts/finance/` |
| Email/SMS | Service | `services/notification/` |
| PDF generation | Service | `services/pdf/` |
| File storage | Service | `services/storage/` |
| External APIs | Service | `services/integration/` |
| Reports | Service | `services/reporting/` |
| Deployable app | Assembly | `assemblies/` |

---

## Step 2: Check Dependencies

Before generating code, verify:

| Check | Rule |
|---|---|
| Kernel → anything above | ❌ FORBIDDEN |
| Domain → Context | ❌ FORBIDDEN |
| Context → Context | ❌ FORBIDDEN |
| Context → Domain | ✅ ALLOWED |
| Context → Kernel | ✅ ALLOWED |
| Domain → Kernel | ✅ ALLOWED |

**If you need to reference another context:**
- Use events (publish/subscribe)
- Never direct method calls
- Never database joins

See `dependency-rules.md` for the full dependency matrix.

---

## Step 3: Reference by Contract

When one component needs another:

```csharp
// CORRECT: Reference by interface
public class QuotationService
{
    private readonly IPartyRepository _partyRepo;  // Contract
    private readonly IVehicleRepository _vehicleRepo;  // Contract
}

// WRONG: Reference by implementation
public class QuotationService
{
    private readonly PartyRepository _partyRepo;  // ❌ Concrete type
    private readonly VehicleRepository _vehicleRepo;  // ❌ Concrete type
}
```

---

## Step 4: One Owner Per Concept

| Concept | Owner | Others Reference By |
|---|---|---|
| Party | Party Kernel | PartyId |
| Vehicle | Vehicle Domain | VehicleId |
| Customer | CRM Context | CustomerId |
| Quotation | Commercial Context | QuotationId |
| WorkOrder | Workshop Context | WorkOrderId |

**Rule:** If an entity already exists, reference it by ID. Never duplicate.

---

## Step 5: Generate Code Following the Spec

Each `.md` file in this template defines:

- **Purpose** — What this component is responsible for
- **Responsibilities** — What it does and doesn't do
- **Core Concepts** — The entities and value objects
- **Business Rules** — The invariants and constraints
- **Lifecycle** — The state transitions
- **Domain Events** — What it publishes
- **Public Contracts** — What it exposes
- **Anti-Patterns** — What NOT to do

**Use these as the source of truth when generating code.**

---

## Quick Reference: File Locations

| You Need | Look In |
|---|---|
| Architecture overview | `AI-GUIDE.md` |
| Dependency rules | `dependency-rules.md` |
| Naming conventions | `AI-RULES.md` |
| Glossary | `glossary.md` |
| Design principles | `principles.md` |
| Worked examples | `examples/` |
| Build your own app | `build-your-own-app.md` |
| Kernel specs | `kernels/` |
| Domain specs | `domains/` |
| Context specs | `business-contexts/` |
| Service specs | `services/` |
| Assembly specs | `assemblies/` |

---

## Example: Generate a New Entity

**Request:** "Create a Customer entity for the CRM context"

**AI Agent Process:**

1. Check `business-contexts/crm/` — does Customer exist?
2. If not, check `kernels/party.md` — does Party own this concept?
3. Party owns "Party" — CRM defines "Customer" as a Party role
4. Generate `business-contexts/crm/customer.md` following the spec pattern
5. Reference Party by PartyId, never embed Party data
6. Verify no circular dependencies
7. Generate code following the .md spec

---

## Example: Generate a New API Endpoint

**Request:** "Create an API to get quotations"

**AI Agent Process:**

1. Check `business-contexts/commercial/` — Quotation is owned here
2. Check `dependency-rules.md` — Contexts expose APIs, not Kernels
3. Generate API endpoint in the Commercial Context layer
4. Reference only contracts (IQuotationRepository), not implementations
5. Return DTOs, not domain entities
6. Verify dependency direction (downward only)

---

## Example: Generate Cross-Context Feature

**Request:** "When a quotation is approved, create a work order"

**AI Agent Process:**

1. Check ownership — Quotation belongs to Commercial, WorkOrder belongs to Workshop
2. Contexts cannot reference each other directly
3. Solution: Use events
4. Commercial publishes `QuotationApprovedEvent`
5. Workshop subscribes and creates WorkOrder
6. No direct coupling between contexts
7. Verify no circular dependencies

---

## Anti-Patterns to Avoid

| Pattern | Why It's Wrong | Correct Approach |
|---|---|---|
| `new CustomerRepository()` | Concrete dependency | Use `ICustomerRepository` interface |
| `contextA.Call(contextB)` | Context-to-context reference | Use events |
| `SELECT * FROM Customers` (no tenant) | Missing tenant filter | Always include TenantId |
| Duplicating Party in CRM | Duplicate ownership | Reference Party by PartyId |
| Putting business logic in Assembly | Wrong layer | Put logic in Context |
| Putting UI in Kernel | Wrong layer | UI goes in Assembly |

---

## Validation Checklist

Before finalizing generated code:

| Check | Status |
|---|---|
| Component placed in correct layer? | ☐ |
| References only contracts (interfaces)? | ☐ |
| No circular dependencies? | ☐ |
| No context-to-context references? | ☐ |
| One owner per concept? | ☐ |
| Cross-context via events only? | ☐ |
| Tenant filter included? | ☐ |
| Follows naming conventions? | ☐ |
| Matches spec in .md file? | ☐ |

---

# Guiding Principle

Every piece of generated code should strengthen the architecture rather than weaken it.

If a solution is easier but violates ownership, layering, or dependency rules, it is the wrong solution.

Correct architecture takes precedence over implementation convenience.

When uncertain:

Reuse before creating.

Compose before duplicating.

Extend before replacing.