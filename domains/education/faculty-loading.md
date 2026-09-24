# Faculty Loading Domain

## Education Domain Specification

**Version:** 1.1
**Status:** Final
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Faculty Loading Domain defines the canonical representation of faculty subject assignments within the Education Domain Pack.

It owns the relationship between faculty members and the subjects they teach.

The Faculty Loading Domain answers:

> **"Which faculty member teaches which subject?"**

It does not determine student enrollment, section scheduling, consultation, or evaluation.

---

## 2. Responsibilities

The Faculty Loading Domain is responsible for:

- faculty-subject assignments
- assignment validation
- assignment lifecycle
- assignment events
- assignment constraints (one faculty per subject)

---

## 3. What the Faculty Loading Domain Owns

Examples include:

- Faculty Subject Assignment
- Faculty-Subject Link
- Assignment Status

These concepts belong exclusively to the Faculty Loading Domain.

---

## 4. What the Faculty Loading Domain Does NOT Own

The Faculty Loading Domain does not own:

- Faculty identity — belongs to Faculty Domain
- Subject identity — belongs to Subject Domain
- Section scheduling — belongs to Section Domain (logistical)
- Student enrollment — belongs to Enrollment Domain
- Consultations — belongs to Consultation Business Context
- Evaluations — belongs to Evaluation Business Context

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

## 5. Ownership

The Faculty Loading Domain owns:

- entities
- value objects
- validation
- assignment rules
- lifecycle rules
- domain events
- public contracts

Business Contexts reference faculty loading but never redefine assignment logic.

---

## 6. Core Concepts

The primary aggregate is:

```
Faculty Subject Assignment
```

Supporting concepts include:

```
Faculty-Subject Link
Assignment Status
```

---

## 7. Relationships

The Faculty Loading Domain may reference:

```
Faculty (Faculty Domain)
  ↓
Subject Assignment
  ↓
Subject (Subject Domain)
```

A faculty loading assignment may be associated with:

- Sections (via Section Domain, loosely coupled)
- Consultations
- Evaluations

The Faculty Loading Domain does not own these relationships.

---

## 8. Business Rules

- A faculty member can be assigned to teach a subject.
- A subject can have multiple faculty members (different sections/batches).
- A faculty member can teach multiple subjects.
- Assignment references faculty by `employees.id` FK (Faculty Domain ID), never by opaque Auth sub or embedded data. Identity is referenced by JWT claims only.
- Assignment references subject by ID, not by embedded data.
- Duplicate assignments (same faculty + same subject) are prohibited.
- Section assignment is separate and loosely coupled (logistical).

---

## 9. Lifecycle

Typical lifecycle:

```
Assigned
  ↓
Active (teaching)
  ↓
Completed (semester ended)
  ↓
Archived
```

---

## 10. Domain Events

```
FacultyAssignedToSubject
FacultyUnassignedFromSubject
FacultyLoadingUpdated
```

---

## 11. Public Contracts

The Faculty Loading Domain should expose stable contracts for:

- assigning faculty to subjects
- unassigning faculty from subjects
- checking faculty loading status
- retrieving faculty assignments
- publishing faculty loading events

---

## 12. Anti-Patterns

### Section Coupling

```
Faculty Loading
  requires section assignment
```

Section assignment is logistical and loosely coupled. Faculty Loading does not require section assignment.

### Enrollment Ownership

```
Faculty Loading
  manages student enrollment
```

Student enrollment belongs to the Enrollment Domain.

---

## 13. Guiding Principle

The Faculty Loading Domain is the canonical source of faculty subject assignment information.

It defines:

- which faculty teach which subjects
- how assignments are tracked
- assignment constraints

It does not determine:

- which sections faculty are assigned to (logistical)
- which students are enrolled in subjects
- how faculty are evaluated
- what consultations faculty conduct

Those responsibilities belong to other Domains or Business Contexts.
