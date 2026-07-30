# domains/education/semester.md

# Semester Domain
## Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Semester Domain defines the canonical representation of academic terms within the Education Domain Pack.

It owns semester identity, date ranges, evaluation periods, and term lifecycle.

The Semester Domain answers:

> **"Which academic term is this?"**

It does not determine scheduling, enrollment, grading, or certificate generation.

---

# 2. Responsibilities

The Semester Domain is responsible for:

- semester identity
- semester date ranges
- evaluation period windows
- term classification
- semester status
- semester validation
- semester events

---

# 3. What the Semester Domain Owns

Examples include:

- Semester
- Semester Title
- Start Date
- End Date
- Evaluation Start Date
- Evaluation End Date
- Is Active Flag
- Semester Status

These concepts belong exclusively to the Semester Domain.

---

# 4. What the Semester Domain Does NOT Own

The Semester Domain does not own:

- Students
- Faculty
- Courses
- Subjects
- Sections
- Enrollments
- Consultations
- Evaluations
- Certificates
- Grades

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Semester Domain owns:

- entities
- value objects
- validation
- date rules
- lifecycle rules
- domain events
- public contracts

Business Contexts reference semesters but never redefine semester identity or structure.

---

# 6. Core Concepts

The primary aggregate is:

```
Semester
```

Supporting concepts include:

```
Semester Title
Academic Year
Evaluation Window
Start Date
End Date
Is Active
```

---

# 7. Relationships

The Semester Domain may reference Platform Kernels.

Examples:

```
Semester

↓

Organization (institution)
```

A semester may be associated with:

- Enrollments
- Evaluations
- Consultations
- Faculty assignments

The Semester Domain does not own these relationships.

The Semester Domain may also be referenced by:

- Enrollment
- Evaluation
- Consultation
- Certificate

---

# 8. Business Rules

Examples include:

- Every semester has a unique title.
- Only one semester can be active at a time.
- Evaluation dates must fall within semester dates.
- Start date must be before end date.
- Evaluation start must be before evaluation end.
- A semester may exist before enrollments begin.
- Past semesters cannot be activated.

---

# 9. Lifecycle

Typical lifecycle:

```
Planning

↓

Registration

↓

Active

↓

Evaluation

↓

Completed

↓

Archived
```

Business Contexts determine operational status.

The Semester Domain defines only the term lifecycle.

---

# 10. Domain Events

Examples include:

```
SemesterCreated

SemesterActivated

SemesterEvaluationStarted

SemesterCompleted

SemesterArchived
```

---

# 11. Public Contracts

The Semester Domain should expose stable contracts for:

- retrieving semesters
- validating semester identity
- checking if evaluation window is open
- determining active semester
- publishing semester events

---

# 12. Consumers

Expected consumers include:

- Consultation
- Evaluation
- Certificate
- Enrollment
- Reporting

The Semester Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Evaluation Ownership

```
Semester

defines Evaluation rubrics
```

Rubric definitions belong to the Evaluation Business Context.

---

## Enrollment Ownership

```

Semester

manages Student enrollment
```

Enrollment management belongs to the Enrollment Domain.

---

## Consultation Ownership

```

Semester

creates Consultation slots
```

Consultation scheduling belongs to the Consultation Business Context.

---

# 14. Future Evolution

The Semester Domain may evolve to support:

- academic year grouping
- intersession terms
- summer terms
- cross-semester enrollments
- semester equivalency rules
- historical semester archives

Future additions should continue to represent term knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Semester Domain is the canonical source of academic term information.

It defines:

- what terms exist
- when terms begin and end
- when evaluation windows are open
- which term is currently active

It does not determine:

- how students enroll
- how faculty are assigned
- how evaluations are conducted
- what certificates are issued

Those responsibilities belong to Platform Kernels, other Domains, or Business Contexts.
