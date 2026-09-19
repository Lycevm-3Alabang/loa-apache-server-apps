# Enrollment Domain

## Education Domain Specification

**Version:** 1.1
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Enrollment Domain defines the canonical representation of student registration in subjects within the Education Domain Pack.

It owns enrollment identity, student-subject relationships, and enrollment lifecycle.

The Enrollment Domain answers:

> **"Which students are enrolled in which subjects?"**

It does not determine section scheduling, grading, evaluation, consultation, or certificate generation.

---

## 2. Responsibilities

The Enrollment Domain is responsible for:

- enrollment identity
- student-subject relationships
- enrollment validation
- enrollment lifecycle
- enrollment events

---

## 3. What the Enrollment Domain Owns

Examples include:

- Student Enrollment
- Student-Subject Link
- Enrollment Status

These concepts belong exclusively to the Enrollment Domain.

---

## 4. What the Enrollment Domain Does NOT Own

The Enrollment Domain does not own:

- Student identity (student_number, course) — belongs to Student Domain
- Faculty identity — belongs to Faculty Domain
- Faculty Loading (faculty-subject assignments) — belongs to Faculty Loading Domain
- Section assignments — belongs to Section Domain (logistical)
- Subjects — belongs to Subject Domain
- Consultations — belongs to Consultation Business Context
- Evaluations — belongs to Evaluation Business Context
- Grades — belongs to Grading Business Context (future)

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

## 5. Ownership

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

## 6. Core Concepts

The primary aggregate is:

```
Student Enrollment
```

Supporting concepts include:

```
Student-Subject Link
Enrollment Status
```

---

## 7. Relationships

The Enrollment Domain may reference:

```
Student (Student Domain)
  ↓
Subject (Subject Domain)
```

An enrollment may be associated with:

- Sections (via Section Domain, loosely coupled — informational)
- Consultations
- Evaluations

The Enrollment Domain does not own these relationships.

---

## 8. Business Rules

- A student can enroll in a subject once per semester.
- Enrollment references student by user_id (opaque Auth sub), not by embedded data.
- Enrollment references subject by ID, not by embedded data.
- Enrollment can be active or inactive.
- Duplicate enrollments (same student + same subject) are prohibited.
- Being in a section does NOT imply enrollment in all subjects of that section.
- Irregular students may exist without section assignment.

---

## 9. Lifecycle

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

## 10. Domain Events

```
StudentEnrolled
StudentWithdrawn
EnrollmentActivated
EnrollmentCompleted
```

---

## 11. Public Contracts

The Enrollment Domain should expose stable contracts for:

- enrolling students in subjects
- withdrawing students from subjects
- checking enrollment status
- retrieving enrollments by student or subject
- publishing enrollment events

---

## 12. Anti-Patterns

### Section Coupling

```
Enrollment
  requires section assignment
```

Section assignment is logistical and loosely coupled. Enrollment does not require section assignment.

### Faculty Loading Ownership

```
Enrollment
  assigns faculty to subjects
```

Faculty assignment belongs to the Faculty Loading Domain.

### Grade Ownership

```
Enrollment
  calculates grades
```

Grading belongs to a Grading Business Context (future).

---

## 13. Guiding Principle

The Enrollment Domain is the canonical source of enrollment information.

It defines:

- which students are enrolled in which subjects
- how enrollments are tracked

It does not determine:

- which sections students attend (logistical)
- which faculty teach subjects (Faculty Loading Domain)
- how students are evaluated
- what grades are assigned
- what certificates are issued

Those responsibilities belong to other Domains or Business Contexts.
