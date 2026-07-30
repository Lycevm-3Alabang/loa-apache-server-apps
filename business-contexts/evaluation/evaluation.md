# Evaluation
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Evaluation
**Aggregate:** Evaluation
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Evaluation aggregate represents a student's evaluation of a faculty member for a specific subject within a semester.

It is the central aggregate of the Evaluation Business Context and owns the complete evaluation lifecycle, including ratings, comments, and submission workflow.

The Evaluation aggregate answers:

> **"What did this student think of this faculty member?"**

It does not own users, semesters, rubrics, or certificates.

---

# 2. Responsibilities

The Evaluation aggregate is responsible for:

- evaluation creation
- evaluation lifecycle
- rating collection
- comment collection
- submission workflow
- uniqueness enforcement
- evaluation events

---

# 3. What the Evaluation Aggregate Owns

Examples include:

- Evaluation
- Evaluator ID (student)
- Evaluatee ID (faculty)
- Semester ID
- Status (DRAFT, SUBMITTED)
- Submitted At
- Created At
- Updated At

The Evaluation aggregate owns these concepts completely.

---

# 4. What the Evaluation Aggregate Does NOT Own

The Evaluation aggregate does not own:

- Users
- Semesters
- Rubric Groups
- Rubric Categories
- Rubric Items
- Rating Scales
- Evaluation Results
- Sentiment Analysis
- Certificates

These belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Evaluation aggregate owns:

- aggregate state
- business invariants
- lifecycle
- validation
- domain events

The Evaluation aggregate is the consistency boundary for all evaluation operations.

---

# 6. Aggregate Structure

```
Evaluation
│
├── Evaluation Rating
│   └── Evaluation Rating (one per rubric item)
├── Evaluation Comment
│   └── Evaluation Comment (zero or more)
└── Metadata
```

All child entities exist only within the lifetime of an Evaluation.

---

# 7. Relationships

The Evaluation aggregate references:

```
Identity (evaluator, evaluatee)
Semester
Subject
Section
Enrollment
```

These references provide business context.

Ownership remains with their respective Platform Kernels and Education Domains.

---

# 8. Business Rules

Examples include:

- Every evaluation belongs to a semester.
- Every evaluation has an evaluator and evaluatee.
- Each student evaluates each faculty member once per semester.
- Ratings are on a 1-5 scale per rubric item.
- Comments may include sentiment analysis.
- Submitted evaluations cannot be modified.
- Draft evaluations can be updated.
- Uniqueness is enforced per semester, evaluator, and evaluatee.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Submitted
```

State transitions are governed by Evaluation policies.

---

# 10. Domain Events

Examples include:

```
EvaluationCreated

EvaluationRatingSaved

EvaluationCommentSaved

EvaluationSubmitted
```

---

# 11. Public Contracts

The Evaluation aggregate should expose stable contracts for:

- creating evaluations
- saving ratings
- saving comments
- submitting evaluations
- retrieving evaluation details

---

# 12. Aggregate Invariants

The following invariants must always hold:

- An evaluation always has a semester.
- An evaluation always has an evaluator.
- An evaluation always has an evaluatee.
- Ratings are between 1 and 5.
- Submitted evaluations cannot be modified.
- Each evaluator can only evaluate each evaluatee once per semester.

These invariants are enforced by the aggregate root.

---

# 13. Anti-Patterns

The following are architectural violations.

## User Ownership

```
Evaluation

stores Student name
```

Student identity belongs to the Identity Kernel. Reference by Student ID.

---

## Result Ownership

```

Evaluation

computes final results
```

Result computation belongs to the Evaluation Result aggregate.

---

## Rubric Ownership

```

Evaluation

defines rubric categories
```

Rubric definitions are separate aggregates.

---

# 14. Guiding Principle

The Evaluation aggregate is the canonical representation of a student evaluation.

It owns:

- the evaluation record
- its ratings
- its comments
- its submission state

It references, but never owns:

- users
- semesters
- rubrics
- results
- certificates

Those responsibilities remain with Platform Kernels, Education Domains, and other aggregates.
