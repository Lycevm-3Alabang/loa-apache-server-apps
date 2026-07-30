# audit.md

# Automotive Business Platform
## Platform Kernel Specification – Audit

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Kernel
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Audit Kernel establishes the canonical representation of data change history within the Automotive Business Platform.

It answers one architectural question:

> **Who changed what, when, and how?**

The Audit Kernel provides immutable traceability for business data across all Business Contexts.

Its primary purpose is accountability, compliance, diagnostics, and historical reconstruction.

---

# 2. Scope

The Audit Kernel is responsible for:

- Audit records
- Change history
- Field-level changes
- Actor attribution
- Change timestamps
- Historical reconstruction

The Audit Kernel is **not** responsible for:

- Business timelines
- Workflow execution
- Business events
- Notifications
- Business rules

Those responsibilities belong to other architectural components.

---

# 3. Responsibilities

The Audit Kernel records immutable evidence whenever business data changes.

It enables:

- historical reconstruction
- compliance reporting
- operational diagnostics
- accountability
- security investigations

Audit data should never be considered business data.

It is historical evidence.

---

# 4. Core Concepts

## Audit Record

Represents one immutable record of a data modification.

Every Audit Record has exactly one AuditId.

---

## Audit Subject

Identifies the business object being audited.

Examples

```
Quotation

Vehicle

Job Order

Customer

Invoice
```

The Audit Kernel references these entities but does not own them.

---

## Audit Change

Represents an individual property modification.

Examples

```
Status

Draft

↓

Submitted
```

```
Discount

10%

↓

15%
```

```
Estimated Cost

$125.00

↓

$150.00
```

Multiple changes may exist within a single Audit Record.

---

## Audit Actor

Identifies who or what performed the change.

Examples

- User
- System
- Scheduled Job
- Integration
- API Client

Audit actors reference the Identity Kernel.

---

## Audit Context

Provides additional execution context.

Examples

- Branch
- Organization
- Device
- IP Address
- Correlation ID
- Session ID

---

# 5. Owns

The Audit Kernel owns:

- Audit Record
- Audit Change
- Audit Context
- Audit Metadata

---

# 6. Does Not Own

The Audit Kernel never owns:

- Business entities
- Business workflows
- Activity timelines
- Notifications
- Integration events

Business Contexts own those concepts.

---

# 7. Public Contracts

Examples

```
RecordAudit()

GetAuditHistory()

GetEntityHistory()

GetFieldHistory()

SearchAudit()

ReconstructEntity()
```

These contracts expose historical information only.

Audit records must never be modified.

---

# 8. Published Events

Examples

```
AuditRecorded

AuditArchived
```

Business Contexts typically do not subscribe to Audit events.

Audit exists primarily for traceability.

---

# 9. Dependencies

The Audit Kernel may reference:

- Identity
- Organization
- Workflow (optional)

It must never depend on:

- Business Contexts
- Core Business Domains
- Platform Services

---

# 10. Data Ownership

The Audit Kernel owns:

- AuditId
- Subject Reference
- Changed Fields
- Previous Values
- New Values
- Actor Reference
- Timestamp
- Audit Metadata

The Audit Kernel does **not** own:

- Business entities
- Business validation
- Business calculations
- Workflow state

---

# 11. Example Usage

Commercial

```
Quotation

Status

Draft

↓

Submitted
```

Workshop

```
Job Order

Assigned Technician

John

↓

Maria
```

CRM

```
Customer

Phone Number

Old Value

↓

New Value
```

Each change is recorded independently of the Business Context.

---

# 12. Architectural Constraints

The Audit Kernel must satisfy the following constraints.

1. Audit records are immutable.
2. Audit records are append-only.
3. Every audit entry references an auditable subject.
4. Every audit entry records the responsible actor when available.
5. Historical records must never be overwritten.
6. Audit data must remain independent of business workflows.
7. Audit retrieval must not modify historical evidence.

---

# 13. Future Considerations

The Audit Kernel should support future capabilities including:

- configurable retention policies
- field masking for sensitive data
- cryptographic integrity verification
- export for compliance reporting
- tamper detection
- legal hold support
- cross-system correlation
- distributed tracing integration

These capabilities should extend the kernel without changing Business Contexts.

---

# 14. Example Audit History

```
09:02

Status

Draft

↓

Submitted

Actor

John Smith
```

```
09:15

Discount

10%

↓

15%

Actor

Sales Manager
```

```
09:17

Total Amount

$125.00

↓

$112.50

Actor

System
```

This history provides a complete reconstruction of changes made to the quotation.

---

# 15. Guiding Principle

The Audit Kernel answers one question:

> **Who changed what, when, and how?**

It does not determine:

- whether the change was valid
- whether the user was authorized
- whether a workflow transition occurred
- whether a notification was sent

Those responsibilities belong to other architectural components.

The Audit Kernel exists solely to preserve an immutable history of data changes for accountability, diagnostics, and compliance.