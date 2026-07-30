# Storage Service

## Platform Service Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Service
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Storage Service provides reusable capabilities for persisting, retrieving, and managing files and binary data.

It answers one technical question:

> **How do we persist and retrieve files?**

The Storage Service owns file storage, retrieval, metadata, and lifecycle management. It does not own business entities or determine what files should be stored.

---

# 2. Responsibilities

The Storage Service is responsible for:

- file storage
- file retrieval
- file deletion
- file metadata
- file versioning
- storage quotas
- storage configuration
- storage events

---

# 3. What the Storage Service Owns

Examples include:

- Storage Provider
- File Reference
- File Metadata
- Storage Configuration
- Storage Quota
- Retention Policy

These concepts belong exclusively to the Storage Service.

---

# 4. What the Storage Service Does NOT Own

The Storage Service does not own:

- Business Entities
- Business Workflows
- Document Classification
- Document Lifecycle
- Document Relationships
- Business Content

Those belong to Platform Kernels, Industry Domains, or Business Contexts.

---

# 5. Ownership

The Storage Service owns:

- file persistence
- file retrieval
- storage configuration
- storage quotas
- retention policies
- storage events

---

# 6. Relationships

The Storage Service may reference:

- Platform Kernels
- Document Kernel

It must never depend on:

- Industry Domains
- Business Contexts
- Product Assemblies

---

# 7. Business Rules

Examples include:

- Every stored file has a unique reference.
- Storage quotas are enforced.
- Retention policies are configurable.
- Deleted files are recoverable within policy.
- Storage metadata is immutable.
- Access control is configurable per scope.

---

# 8. Storage Providers

The Storage Service supports:

- Cloud Blob Storage
- Local File System
- Network Storage
- Database Binary Storage
- CDN Storage

Provider selection is configurable.

---

# 9. Anti-Patterns

The following are architectural violations.

## Business Logic

```
Storage Service

determines document classification
```

Document classification belongs to the Document Kernel.

---

## Direct Dependency

```
Storage Service

depends on Commercial
```

Platform Services must never depend on Business Contexts.

---

## Business Ownership

```
Storage Service

owns quotation documents
```

Document ownership belongs to the Document Kernel.

Storage owns file persistence.

---

# 10. Guiding Principle

The Storage Service answers one question:

> **How do we persist and retrieve files?**

It does not determine what files should be stored or how they should be classified.

Those responsibilities belong to Platform Kernels and Business Contexts.
