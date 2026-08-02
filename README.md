# Business Platform Template

> A modular, industry-agnostic business platform for building CRM, Quotation, Workshop, Fleet, Inventory, Procurement, Finance, and future applications from a shared architectural foundation.

---

# Vision

This platform is designed to replace fragmented spreadsheets, disconnected applications, and monolithic systems with a composable platform that works across industries.

Instead of building isolated applications, the platform provides reusable building blocks that can be assembled into complete products.

Whether a business starts with a simple quotation system or eventually operates a complete ERP, every application shares the same architectural foundation.

**Industry-agnostic by design.** Plug in an Industry Pack for your domain:

- Automotive
- Education
- Events
- Healthcare
- Any custom industry

---

# Goals

The platform is designed around the following principles:

- Modular and composable
- Industry-agnostic (via pluggable Industry Packs)
- Plugin-oriented
- Multi-tenant
- SaaS and self-hosted deployment
- API-first
- Offline-capable
- Event-driven
- AI-ready
- Extensible without modifying existing modules

---

# Why This Platform Exists

Most businesses begin with simple operational tools:

- Excel spreadsheets
- Customer lists
- Manual records
- Printed documents
- Disconnected apps

As the business grows, these solutions become increasingly difficult to maintain.

This platform allows businesses to evolve incrementally.

Example roadmap:

```
Excel

↓

CRM

↓

Quotation

↓

Workshop

↓

Inventory

↓

Procurement

↓

Fleet

↓

Finance

↓

Complete ERP
```

Each new capability builds upon existing platform components rather than replacing them.

---

# Architectural Philosophy

The platform follows a layered architecture where every layer builds upon stable, reusable foundations.

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

Each layer has a single responsibility.

Lower layers remain stable while higher layers evolve as business requirements change.

---

# Platform Layers

## Platform Kernels

Canonical business concepts shared across every application.

Examples:

- Identity
- Organization
- Party
- Workflow
- Document
- Activity
- Audit
- Events
- Configuration
- Offline

---

## Industry Domains

Reusable industry knowledge. Plug in the pack you need.

Examples:

### Automotive Pack
- Vehicle
- Pricing
- Catalog
- Labor
- Warranty
- Maintenance
- Scheduling
- Tax

### Education Pack
- Course
- Student
- Instructor

### Events Pack
- Event
- Attendee
- Certificate

---

## Platform Services

Reusable technical capabilities.

Examples:

- Notification
- Search
- Reporting
- Storage
- PDF Generation
- Integrations

---

## Business Contexts

Complete business capabilities assembled from lower layers.

Examples:

- CRM
- Commercial (Quotation)
- Workshop
- Inventory
- Procurement
- Fleet
- Finance

---

## Product Assemblies

Deployable products assembled from one or more Business Contexts.

Examples:

- Quotation Application
- Workshop Management System
- Fleet Management
- Education Platform
- Events Certificate API
- Complete ERP

---

# Example Growth Journey

A startup may initially build only a quotation application.

```
Quotation App

Commercial

Pricing
Vehicle
Catalog

Party
Workflow
Document
Events
```

Months later:

```
Workshop

↓

reuses

Vehicle
Party
Workflow
Document
Activity
Audit
Pricing
```

No architectural redesign is required.

Applications expand by assembling additional modules.

---

# Design Principles

- Single ownership of every business concept
- Clear architectural boundaries
- Composition over duplication
- Event-driven communication
- API-first integration
- Offline-first synchronization
- Technology-agnostic business models
- Domain-oriented design
- Incremental evolution

---

# How to Use This Template

This is **not a code repository**. It is a reference that code repositories use.

## For Architects

Start with:

- `AI-GUIDE.md` — Architecture and code generation guide
- `dependency-rules.md` — Dependency matrix
- `glossary.md` — Architectural terms
- `principles.md` — Design principles

## For Developers

Start with:

- `AI-RULES.md` — Coding conventions
- `build-your-own-app.md` — Step-by-step assembly guide
- `examples/` — Worked examples with Mermaid diagrams

## For AI Agents

Start with:

- `AI-GUIDE.md` — Contains the code generation decision tree and placement guide

---

# Testing

## Auth Platform (loa-auth-platform)

### Prerequisites

- PHP 8.3+
- Composer
- MySQL or SQLite (for testing)

### Running Tests

```bash
cd assemblies/loa-auth-platform

# Install dependencies
composer install

# Run all tests
php vendor/bin/phpunit

# Run a specific test file
php vendor/bin/phpunit tests/Feature/Api/PermissionPolicy/ClaimPolicyMiddlewareTest.php

# Run with verbose output
php vendor/bin/phpunit --verbose

# Run with coverage
php vendor/bin/phpunit --coverage
```

### Test Environment

Tests use an in-memory SQLite database. The `.env` file is not required for testing — the `phpunit.xml.dist` sets all necessary environment variables:

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `JWT_SECRET=test-secret-key-for-testing-only-32chars`
- `APP_ENV=testing`

### Test Structure

```
tests/
├── Feature/
│   ├── Api/
│   │   ├── Auth/           # Authentication flow tests
│   │   ├── Groups/         # Group management tests
│   │   ├── PermissionPolicy/  # Permission policy tests (new)
│   │   │   ├── ClaimPolicyMiddlewareTest.php
│   │   │   ├── ImportPermissionsCommandTest.php
│   │   │   ├── ModelTest.php
│   │   │   ├── PermissionPolicyControllerTest.php
│   │   │   └── PermissionPolicyServiceTest.php
│   │   ├── UserGroups/     # User group permission tests
│   │   └── Users/          # User management tests
│   └── Web/                # Web UI tests
├── Traits/
│   ├── RefreshJwtSecret.php
│   └── WithJwt.php         # JWT generation for tests
├── CreatesApplication.php
└── TestCase.php
```

### Writing New Tests

- Use `RefreshDatabase` trait for database tests
- Use `WithJwt` trait for JWT-based auth tests
- Use `WithJwtClaims` trait for claims-based auth tests (new permission model)
- Extend `Tests\TestCase` for all test classes
- Follow the existing naming convention: `{FeatureName}Test.php`

```
application-template/
├── kernels/                    # Platform Kernels (specs)
├── domains/                    # Industry Domains (specs)
│   ├── automotive/
│   ├── education/
│   └── events/
├── business-contexts/          # Business Contexts (specs)
├── services/                   # Platform Services (specs)
├── assemblies/                 # Product Assemblies (specs)
├── examples/                   # Worked examples with diagrams
├── decisions/                  # Architecture Decision Records
├── AI-GUIDE.md                 # Architecture + code generation guide
├── AI-RULES.md                 # Coding conventions
├── build-your-own-app.md       # Step-by-step assembly guide
├── dependency-rules.md         # Dependency matrix
├── glossary.md                 # Architectural terms
├── platform.md                 # Platform overview
├── principles.md               # Design principles
└── README.md                   # This file
```

---

# Documentation

| Document | Purpose |
|---|---|
| `AI-GUIDE.md` | Architecture + code generation guide |
| `AI-RULES.md` | Coding conventions |
| `build-your-own-app.md` | Step-by-step assembly guide |
| `dependency-rules.md` | Dependency matrix |
| `glossary.md` | Architectural terms |
| `principles.md` | Design principles |
| `platform.md` | Platform overview |
| `examples/` | Worked examples with Mermaid diagrams |
| `kernels/` | Platform Kernel specs |
| `domains/` | Industry Domain specs |
| `business-contexts/` | Business Context specs |
| `services/` | Platform Service specs |
| `assemblies/` | Product Assembly specs |

---

# Guiding Principle

This platform is not a single application.

It is a platform for building business applications across any industry.

Every application is assembled from reusable architectural building blocks.

Build once.

Reuse everywhere.
