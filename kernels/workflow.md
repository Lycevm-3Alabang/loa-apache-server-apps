# workflow.md

# Automotive Business Platform
## Platform Kernel Specification – Workflow

**Version:** 1.0  
**Status:** Approved  
**Layer:** Platform Kernel  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Workflow Kernel provides the canonical framework for defining and executing state-based lifecycles within the Automotive Business Platform.

It answers one architectural question:

> **What is the current lifecycle state of a business object, and what transitions are allowed?**

The Workflow Kernel provides reusable workflow capabilities but does not define business-specific workflows.

Business Contexts define their own workflow definitions using the capabilities provided by this kernel.

---

# 2. Scope

The Workflow Kernel is responsible for:

- Workflow definitions
- States
- State transitions
- Transition validation
- Workflow instances
- Transition history
- Workflow events

The Workflow Kernel is **not** responsible for:

- Business rules
- Business approvals
- Quotations
- Job Orders
- Purchase Orders
- Invoices
- Inventory transactions

Those belong to Business Contexts.

---

# 3. Responsibilities

The Workflow Kernel owns the infrastructure required to execute workflows.

It provides:

- reusable state machines
- transition validation
- lifecycle management
- transition history
- workflow metadata

Business Contexts define their own states and transitions.

---

# 4. Core Concepts

## Workflow Definition

Defines the structure of a workflow.

Examples

- Quotation Workflow
- Job Order Workflow
- Purchase Order Workflow
- Invoice Workflow

A definition contains states and transitions.

---

## State

Represents a valid lifecycle stage.

Examples

```
Draft

Submitted

Approved

Rejected

Completed

Cancelled
```

State names are defined by Business Contexts.

The Workflow Kernel does not prescribe them.

---

## Transition

Represents a permitted movement between two states.

Example

```
Draft

↓

Submitted
```

Transitions are directional.

---

## Workflow Instance

Represents the lifecycle of a specific business object.

Example

```
Quotation #1001

Current State

Submitted
```

---

## Transition History

Records every successful state transition.

History is immutable.

---

# 5. Owns

The Workflow Kernel owns:

- Workflow Definitions
- States
- Transitions
- Workflow Instances
- Transition History

---

# 6. Does Not Own

The Workflow Kernel never owns:

- Quotations
- Job Orders
- Purchase Orders
- Inventory Transactions
- Service Requests
- Business Rules
- Approval Logic

Business Contexts own those concepts.

---

# 7. Public Contracts

Examples

```
CreateWorkflow()

RegisterState()

RegisterTransition()

StartWorkflow()

Transition()

GetCurrentState()

GetHistory()

CanTransition()
```

Business Contexts consume these contracts.

---

# 8. Published Events

Examples

```
WorkflowStarted

StateChanged

TransitionSucceeded

TransitionRejected

WorkflowCompleted

WorkflowCancelled
```

Business Contexts may subscribe to these events.

---

# 9. Dependencies

The Workflow Kernel may reference:

- Events Kernel
- Activity Kernel (optional)
- Audit Kernel (optional)

It must never depend on:

- Core Business Domains
- Platform Services
- Business Contexts

---

# 10. Data Ownership

The Workflow Kernel owns:

- WorkflowId
- Workflow Definition
- Current State
- Allowed Transitions
- Transition History
- Workflow Metadata

The Workflow Kernel does **not** own:

- Business data
- Approval decisions
- Business calculations
- Domain validation

---

# 11. Example Usage

Commercial Context

```
Quotation

↓

Workflow Instance

Draft

↓

Submitted

↓

Approved
```

Service Context

```
Job Order

↓

Workflow Instance

Assigned

↓

In Progress

↓

Completed
```

Inventory Context

```
Stock Transfer

↓

Workflow Instance

Draft

↓

Released

↓

Received
```

Each Business Context owns its workflow definition.

The Workflow Kernel executes it.

---

# 12. Architectural Constraints

The Workflow Kernel must satisfy the following constraints.

1. Workflow definitions are independent of business data.
2. States are immutable once referenced by historical records.
3. Every transition must have a defined source and destination state.
4. Invalid transitions must be rejected.
5. Transition history is immutable.
6. Business Contexts define workflow semantics.
7. The Workflow Kernel executes workflows but does not interpret business meaning.

---

# 13. Future Considerations

The Workflow Kernel should support future capabilities including:

- Conditional transitions
- Parallel workflows
- Nested workflows
- Timed transitions
- Escalation rules
- State timeouts
- Dynamic workflow definitions
- Versioned workflows

These capabilities should extend the kernel without affecting Business Context implementations.

---

# 14. Example Workflow Definitions

Quotation

```
Draft
    ↓
Submitted
    ↓
Approved
```

Job Order

```
Draft
    ↓
Assigned
    ↓
In Progress
    ↓
Completed
```

Purchase Order

```
Draft
    ↓
Approved
    ↓
Ordered
    ↓
Received
```

Notice that the Workflow Kernel does not know what a quotation, job order, or purchase order is.

It only manages lifecycle progression.

---

# 15. Guiding Principle

The Workflow Kernel answers one question:

> **What lifecycle state is this business object currently in, and what transitions are permitted?**

It does not determine:

- Why a transition occurs.
- Who may perform it.
- What business rules apply.
- What business object is being managed.

Those responsibilities belong to Business Contexts.

By separating workflow execution from business meaning, the platform provides a reusable lifecycle engine that can be shared across every application.