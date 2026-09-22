# platform.md

# Automotive Business Platform
## Platform Architecture Specification

**Version:** 1.0  
**Status:** Draft  
**Audience:** Architects, Senior Engineers, AI Development Agents

---

# 1. Purpose

This document defines the architectural foundation of the Automotive Business Platform.

It establishes the structure, ownership model, dependency rules, and architectural boundaries that govern every component built on the platform.

This is the highest-level architecture document.

All subsequent architectural documents inherit the rules defined here.

---

# 2. Vision

The Automotive Business Platform is a modular software platform designed for automotive service, repair, maintenance, and related businesses.

Rather than developing isolated applications, the platform provides reusable architectural building blocks that can be assembled into different business solutions.

Examples include:

- Customer Relationship Management (CRM)
- Commercial (Quotation & Estimates)
- Service Management
- Inventory Management
- Procurement
- Fleet Management
- Warranty Management
- Finance
- Franchise Operations

Applications are assembled from reusable platform components rather than developed independently.

The platform is the product.

Applications are compositions of the platform.

---

# 3. Design Goals

The platform is designed around the following principles.

## Reusability

Common business concepts should be implemented once and reused everywhere.

---

## Modularity

Business applications should be independently deployable without introducing unnecessary dependencies.

---

## Extensibility

The platform should support incremental growth without requiring architectural redesign.

---

## Replaceability

Implementations should be replaceable through contracts.

Examples include:

- Pricing providers
- Vendor integrations
- Notification providers
- Tax providers
- Payment providers

Business applications should remain unchanged when implementations evolve.

---

## Offline Readiness

Offline synchronization is a platform capability.

Business applications should not implement offline behavior independently.

---

## Multi-Tenancy

The platform must support:

- Single workshop
- Multi-branch businesses
- Franchise organizations
- SaaS deployments
- Self-hosted deployments

without architectural changes.

---

# 4. Non-Goals

This document intentionally does not define:

- Programming language
- Database technology
- Cloud provider
- Frontend framework
- Deployment topology
- UI architecture

These decisions are implementation concerns and are documented elsewhere.

---

# 5. Platform Architecture

The platform consists of four architectural layers.

```
+----------------------------------------------------------------+
|                    Business Applications                       |
|----------------------------------------------------------------|
| Commercial | CRM | Service | Inventory | Fleet | Finance | HR |
+-----------------------------▲----------------------------------+
                              │
+----------------------------------------------------------------+
|                  Core Business Domains                         |
|----------------------------------------------------------------|
| Catalog | Pricing | Tax | Scheduling | Reference Data          |
+-----------------------------▲----------------------------------+
                              │
+----------------------------------------------------------------+
|                    Platform Services                           |
|----------------------------------------------------------------|
| Notification | Approval | Search | PDF | Reporting | Integration|
+-----------------------------▲----------------------------------+
                              │
+----------------------------------------------------------------+
|                     Platform Kernels                           |
|----------------------------------------------------------------|
| Identity | Organization | Party | Asset | Workflow | Documents |
| Activity | Audit | Events | Configuration | Offline            |
+----------------------------------------------------------------+
```

Dependencies flow downward only.

---

# 6. Architectural Layers

## Platform Kernels

Platform Kernels represent foundational business concepts that are shared across the entire platform.

Characteristics

- Own canonical entities
- Own lifecycle
- Own validation
- Independent from business applications
- Stable over time

Examples

- Identity
- Organization
- Party
- Asset
- Workflow
- Documents
- Activity
- Audit
- Events
- Configuration
- Offline

---

## Core Business Domains

Core Business Domains encapsulate reusable business knowledge shared across multiple business applications.

Characteristics

- Own reusable business entities
- Own business rules specific to the domain
- Independent of any single business application
- Shared by multiple applications

Examples

- Catalog
- Pricing
- Tax
- Scheduling
- Reference Data

---

## Platform Services

Platform Services provide reusable technical or cross-cutting functionality.

Characteristics

- Perform work
- Do not own business entities
- Replaceable implementations
- Accessed through interfaces or contracts

Examples

- Notification
- PDF Rendering
- Search
- Approval
- Reporting
- Integration

---

## Business Applications

Business Applications implement complete business workflows.

Characteristics

- Own transactional aggregates
- Reference shared concepts
- Publish domain events
- Consume Core Business Domains
- Consume Platform Services
- Never duplicate shared entities

Examples

- Commercial
- CRM
- Service
- Inventory
- Procurement
- Fleet
- Finance
- Warranty

---

# 7. Dependency Rules

Dependencies must always flow downward.

Allowed

```
Business Applications

↓

Core Business Domains

↓

Platform Services

↓

Platform Kernels
```

Forbidden

```
Business Application

↓

Business Application
```

Forbidden

```
Platform Kernel

↓

Business Application
```

Forbidden

```
Core Business Domain

↓

Business Application
```

Circular dependencies are prohibited.

---

# 8. Ownership Model

Every entity, aggregate, value object, service, and business rule must have exactly one owner.

Ownership includes:

- Lifecycle
- Validation
- Business Rules
- Persistence
- Events

Other components may reference an entity but must never redefine or duplicate it.

Example

```
Party Kernel

owns Customer
```

Commercial Application

```
references Customer
```

Service Application

```
references Customer
```

Customer exists only once within the platform.

---

# 9. Communication Model

Business Applications communicate indirectly.

Preferred mechanisms include:

- Domain Events
- Integration Events
- Published Interfaces

Direct application-to-application dependencies are prohibited.

Preferred

```
Commercial

publishes

QuotationApproved
```

Service subscribes to

```
QuotationApproved
```

instead of

```
Commercial

↓

Service
```

---

# 10. Composition Model

Products are assembled from reusable platform components.

Example

Quotation Solution

```
Platform Kernels

+

Core Business Domains

+

Platform Services

+

Commercial Application
```

Workshop Suite

```
Platform Kernels

+

Core Business Domains

+

Platform Services

+

CRM

+

Commercial

+

Service
```

Enterprise Platform

```
Platform Kernels

+

Core Business Domains

+

Platform Services

+

All Business Applications
```

Business applications remain unchanged regardless of the final product composition.

---

# 11. Extension Model

New functionality should be introduced by extending one of the following architectural layers.

- Platform Kernel
- Core Business Domain
- Platform Service
- Business Application

Existing components should rarely require modification when extending the platform.

The preferred approach is extension rather than modification.

---

# 12. Plugin Architecture

Every major capability should be replaceable through contracts.

Examples include:

- Pricing Provider
- Notification Provider
- Vendor Catalog Provider
- Payment Provider
- Tax Provider

Business Applications must depend on abstractions rather than implementations.

---

# 13. Architectural Constraints

The following rules apply to the entire platform.

1. Every entity has exactly one owner.
2. Shared concepts must never be duplicated.
3. Business Applications may reference shared entities but may not redefine them.
4. Platform Kernels may not depend on Business Applications.
5. Business Applications may not directly depend on other Business Applications.
6. Cross-application communication must occur through events or published contracts.
7. Business rules should be implemented at the highest reusable layer possible.
8. Historical business transactions should be immutable after publication.
9. Platform Services must not own business entities.
10. Circular dependencies are prohibited.

---

# 14. Repository Structure

```
architecture/
│
├── platform.md
├── principles.md
├── dependency-rules.md
├── glossary.md
│
├── kernels/
├── domains/
├── services/
├── applications/
├── assemblies/
└── decisions/
```

Each document defines a single architectural responsibility.

---

# 15. Related Documents

This document is the root of the architecture repository.

The following documents extend this specification.

- principles.md
- dependency-rules.md
- glossary.md
- kernels/*
- domains/*
- services/*
- applications/*
- assemblies/*
- decisions/*

No lower-level document may contradict the rules defined in this specification.

---

# 15b. LOA Platform Notes (merged 2026-09-22 from AI-GUIDE)

> `AGENT.md` is the lean entry; this section holds LOA-specific architecture and gotchas.

## App topology

| App | Subdomain | Database | Purpose |
|-----|-----------|----------|---------|
| Auth | auth.lyceumalabang.edu.ph | loa_auth | JWT service, users, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Booking + evaluation |
| Cert API | cert-api.lyceumalabang.edu.ph | loa_cert | Issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js consumer of Auth + Cert APIs |

JWT: shared HMAC-SHA256 secret, HS256, `type=access`, local validation, no HTTP per request. Cross-app identity via Auth API / JWT claims only — no shared DB reads. See `PROJECT.md` for live status.

## Laravel assembly scaffolding checklist

All of these must exist or artisan breaks silently: `artisan`, `public/index.php`, `bootstrap/app.php` (NO `providers` array — Laravel 11+ auto-registers), quoted-space `.env`, `config/app.php` (NO `providers` array), `routes/api.php` (must exist even if empty).

## Recurring gotchas (full)

- **`.env` quoting:** quote every value with spaces (`APP_NAME="LOA Cert Platform"`), or dotenv fails to parse.
- **Apache strips `Authorization` on cPanel:** `public/.htaccess` MUST forward it BEFORE the front-controller rule:
  `RewriteCond %{HTTP:Authorization} .` + `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`. Without it, JWT endpoints 401 in prod while passing locally (Docker uses nginx).
- **`.env.cpanel` secret parity:** `JWT_SECRET` and `ENCRYPTION_KEY` byte-identical across Auth ↔ consumers; tenant slug must match Auth DB `tenants.slug`. Verify via `php artisan tinker --execute="echo config('jwt.secret');"` post-deploy; never commit secrets.
- **Layer map for LOA work:** Product Assemblies (`assemblies/`) → Business Contexts (`business-contexts/`) → Industry Domains (`domains/`) → Platform Services (`services/`) → Platform Kernels (`kernels/`). Assemblies contain no business logic; contexts collaborate via events only.

---

# 15c. The Grander Scheme — Three Shelves, One Platform (2026-09-22)

The folders below are not filing categories. They are **underlying plans of a single scheme**: a long-lived reusable platform (kernels → domains → services → contexts) composed into independently-shippable products (assemblies), bound by joint contracts (integration), advanced by governed agents, kept alive by operations. Each shelf draws the same scheme at a different altitude:

| Shelf | Question | Folders | Altitude |
|-------|----------|---------|----------|
| **Build** (vision made concrete) | What do we build? | `kernels/` → `domains/` → `services/` → `business-contexts/` → `assemblies/` → `integration/` | Ordered by stability: reusable foundations first, deployables last, handshakes between deployables after both sides exist |
| **Govern** (conduct) | How do we work? | `AGENT.md` → `principles.md` / `platform.md` → `PROJECT.md` → `PROJECT_UPDATES.md` | Entry → rules → status → record. Describes agent behavior, never product behavior |
| **Operate** (execution) | How does it run? | `docs/`, `SSO-SETUP.md`, `LOCAL-DEV-RUNBOOK.md`, `CLI-COMMANDS.md`, per-assembly `DEPLOY.md` | Human operators + pipelines. Workspace operations: specs + runbooks for running, not building |

Traversing the repo means zooming through the scheme: a task arrives at govern (what are we doing?), descends the build shelf by dependency direction (where does this concept belong?), and lands on operate when it must run somewhere. A file's address should always answer *which plan, at which altitude* — never just *which topic*.

## Independence yet correlation (law, 2026-09-22)

Applies to every folder holding specs:

- **Independent plans:** a shared layer never names an assembly to *define* itself. Cross-links may point *down* to implementations (informational), never *up* for meaning. Verified: `domains/` and `business-contexts/` name no assembly; `kernels/identity/` links point down only.
- **Correlation at composition:** every spec folder declares its correlation in a fixed block — shared layers list `Composes` (what they wire from below) and informational consumers; assemblies list exactly what they compose (`Included` sections); joint contracts list `Parties` and each side's owned half.
- **Convention:** `## Correlation (independent yet correlated)` in folder READMEs (`Composes` / `Consumed by` / `Contracts`); `Parties` header block in `integration/specs/` files. Assemblies satisfy it via their existing `Included` sections.

---

# 16. Guiding Principle

The Automotive Business Platform is a platform—not a collection of applications.

Business Applications are temporary compositions built upon stable architectural foundations.

Invest in reusable foundations first.

Business applications should emerge naturally from those foundations.