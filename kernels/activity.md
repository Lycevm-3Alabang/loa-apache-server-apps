# activity.md

# Automotive Business Platform
## Platform Kernel Specification – Activity

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Kernel
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Activity Kernel establishes the canonical representation of business activities across the Automotive Business Platform.

It answers one architectural question:

> **What meaningful business interaction occurred?**

Activities provide a unified timeline of business interactions regardless of which Business Context generated them.

---

# 2. Scope

The Activity Kernel is responsible for:

- Activity records
- Activity types
- Timeline entries
- Activity participants
- Activity references
- Activity chronology

The Activity Kernel is **not** responsible for:

- Workflow execution
- Audit history
- Domain events
- Business transactions
- Notifications

Those belong to other architectural components.

---

# 3. Responsibilities

The Activity Kernel provides a consistent way to record meaningful business interactions.

Activities may originate from:

- users
- automated processes
- external systems

Activities become part of a chronological business timeline.

---

# 4. Core Concepts

## Activity

Represents a business interaction.

Every Activity has exactly one ActivityId.

Examples

- Customer Called
- Email Sent
- SMS Sent
- Vehicle Checked In
- Quotation Submitted
- Appointment Scheduled
- Inspection Completed

---

## Activity Type

Defines the classification of an activity.

Examples

- Communication
- Customer Interaction
- Workflow
- System
- Reminder
- Note
- File Attachment

Activity Types are configurable.

---

## Activity Timeline

Represents an ordered history of activities associated with a business object.

Example

```
Customer

↓

Timeline

↓

Call Logged

↓

Quotation Sent

↓

Vehicle Checked In

↓

Job Completed
```

---

## Activity Reference

Associates an activity with another entity.

Examples

```
Party

↓

Activity
```

```
Vehicle

↓

Activity
```

```
Quotation

↓

Activity
```

An activity may reference multiple business objects.

---

## Activity Participant

Represents the parties involved in an activity.

Examples

- Customer
- Technician
- Service Advisor
- Supplier

---

# 5. Owns

The Activity Kernel owns:

- Activity
- Activity Type
- Timeline
- Activity Reference
- Activity Participant

---

# 6. Does Not Own

The Activity Kernel never owns:

- Quotations
- Job Orders
- CRM Opportunities
- Audit Records
- Workflow States
- Notifications

Business Contexts own those concepts.

---

# 7. Public Contracts

Examples

```
RecordActivity()

AttachReference()

AddParticipant()

GetTimeline()

SearchActivities()
```

---

# 8. Published Events

Examples

```
ActivityRecorded

ActivityUpdated

ActivityLinked

TimelineGenerated
```

---

# 9. Dependencies

The Activity Kernel may reference:

- Party
- Organization
- Identity
- Document

It must never depend on:

- Business Contexts
- Core Business Domains
- Platform Services

---

# 10. Data Ownership

The Activity Kernel owns:

- ActivityId
- Activity Type
- Activity Timestamp
- Participants
- References
- Timeline Ordering
- Activity Metadata

The Activity Kernel does **not** own:

- Business transactions
- Workflow definitions
- Audit changes
- Integration events

---

# 11. Example Usage

Commercial

```
Quotation Sent
```

CRM

```
Customer Called
```

Workshop

```
Vehicle Checked In
```

Fleet

```
Maintenance Scheduled
```

All appear in a consistent timeline regardless of their originating Business Context.

---

# 12. Architectural Constraints

The Activity Kernel must satisfy the following constraints.

1. Activities are append-only.
2. Activities must preserve chronological order.
3. Activities may reference multiple business objects.
4. Activities never replace business transactions.
5. Activities should be human-readable.
6. Timeline generation must be independent of Business Contexts.

---

# 13. Future Considerations

The Activity Kernel should support:

- threaded conversations
- mentions
- reactions
- reminders
- calendar integration
- AI-generated summaries
- timeline filtering
- activity categorization

These enhancements should not require changes to Business Contexts.

---

# 14. Example Timeline

```
08:30

Customer Called

↓

09:15

Quotation Sent

↓

10:40

Vehicle Checked In

↓

11:05

Inspection Started

↓

13:20

Repair Completed

↓

15:00

Vehicle Released
```

Each activity references the originating business object while remaining independent of its implementation.

---

# 15. Guiding Principle

The Activity Kernel answers one question:

> **What meaningful business interaction occurred?**

It does not determine:

- whether the interaction changed business state
- whether it modified data
- whether it triggered integrations
- whether it was authorized

Those responsibilities belong to other architectural components.

The Activity Kernel provides a unified business timeline that spans every application within the platform.