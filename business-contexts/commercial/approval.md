# Approval
## Business Capability Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Commercial
**Capability:** Approval
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Approval capability governs the authorization of commercial transactions before they become customer-facing or legally binding.

It defines approval requests, approval decisions, approval policies, and approval outcomes.

The Approval capability answers:

> **"Is this quotation authorized to proceed?"**

It does not determine pricing, commercial negotiations, or customer acceptance.

---

# 2. Responsibilities

The Approval capability is responsible for:

- approval requests
- approval workflows
- approval decisions
- approval history
- approval policies
- approval validation
- approval events

---

# 3. What Approval Owns

Examples include:

- Approval Request
- Approval Decision
- Approval Status
- Approval Level
- Approval History
- Approval Reason
- Approval Outcome

These concepts belong exclusively to the Approval capability.

---

# 4. What Approval Does NOT Own

Approval does not own:

- Quotations
- Customers
- Pricing Rules
- Discounts
- Commercial Policies
- User Accounts
- Roles
- Authentication

Those belong to Platform Kernels, Industry Domains, or other Business Contexts.

---

# 5. Ownership

Approval owns:

- approval lifecycle
- approval decisions
- approval history
- validation
- approval events

It references quotations without owning them.

---

# 6. Relationships

Approval references:

```
Quotation

Identity

Organization
```

These references provide business context.

Ownership remains with their respective Platform Kernels and Business Contexts.

---

# 7. Business Rules

Examples include:

- Every approval belongs to one quotation.
- A quotation may require multiple approval levels.
- An approval decision is immutable once recorded.
- A rejected approval requires a new approval request before resubmission.
- Approval requirements are determined by commercial policies.
- Approval history must be preserved.

---

# 8. Lifecycle

Typical lifecycle:

```
Pending

↓

In Review

↓

Approved
        │
        ├── Rejected
        ├── Cancelled
        └── Expired
```

---

# 9. Domain Events

Examples include:

```
ApprovalRequested

ApprovalAssigned

ApprovalApproved

ApprovalRejected

ApprovalCancelled

ApprovalExpired
```

---

# 10. Public Contracts

The Approval capability should expose stable contracts for:

- requesting approval
- assigning approvers
- recording approval decisions
- retrieving approval history
- publishing approval events

---

# 11. Consumers

Approval information may be consumed by:

- Commercial
- CRM
- Reporting
- Audit

Consumers interact through published contracts.

---

# 12. Anti-Patterns

The following are architectural violations.

## Pricing Ownership

```
Approval

calculates Pricing
```

Pricing belongs to the Pricing Domain.

---

## Quotation Ownership

```
Approval

owns Quotation
```

Quotation remains the Aggregate Root.

---

## Identity Ownership

```
Approval

defines Users
```

Users belong to the Identity Kernel.

---

## Workflow Ownership

```
Approval

implements Workflow Engine
```

Workflow orchestration belongs to the Workflow Kernel.

Approval defines approval decisions, not workflow infrastructure.

---

# 13. Future Evolution

Approval may evolve to support:

- configurable approval policies
- delegated approvals
- parallel approvals
- conditional approvals
- risk-based approvals
- digital approvals
- electronic signatures

Future additions should continue to represent approval decisions without assuming ownership of commercial transactions or workflow infrastructure.

---

# 14. Guiding Principle

The Approval capability is the canonical representation of commercial authorization.

It defines:

- who approved
- what was approved
- when approval occurred
- the approval outcome
- the approval history

It does not define:

- the quotation
- pricing rules
- customers
- workflow infrastructure

Those responsibilities belong to their respective Platform Kernels, Industry Domains, and Business Contexts.