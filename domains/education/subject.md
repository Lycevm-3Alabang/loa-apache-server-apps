# domains/education/subject.md

# Subject Domain
## Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Subject Domain defines the canonical representation of individual courses taught within a program.

It owns subject identity, code, name, and classification.

The Subject Domain answers:

> **"What is being taught?"**

It does not determine who teaches it, who enrolls, or how it is evaluated.

---

# 2. Responsibilities

The Subject Domain is responsible for:

- subject identity
- subject code
- subject name
- subject classification
- subject validation
- subject events

---

# 3. What the Subject Domain Owns

Examples include:

- Subject
- Subject Code
- Subject Name
- Subject Status

These concepts belong exclusively to the Subject Domain.

---

# 4. What the Subject Domain Does NOT Own

The Subject Domain does not own:

- Faculty assignments
- Section assignments
- Students
- Enrollments
- Consultations
- Evaluations
- Grades
- Semesters

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Subject Domain owns:

- entities
- value objects
- validation
- lifecycle rules
- domain events
- public contracts

Business Contexts reference subjects but never redefine subject identity.

---

# 6. Core Concepts

The primary aggregate is:

```
Subject
```

Supporting concepts include:

```
Subject Code
Subject Name
Subject Status
```

---

# 7. Relationships

The Subject Domain may reference:

```
Course (program)

↓

Subject
```

A subject may be associated with:

- Faculty members (via FacultySubject)
- Sections
- Enrollments
- Consultations
- Evaluations

The Subject Domain does not own these relationships.

---

# 8. Business Rules

Examples include:

- Every subject has a unique subject code.
- Subject codes are immutable.
- Subject names may change.
- A subject may exist without faculty assignment.
- A subject may exist without enrollments.

---

# 9. Lifecycle

Typical lifecycle:

```
Proposed

↓

Approved

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
SubjectCreated

SubjectUpdated

SubjectActivated

SubjectArchived
```

---

# 11. Public Contracts

The Subject Domain should expose stable contracts for:

- retrieving subjects
- validating subject identity
- retrieving subject details
- publishing subject events

---

# 12. Anti-Patterns

The following are architectural violations.

## Faculty Ownership

```
Subject

assigns Faculty
```

Faculty assignment belongs to the Consultation Business Context.

---

## Enrollment Ownership

```

Subject

manages Student enrollment
```

Enrollment belongs to the Enrollment Domain.

---

# 13. Guiding Principle

The Subject Domain is the canonical source of subject information.

It defines:

- what subjects exist
- how subjects are identified
- how subjects are classified

It does not determine:

- who teaches a subject
- who enrolls in a subject
- how a subject is evaluated

Those responsibilities belong to other Domains or Business Contexts.
