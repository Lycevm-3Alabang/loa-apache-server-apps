# Faculty Domain

## Education Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Faculty Domain defines the canonical representation of faculty profiles within the Education Domain Pack.

It owns faculty identity (academic), faculty profile attributes, and faculty status.

The Faculty Domain answers:

> **"Who is the faculty member and what department are they in?"**

It does not determine subject assignments, consultation scheduling, or evaluation.

---

## 2. Responsibilities

The Faculty Domain is responsible for:

- faculty academic identity (employee_number)
- department affiliation
- faculty status (active/inactive)
- faculty profile attributes
- faculty validation
- faculty events

---

## 3. What the Faculty Domain Owns

Examples include:

- Faculty Profile
- Employee Number
- Department Affiliation
- Faculty Status

These concepts belong exclusively to the Faculty Domain.

---

## 4. What the Faculty Domain Does NOT Own

The Faculty Domain does not own:

- User identity (name, email) — belongs to Identity Kernel
- Subject assignments (Faculty Loading) — belongs to Faculty Loading Domain
- Section scheduling — belongs to Section Domain (logistical)
- Consultations — belongs to Consultation Business Context
- Evaluations — belongs to Evaluation Business Context
- Availability rules — belongs to Consultation Business Context

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

## 5. Ownership

The Faculty Domain owns:

- entities
- value objects
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts reference faculty but never redefine faculty identity.

---

## 6. Core Concepts

The primary aggregate is:

```
Faculty Profile
```

Supporting concepts include:

```
Employee Number
Department Affiliation
Faculty Status
```

---

## 7. Relationships

The Faculty Domain may reference:

```
User (Identity Kernel)
  ↓
Faculty Profile
  ↓
Department (Department Domain)
```

A faculty member may be associated with:

- Subject assignments (via Faculty Loading Domain)
- Sections (via Section Domain, loosely coupled)
- Consultations
- Evaluations
- Availability rules

The Faculty Domain does not own these relationships.

---

## 8. Business Rules

- Every faculty member has a unique employee_number (nullable — may not be assigned yet).
- A faculty member belongs to exactly one department.
- A faculty member may be active or inactive.
- Faculty identity (name, email) comes from the Identity Kernel via SSO.
- A user can be both a student and a faculty (dual role).
- Faculty profile is created on first SSO login (email domain: `*@lyceumalabang.edu.ph`).

---

## 9. Lifecycle

Typical lifecycle:

```
Created (on first SSO login)
  ↓
Active
  ↓
Inactive (transferred, resigned, retired)
  ↓
Archived
```

---

## 10. Domain Events

```
FacultyCreated
FacultyActivated
FacultyDeactivated
FacultyDepartmentChanged
```

---

## 11. Public Contracts

The Faculty Domain should expose stable contracts for:

- retrieving faculty members
- validating faculty identity (employee_number)
- checking faculty status
- determining faculty department affiliation
- publishing faculty events

---

## 12. Anti-Patterns

### Loading Ownership

```
Faculty
  assigns own subjects
```

Subject assignment (Faculty Loading) belongs to the Faculty Loading Domain.

### Consultation Ownership

```
Faculty
  schedules own consultations
```

Consultation scheduling belongs to the Consultation Business Context.

---

## 13. Guiding Principle

The Faculty Domain is the canonical source of faculty profile information.

It defines:

- who is a faculty member
- how faculty are identified (employee_number)
- what department a faculty member belongs to

It does not determine:

- what subjects a faculty member teaches
- what sections a faculty member is assigned to
- how faculty are evaluated
- what consultations a faculty member conducts

Those responsibilities belong to other Domains or Business Contexts.
