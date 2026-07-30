# domains/education/enrollment.md

# Enrollment Domain
## Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Enrollment Domain defines the canonical representation of student registration in sections.

It owns enrollment identity, student-section relationships, and enrollment lifecycle.

The Enrollment Domain answers:

> **"Which students are registered in which sections?"**

It does not determine grading, evaluation, consultation, or certificate generation.

---

# 2. Responsibilities

The Enrollment Domain is responsible for:

- enrollment identity
- student-section relationships
- faculty-subject-section assignments
- enrollment validation
- enrollment lifecycle
- enrollment events

---

# 3. What the Enrollment Domain Owns

Examples include:

- Student Enrollment
- Faculty Subject Assignment
- Student-Section Link
- Faculty-Subject-Section Link
- Enrollment Status

These concepts belong exclusively to the Enrollment Domain.

---

# 4. What the Enrollment Domain Does NOT Own

The Enrollment Domain does not own:

- Students (identity belongs to Identity Kernel)
- Sections (identity belongs to Section Domain)
- Subjects (identity belongs to Subject Domain)
- Semesters (identity belongs to Semester Domain)
- Consultations
- Evaluations
- Grades
- Certificates

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Enrollment Domain owns:

- entities
- value objects
- validation
- enrollment rules
- lifecycle rules
- domain events
- public contracts

Business Contexts reference enrollments but never redefine enrollment logic.

---

# 6. Core Concepts

The primary aggregates are:

```
Student Enrollment
Faculty Subject Assignment
```

Supporting concepts include:

```
Student-Section Link
Faculty-Subject-Section Link
Enrollment Status
```

---

# 7. Relationships

The Enrollment Domain may reference:

```
Student (Identity Kernel)

↓

Section (Section Domain)

↓

Subject (Subject Domain)

↓

Semester (Semester Domain)
```

An enrollment may be associated with:

- Consultations
- Evaluations
- Certificates

The Enrollment Domain does not own these relationships.

---

# 8. Business Rules

Examples include:

- A student can enroll in a section once per semester.
- Faculty can be assigned to one subject-section combination.
- Enrollment references student by ID, not by embedded data.
- Enrollment references section by ID, not by embedded data.
- Enrollment can be active or inactive.
- Duplicate enrollments are prohibited.

---

# 9. Lifecycle

Typical lifecycle:

```
Registered

↓

Active

↓

Withdrawn

↓

Completed
```

---

# 10. Domain Events

Examples include:

```
StudentEnrolled

StudentWithdrawn

FacultyAssigned

FacultyUnassigned
```

---

# 11. Public Contracts

The Enrollment Domain should expose stable contracts for:

- enrolling students
- withdrawing students
- assigning faculty to subjects
- unassigning faculty
- checking enrollment status
- publishing enrollment events

---

# 12. Anti-Patterns

The following are architectural violations.

## Student Identity Ownership

```
Enrollment

stores Student name and email
```

Student identity belongs to the Identity Kernel. Reference by Student ID.

---

## Grade Ownership

```
Enrollment

calculates grades
```

Grading belongs to a Grading Business Context (future).

---

## Consultation Ownership

```

Enrollment

creates Consultations
```

Consultation creation belongs to the Consultation Business Context.

---

# 13. Guiding Principle

The Enrollment Domain is the canonical source of enrollment information.

It defines:

- which students are in which sections
- which faculty teach which subjects
- how enrollments are tracked

It does not determine:

- how students are evaluated
- what grades are assigned
- what certificates are issued
- what consultations are booked

Those responsibilities belong to other Domains or Business Contexts.
