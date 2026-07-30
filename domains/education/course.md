# domains/education/course.md

# Course Domain
## Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Industry Pack:** Education
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Course Domain defines the canonical representation of academic programs within the Education Domain Pack.

It owns program identity, classification, department affiliation, and program lifecycle.

The Course Domain answers:

> **"What academic program does this belong to?"**

It does not determine enrollment, grading, scheduling, or certificate generation.

---

# 2. Responsibilities

The Course Domain is responsible for:

- program identity
- program classification
- department affiliation
- program attributes
- program lifecycle
- program validation
- program events

---

# 3. What the Course Domain Owns

Examples include:

- Course (Program)
- Course Code
- Course Name
- Department
- Department Code
- Dean Assignment
- Course Status

These concepts belong exclusively to the Course Domain.

---

# 4. What the Course Domain Does NOT Own

The Course Domain does not own:

- Students
- Faculty
- Subjects
- Sections
- Enrollments
- Semesters
- Consultations
- Evaluations
- Certificates

Those belong to Platform Kernels, other Domains, or Business Contexts.

---

# 5. Ownership

The Course Domain owns:

- entities
- value objects
- validation
- classification rules
- lifecycle rules
- domain events
- public contracts

Business Contexts reference courses but never redefine course identity or structure.

---

# 6. Core Concepts

The primary aggregate is:

```
Course
```

Supporting concepts include:

```
Department
Department Code
Course Code
Course Name
Dean Assignment
Course Status
```

---

# 7. Relationships

The Course Domain may reference Platform Kernels.

Examples:

```
Course

↓

Organization (department)
```

A course may be associated with:

- Department
- Dean
- Faculty members
- Students

The Course Domain does not own these relationships.

The Course Domain may also be referenced by:

- Subject
- Section
- Enrollment
- Consultation
- Evaluation
- Certificate

---

# 8. Business Rules

Examples include:

- Every course has a unique course code.
- Every course belongs to exactly one department.
- A course may have one dean assigned.
- Course codes are immutable after creation.
- Course names may change over time.
- A course may exist before any students are enrolled.
- A course may exist without active subjects.

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

Suspended

↓

Archived
```

Business Contexts determine operational status.

The Course Domain defines only the program lifecycle.

---

# 10. Domain Events

Examples include:

```
CourseCreated

CourseUpdated

CourseActivated

CourseSuspended

CourseArchived
```

---

# 11. Public Contracts

The Course Domain should expose stable contracts for:

- retrieving courses
- validating course identity
- retrieving course details
- determining course department affiliation
- publishing course events

---

# 12. Consumers

Expected consumers include:

- Consultation
- Evaluation
- Certificate
- Reporting

The Course Domain remains unaware of these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## Student Ownership

```
Course

manages Student enrollment
```

Student enrollment belongs to the Enrollment Domain.

---

## Consultation Ownership

```

Course

creates Consultations
```

Consultation creation belongs to the Consultation Business Context.

---

## Evaluation Ownership

```

Course

computes Evaluation results
```

Evaluation computation belongs to the Evaluation Business Context.

---

# 14. Future Evolution

The Course Domain may evolve to support:

- curriculum management
- credit hour definitions
- prerequisite chains
- accreditation metadata
- program outcomes
- industry partnerships

Future additions should continue to represent program knowledge rather than operational workflows.

---

# 15. Guiding Principle

The Course Domain is the canonical source of academic program information.

It defines:

- what programs exist
- how programs are identified
- how programs are classified
- which department owns a program

It does not determine:

- who enrolls in a program
- what subjects are taught
- how students are evaluated
- what certificates are issued

Those responsibilities belong to Platform Kernels, other Domains, or Business Contexts.
