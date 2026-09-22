# Student Domain

## Education Domain Specification

**Version:** 1.1
**Status:** Final
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Student Domain defines the canonical representation of student profiles within the Education Domain Pack.

It owns student identity (academic), student profile attributes, and student status.

The Student Domain answers:

> **"Who is the student and what program are they in?"**

It does not determine enrollment, grading, consultation, or evaluation.

---

## 2. Responsibilities

The Student Domain is responsible for:

- student academic identity (student_number)
- program affiliation (course)
- student status (active/inactive)
- student profile attributes
- student validation
- student events

---

## 3. What the Student Domain Owns

Examples include:

- Student Profile (first-class cache: `name`, `email` UNIQUE, upserted from SSO JWT claims on first login; Auth-platform promotion optional by email, same pattern as cert `event_attendees`)
- Student Number
- Course Affiliation
- Student Status

These concepts belong exclusively to the Student Domain. Identity Kernel remains the sole authority for authentication; `name`/`email` here are a domain-owned cache for tenant membership, never credentials.

---

## 4. What the Student Domain Does NOT Own

The Student Domain does not own:

- User authentication / credentials — belongs to Identity Kernel (Auth Platform, sole authority for login, tokens, passwords)
- Subject enrollments — belongs to Enrollment Domain
- Section assignments — belongs to Section Domain (logistical)
- Consultations — belongs to Consultation Business Context
- Evaluations — belongs to Evaluation Business Context
- Grades — belongs to Grading Business Context (future)

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

## 5. Ownership

The Student Domain owns:

- entities
- value objects
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts reference students but never redefine student identity.

---

## 6. Core Concepts

The primary aggregate is:

```
Student Profile
```

Supporting concepts include:

```
Student Number
Course Affiliation
Student Status
```

---

## 7. Relationships

The Student Domain may reference:

```
User (Identity Kernel)
  ↓
Student Profile
  ↓
Course (Course Domain)
```

A student may be associated with:

- Enrollments (via Enrollment Domain)
- Sections (via Section Domain, loosely coupled)
- Consultations
- Evaluations

The Student Domain does not own these relationships.

---

## 8. Business Rules

- Every student has a unique student_number.
- A student belongs to exactly one course (program).
- A student may be active or inactive.
- `name`/`email` are first-class cached attributes (upserted by `email` from SSO JWT claims on login; `email` UNIQUE; domain `*@itmlyceumalabang.onmicrosoft.com`). Identity Kernel is the auth authority, never read via SQL — claims only.
- A user can be both a student and a faculty (dual role — separate rows in each domain).
- Student profile is created on first SSO login (email domain: `*@itmlyceumalabang.onmicrosoft.com`).

---

## 9. Lifecycle

Typical lifecycle:

```
Created (on first SSO login)
  ↓
Active
  ↓
Inactive (transferred, graduated, withdrawn)
  ↓
Archived
```

---

## 10. Domain Events

```
StudentCreated
StudentActivated
StudentDeactivated
StudentCourseChanged
```

---

## 11. Public Contracts

The Student Domain should expose stable contracts for:

- retrieving students
- validating student identity (student_number)
- checking student status
- determining student course affiliation
- publishing student events

---

## 12. Anti-Patterns

### Enrollment Ownership

```
Student
  manages own enrollment
```

Enrollment management belongs to the Enrollment Domain.

### Evaluation Ownership

```
Student
  computes evaluation results
```

Evaluation computation belongs to the Evaluation Business Context.

---

## 13. Guiding Principle

The Student Domain is the canonical source of student profile information.

It defines:

- who is a student
- how students are identified (student_number)
- what program a student is in

It does not determine:

- what subjects a student is enrolled in
- what sections a student attends
- how students are evaluated
- what consultations a student has

Those responsibilities belong to other Domains or Business Contexts.
