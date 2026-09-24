# Section Domain

## Education Domain Specification

**Version:** 1.1
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Section Domain defines the canonical representation of classroom and schedule assignments within the Education Domain Pack.

It owns section identity, schedule, room, and section status. Section is a **logistical** concept — it groups students for scheduling purposes but is **loosely coupled** from enrollment.

The Section Domain answers:

> **"When and where does this class meet?"**

It does not determine enrollment, faculty loading, evaluation, or consultation.

---

## 2. Responsibilities

The Section Domain is responsible for:

- section identity
- schedule (when classes meet)
- room assignment (where classes meet)
- section status (active/inactive)
- section validation
- section events

---

## 3. What the Section Domain Owns

Examples include:

- Section
- Section Name
- Schedule
- Room
- Section Status

These concepts belong exclusively to the Section Domain.

---

## 4. What the Section Domain Does NOT Own

The Section Domain does not own:

- Student enrollment — belongs to Enrollment Domain
- Faculty loading — belongs to Faculty Loading Domain
- Student identity — belongs to Student Domain
- Faculty identity — belongs to Faculty Domain
- Subject identity — belongs to Subject Domain
- Consultations — belongs to Consultation Business Context
- Evaluations — belongs to Evaluation Business Context

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

## 5. Ownership

The Section Domain owns:

- entities
- value objects
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts reference sections but never redefine section identity.

---

## 6. Core Concepts

The primary aggregate is:

```
Section
```

Supporting concepts include:

```
Section Name
Schedule
Room
Section Status
```

---

## 7. Relationships

The Section Domain may reference:

```
Subject (Subject Domain)
  ↓
Section
```

A section may be associated with:

- Students (via Student Section, loosely coupled)
- Faculty (via Faculty Loading, loosely coupled)
- Consultations
- Evaluations

The Section Domain does not own these relationships.

---

## 8. Business Rules

- A section belongs to a subject.
- A section has a unique name within a subject.
- A section may have a schedule and room assignment.
- Being in a section does NOT imply enrollment in all subjects of that section.
- A student may attend a section without formal enrollment (irregular students).
- Faculty Loading assigns faculty to a subject, not directly to a section (section is informational).
- Multiple sections can exist for the same subject (batches).

---

## 9. Lifecycle

Typical lifecycle:

```
Created
  ↓
Active
  ↓
Inactive
  ↓
Archived
```

---

## 10. Domain Events

```
SectionCreated
SectionActivated
SectionDeactivated
SectionScheduleChanged
```

---

## 11. Public Contracts

The Section Domain should expose stable contracts for:

- retrieving sections
- validating section identity
- checking section schedule
- determining section room assignment
- publishing section events

---

## 12. Anti-Patterns

### Enrollment Ownership

```
Section
  manages student enrollment
```

Enrollment management belongs to the Enrollment Domain.

### Faculty Loading Ownership

```
Section
  assigns faculty to subjects
```

Faculty assignment belongs to the Faculty Loading Domain.

### Tight Coupling

```
Enrollment
  requires section assignment
```

Section assignment is logistical and loosely coupled. Enrollment is per-subject, not per-section.

---

## 13. Guiding Principle

The Section Domain is the canonical source of section information.

It defines:

- what sections exist
- when sections meet (schedule)
- where sections meet (room)

It does not determine:

- who enrolls in subjects (Enrollment Domain)
- who teaches subjects (Faculty Loading Domain)
- how students are evaluated
- what consultations are booked

Those responsibilities belong to other Domains or Business Contexts.
