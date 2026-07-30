# domains/README.md

# Industry Domains
## Domain Layer Specification

**Version:** 1.0  
**Status:** Approved  
**Layer:** Industry Domain Layer  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

This document defines the role of Industry Domains within the Business Platform.

Industry Domains encapsulate reusable business knowledge that is specific to an industry while remaining independent of applications, workflows, and user interfaces.

Industry Domains bridge the gap between the platform's generic capabilities and industry-specific business solutions.

Individual Industry Packs (for example Automotive, Healthcare, or Construction) inherit the architectural rules defined in this document.

---

# 2. What is an Industry Domain?

An Industry Domain is a reusable collection of business concepts, terminology, rules, and behaviors belonging to a specific industry.

An Industry Domain:

- owns industry-specific business concepts
- owns the lifecycle of those concepts
- owns industry terminology
- exposes stable public contracts
- is reusable by multiple Business Contexts
- remains independent of individual applications

An Industry Domain is **not**:

- a Platform Kernel
- a Platform Service
- a Business Context
- a Product Assembly
- a user interface
- infrastructure
- an implementation framework

---

# 3. Responsibilities

Industry Domains are responsible for defining reusable business knowledge within an industry.

Responsibilities include:

- business entities
- value objects
- business terminology
- validation rules
- calculations
- lifecycle rules
- business invariants
- domain events
- public contracts

Industry Domains should answer industry-specific business questions without prescribing application workflows.

---

# 4. Design Characteristics

Every Industry Domain must satisfy the following characteristics.

## Industry Specific

Industry Domains model concepts unique to a particular industry.

Platform-independent concepts belong in Platform Kernels instead.

---

## Reusable

Industry Domains must support multiple Business Contexts.

Business knowledge should be implemented once and reused everywhere.

---

## Stable

Industry Domains evolve more slowly than applications.

Changes should reflect changes in business knowledge rather than implementation requirements.

---

## Independent

Industry Domains must remain independent of Business Contexts and Product Assemblies.

They should never contain application-specific behavior.

---

## Canonical

Every business concept has exactly one owner.

Duplicate ownership across Domains is prohibited.

---

## Technology Agnostic

Industry Domains define business knowledge rather than implementation technologies.

They are independent of:

- databases
- ORMs
- messaging platforms
- cloud providers
- web frameworks
- user interfaces

---

# 5. Ownership Model

Every Industry Domain owns its concepts completely.

Ownership includes:

- entities
- value objects
- terminology
- validation
- calculations
- lifecycle rules
- domain events
- public interfaces

Business Contexts consume these concepts but never redefine them.

Ownership must always remain unambiguous.

---

# 6. Dependency Rules

Industry Domains may depend upon:

- Platform Kernels
- Other Industry Domains (only when representing genuine business relationships)

Industry Domains must never depend upon:

- Platform Services
- Business Contexts
- Product Assemblies
- Applications

Circular dependencies are prohibited.

Dependencies must represent business relationships rather than implementation convenience.

---

# 7. Relationship with Platform Kernels

Platform Kernels provide foundational business concepts shared across every industry.

Industry Domains extend those concepts with industry-specific knowledge.

Examples include:

- referencing a Party
- using Workflow capabilities
- attaching Documents
- publishing Events

Platform Kernels never depend upon Industry Domains.

The relationship is always one-way.

```
Platform Kernels

↓

Industry Domains
```

---

# 8. Relationship with Business Contexts

Business Contexts orchestrate Industry Domains to implement complete business capabilities.

Industry Domains provide knowledge.

Business Contexts provide processes.

```
Business Context

↓

Industry Domains

↓

Platform Kernels
```

Business Contexts may compose multiple Domains to implement workflows.

Industry Domains must never implement Business Context responsibilities.

---

# 9. Industry Packs

Industry Domains are organized into Industry Packs.

Each Industry Pack groups related Domains belonging to the same industry.

Example:

```
domains/

├── automotive/
├── healthcare/
├── construction/
├── retail/
└── logistics/
```

Each Industry Pack defines its own ubiquitous language while following the architectural rules defined in this document.

---

# 10. Extension Rules

A new Industry Domain should be introduced only when:

- the concept represents industry knowledge
- multiple Business Contexts require it
- no existing Domain owns it
- the concept is expected to remain stable over time

Application-specific concepts should remain within the appropriate Business Context.

---

# 11. Anti-Patterns

The following are considered architectural violations.

## Duplicate Ownership

Two Domains owning the same concept.

---

## Business Process Leakage

An Industry Domain implementing application workflows.

Business workflows belong to Business Contexts.

---

## Infrastructure Leakage

Industry Domains directly referencing implementation technologies.

Industry knowledge must remain technology agnostic.

---

## Context Leakage

Industry Domains depending upon Business Contexts.

Business Contexts consume Domains.

Domains never consume Business Contexts.

---

## Application Leakage

Industry Domains implementing:

- screens
- APIs
- controllers
- repositories
- application services

These belong outside the Domain layer.

---

# 12. Layer Position

Industry Domains occupy the layer between Platform Kernels and Business Contexts.

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

This position allows Industry Domains to provide reusable business knowledge without becoming coupled to application behavior.

---

# 13. Guiding Principle

Industry Domains represent reusable business knowledge.

They define the language, concepts, and rules of an industry without prescribing how applications should use them.

Business Contexts implement business capabilities.

Platform Kernels provide foundational concepts.

Industry Domains provide industry expertise.

When designing new functionality, always ask:

> **Does this belong to an existing Industry Domain before creating a new one?**