# PDF Generation Service

## Platform Service Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Service
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The PDF Generation Service provides reusable capabilities for producing portable document format files from business data.

It answers one technical question:

> **How do we produce business documents in PDF format?**

The PDF Generation Service owns template rendering, document composition, and file generation. It does not own business entities or determine what documents should be produced.

---

# 2. Responsibilities

The PDF Generation Service is responsible for:

- template rendering
- document composition
- PDF generation
- template management
- font management
- layout rendering
- image embedding
- PDF metadata
- generation events

---

# 3. What the PDF Generation Service Owns

Examples include:

- PDF Template
- Template Version
- Generation Request
- Generation Result
- PDF Metadata
- Layout Definition

These concepts belong exclusively to the PDF Generation Service.

---

# 4. What the PDF Generation Service Does NOT Own

The PDF Generation Service does not own:

- Business Entities
- Business Workflows
- Document Storage
- Document Classification
- Document Lifecycle
- Business Content

Those belong to Platform Kernels, other Platform Services, or Business Contexts.

---

# 5. Ownership

The PDF Generation Service owns:

- template rendering
- document composition
- PDF generation
- layout logic
- font management
- generation events

---

# 6. Relationships

The PDF Generation Service may reference:

- Platform Kernels
- Document Kernel

It must never depend on:

- Industry Domains
- Business Contexts
- Product Assemblies

---

# 7. Business Rules

Examples include:

- Every PDF has a unique identifier.
- Templates are versioned.
- Generated PDFs are immutable.
- Layouts are configurable.
- Fonts are managed centrally.
- Generation logs are preserved.

---

# 8. Template Model

Templates define document structure.

Examples

- Quotation Template
- Invoice Template
- Purchase Order Template
- Inspection Report Template
- Service Report Template

Templates are independent of business data.

---

# 9. Anti-Patterns

The following are architectural violations.

## Business Logic

```
PDF Service

calculates quotation totals
```

Business logic belongs to Business Contexts.

---

## Storage Ownership

```
PDF Service

stores generated files
```

Storage belongs to the Storage Service.

---

## Direct Dependency

```
PDF Service

depends on Commercial
```

Platform Services must never depend on Business Contexts.

---

# 10. Guiding Principle

The PDF Generation Service answers one question:

> **How do we produce business documents in PDF format?**

It does not determine what documents should be produced or what content they contain.

Those responsibilities belong to Business Contexts.
