# Evaluation Business Context
## Business Context Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Capability:** Evaluation
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Evaluation Business Context manages the complete faculty evaluation lifecycle at Lyceum of Alabang.

It owns evaluation periods, rubric management, student evaluations, evaluation result computation, and sentiment analysis.

The Evaluation Business Context answers:

> **"How do students evaluate faculty performance?"**

It does not own users, authentication, appointments, availability, academic infrastructure, or certificates.

---

# 2. Responsibilities

The Evaluation Business Context is responsible for:

- evaluation period management (semesters)
- rubric management (groups, categories, items)
- rating scale management
- student evaluation creation
- rating submission
- comment submission with sentiment analysis
- evaluation submission workflow
- evaluation result computation
- result visibility controls
- evaluation events

---

# 3. What the Evaluation Business Context Owns

Examples include:

- Evaluation
- Evaluation Rating
- Evaluation Comment
- Evaluation Result
- Rubric Group
- Rubric Category
- Rubric Item
- Rating Scale
- Evaluation Status
- Sentiment Score
- Sentiment Label

These concepts belong exclusively to the Evaluation Business Context.

---

# 4. What the Evaluation Business Context Does NOT Own

The Evaluation Business Context does not own:

- Users
- Authentication
- JWT Tokens
- Departments
- Courses
- Subjects
- Sections
- Semesters (reference only)
- Enrollments
- Appointments
- Faculty Availability
- Certificates

Those belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Evaluation Business Context owns:

- business workflows
- aggregates
- commands
- business policies
- validation
- transaction boundaries
- lifecycle rules
- domain events
- public contracts

It references shared concepts without redefining them.

---

# 6. Core Aggregates

Primary aggregates include:

```
Evaluation
```

Supporting aggregates include:

```
Evaluation Rating
Evaluation Comment
Evaluation Result
Rubric Group
Rubric Category
Rubric Item
Rating Scale
```

---

# 7. Relationships

The Evaluation Business Context references:

```
Identity (evaluator, evaluatee via Auth API)
Semester (education domain)
Subject (education domain)
Section (education domain)
Enrollment (education domain)
```

Evaluation composes these concepts to manage faculty evaluations.

Ownership remains with their respective Platform Kernels and Education Domains.

---

# 8. Business Rules

Examples include:

- Every evaluation belongs to a semester.
- Every evaluation has an evaluator (student) and evaluatee (faculty).
- Each student evaluates each faculty member once per semester.
- Evaluations follow a status workflow: DRAFT → SUBMITTED.
- Ratings are on a 1-5 scale.
- Each rubric item is rated independently.
- Comments may include sentiment analysis.
- Evaluation results are computed per faculty per semester.
- Results can be visible or hidden from faculty.
- Submitted evaluations cannot be modified.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Submitted

↓

Results Computed

        │
        ├── Results Visible
        └── Results Hidden
```

---

# 10. Domain Events

Examples include:

```
EvaluationCreated

EvaluationRatingSaved

EvaluationCommentSaved

EvaluationSubmitted

EvaluationResultsComputed

EvaluationResultsVisibilityChanged
```

---

# 11. Public Contracts

The Evaluation Business Context should expose stable contracts for:

- creating evaluations
- saving ratings
- saving comments
- submitting evaluations
- listing pending evaluations
- computing evaluation results
- changing result visibility
- retrieving evaluation results

---

# 12. Consumers

Expected consumers include:

- Consult Platform (API layer)
- Reporting (evaluation analytics)
- Auth Platform (user lookup)

The Evaluation Business Context remains unaware of implementation details within these consumers.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Evaluation

stores User credentials
```

User credentials belong to the Identity Kernel (Auth Platform).

---

## Semester Ownership

```

Evaluation

manages Semester definitions
```

Semester definitions belong to the Semester Domain.

---

## Rubric Ownership by Admin

```
Evaluation

allows arbitrary rubric creation
```

Rubrics follow predefined categories and seed data. Free-form rubric creation is an anti-pattern.

---

## Certificate Ownership

```

Evaluation

issues Certificates for completed evaluations
```

Certificate issuance belongs to the Certificate Business Context.

---

# 14. Future Evolution

The Evaluation Business Context may evolve to support:

- multiple evaluation forms per period
- anonymous evaluation submission
- evaluation comparison across semesters
- faculty self-evaluation
- peer evaluation
- evaluation appeal workflow
- evaluation analytics dashboard
- automated evaluation reminders
- evaluation export (PDF, CSV)

Future additions should continue to represent evaluation workflows.

---

# 15. Guiding Principle

The Evaluation Business Context is the canonical owner of faculty evaluation.

It defines:

- how evaluations are structured
- how ratings are collected
- how comments are analyzed
- how results are computed

It does not define:

- who the users are
- what semesters exist
- what subjects are taught
- what certificates are issued

Those responsibilities belong to Platform Kernels, Education Domains, or other Business Contexts.
