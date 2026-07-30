# Follow-up
## Aggregate Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** CRM
**Aggregate:** Follow-up
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Follow-up aggregate represents a planned future engagement intended to continue or strengthen a customer relationship.

Follow-ups ensure that commitments, customer requests, sales opportunities, and relationship-building activities are not forgotten.

The Follow-up aggregate answers:

> **"What needs to happen next, and when?"**

It does not own customer communications, customer interactions, quotations, or scheduling infrastructure.

---

# 2. Responsibilities

The Follow-up aggregate is responsible for:

- follow-up planning
- follow-up ownership
- follow-up scheduling
- follow-up prioritization
- follow-up completion
- follow-up history
- follow-up events

---

# 3. What the Follow-up Aggregate Owns

Examples include:

- Follow-up
- Follow-up Number
- Follow-up Type
- Follow-up Status
- Due Date
- Priority
- Assigned User
- Reminder Rules
- Completion Result
- Follow-up Notes

The Follow-up aggregate owns these concepts completely.

---

# 4. What the Follow-up Aggregate Does NOT Own

The Follow-up aggregate does not own:

- Customers
- Leads
- Prospects
- Opportunities
- Communications
- Activities
- Calendar
- Notifications

These belong to Platform Kernels, Platform Services, or other Business Contexts.

---

# 5. Ownership

The Follow-up aggregate owns:

- follow-up lifecycle
- scheduling metadata
- completion history
- business validation
- domain events

The Follow-up aggregate is the consistency boundary for all follow-up operations.

---

# 6. Aggregate Structure

```
Follow-up
│
├── Assignment
├── Reminder
├── Schedule
├── Outcome
└── References
```

All child entities exist only within the lifetime of a Follow-up.

---

# 7. Relationships

The Follow-up aggregate references:

```
Lead

Prospect

Opportunity

Activity

Customer Interaction

Communication

Party
```

These references provide business context only.

Ownership remains with their respective Platform Kernels and Business Contexts.

---

# 8. Business Rules

Examples include:

- Every follow-up has an owner.
- Every follow-up has a due date.
- Every follow-up has a status.
- Completed follow-ups cannot become pending again.
- Cancelled follow-ups cannot be completed.
- A completed follow-up may generate another follow-up.

---

# 9. Lifecycle

Typical lifecycle:

```
Planned

↓

Scheduled

↓

Pending

↓

Completed
        │
        ├── Cancelled
        └── Expired
```

Completed follow-ups become part of the permanent customer relationship history.

---

# 10. Domain Events

Examples include:

```
FollowUpCreated

FollowUpAssigned

FollowUpScheduled

FollowUpCompleted

FollowUpCancelled

FollowUpExpired
```

---

# 11. Public Contracts

The Follow-up aggregate should expose stable contracts for:

- creating follow-ups
- assigning follow-ups
- rescheduling follow-ups
- completing follow-ups
- retrieving follow-up history
- publishing follow-up events

---

# 12. Consumers

Follow-up information may be consumed by:

- CRM
- Reporting
- Executive Dashboards
- Customer Portal
- Notification Services

Consumers interact through published contracts.

---

# 13. Aggregate Invariants

The following invariants must always hold:

- Every follow-up has an owner.
- Every follow-up has a due date.
- Every follow-up has a valid status.
- Completed follow-ups are immutable.
- Historical follow-ups are preserved.
- Every lifecycle transition must be valid.

These invariants are enforced by the aggregate root.

---

# 14. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Follow-up

owns Customer
```

Customer belongs to the Party Kernel.

---

## Calendar Ownership

```
Follow-up

implements Calendar
```

Scheduling belongs to the Scheduling Domain or Platform Services.

---

## Notification Ownership

```
Follow-up

sends Emails
```

Notification delivery belongs to Platform Services.

---

## Commercial Ownership

```
Follow-up

creates Quotations
```

Commercial owns quotations.

---

# 15. Future Evolution

The Follow-up aggregate may evolve to support:

- recurring follow-ups
- SLA-driven follow-ups
- AI-generated recommendations
- escalation policies
- workload balancing
- automated reminders
- mobile follow-up support
- customer self-service follow-ups

Future additions should continue to represent planned relationship activities without assuming ownership of scheduling or notification infrastructure.

---

# 16. Guiding Principle

The Follow-up aggregate is the canonical representation of planned future customer engagement.

It owns:

- follow-up planning
- ownership
- scheduling metadata
- completion history
- follow-up lifecycle

It references, but never owns:

- customers
- communications
- notifications
- calendar infrastructure
- quotations

Those responsibilities belong to Platform Kernels, Platform Services, and other Business Contexts.