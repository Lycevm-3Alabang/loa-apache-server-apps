# domains/education/section.md

# Section Domain
## Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Section Domain defines the canonical representation of class groupings within a program.

It owns section identity, name, program affiliation, and classification.

The Section Domain answers:

> **"Which class grouping is this?"**

It does not determine enrollment, scheduling, or evaluation.

---

# 2. Responsibilities

The Section Domain is responsible for:

- section identity
- section name
- program affiliation
- section classification
- section validation
- section events

---

# 3. What the Section Domain Owns

Examples include:

- Section
- Section Name
- Program
- Department Course
- Section Status

These concepts belong exclusively to the Section Domain.

---

# 4. What the Section Domain Does NOT Own

The Section Domain does not own:

- Students
- Faculty
- Subjects
- Enrollments
- Consultations
- Evaluations
- Semesters

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Section Domain owns:

- entities
- value objects
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts reference sections but never redefine section identity.

---

# 6. Core Concepts

The primary aggregate is:

```
Section
```

Supporting concepts include:

```
Section Name
Program
Department Course
Section Status
```

---

# 7. Relationships

The Section Domain may reference:

```
Course (program)

↓

Section
```

A section may be associated with:

- Students (via enrollment)
- Faculty (via faculty-subject)
- Subjects
- Consultations
- Evaluations

The Section Domain does not own these relationships.

---

# 8. Business Rules

Examples include:

- Every section has a unique name within a program.
- Sections belong to exactly one department course.
- A section may exist without enrolled students.
- Section names are immutable within a program.

---

# 9. Lifecycle

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

# 10. Domain Events

Examples include:

```
SectionCreated

SectionUpdated

SectionActivated

SectionArchived
```

---

# 11. Public Contracts

The Section Domain should expose stable contracts for:

- retrieving sections
- validating section identity
- retrieving section details
- publishing section events

---

# 12. Anti-Patterns

The following are architectural violations.

## Enrollment Ownership

```
Section

manages Student enrollment
```

Enrollment management belongs to the Enrollment Domain.

---

## Consultation Ownership

```

Section

creates Consultation slots
```

Consultation scheduling belongs to the Consultation Business Context.

---

# 13. Guiding Principle

The Section Domain is the canonical source of section information.

It defines:

- what sections exist
- how sections are identified
- which program a section belongs to

It does not determine:

- who enrolls in a section
- what subjects are taught in a section
- how section students are evaluated

Those responsibilities belong to other Domains or Business Contexts.
