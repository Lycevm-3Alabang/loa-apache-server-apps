# Rubric
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Evaluation
**Aggregate:** Rubric
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Rubric aggregate defines the structure used to evaluate faculty performance.

It owns rubric groups, rubric categories, rubric items, and rating scales.

The Rubric aggregate answers:

> **"What criteria are used to evaluate faculty?"**

It does not own evaluations, results, users, or certificates.

---

# 2. Responsibilities

The Rubric aggregate is responsible for:

- rubric group management
- rubric category management
- rubric item management
- rating scale management
- rubric structure validation
- rubric events

---

# 3. What the Rubric Aggregate Owns

Examples include:

- Rubric Group
- Rubric Category
- Rubric Item
- Rating Scale
- Category Name
- Category Display Order
- Item Text
- Item Display Order
- Item Weight
- Rating Value
- Rating Display Order

The Rubric aggregate owns these concepts completely.

---

# 4. What the Rubric Aggregate Does NOT Own

The Rubric aggregate does not own:

- Users
- Evaluations
- Evaluation Ratings
- Evaluation Comments
- Evaluation Results
- Semesters
- Certificates

These belong to Platform Kernels, Education Domains, or other Business Contexts.

---

# 5. Ownership

The Rubric aggregate owns:

- aggregate state
- business invariants
- structure
- validation
- domain events

The Rubric aggregate is the consistency boundary for rubric management.

---

# 6. Aggregate Structure

```
Rubric Group
│
├── Rubric Category
│   ├── Rubric Item
│   │   └── Weight
│   ├── Rubric Item
│   │   └── Weight
│   └── Rubric Item
│       └── Weight
├── Rubric Category
│   ├── Rubric Item
│   │   └── Weight
│   └── Rubric Item
│       └── Weight
└── Rating Scale
    ├── Rating (1)
    ├── Rating (2)
    ├── Rating (3)
    ├── Rating (4)
    └── Rating (5)
```

The default rubric includes 8 categories with 3-6 items each.

---

# 7. Default Rubric Categories

The system seeds the following categories:

```
1. Professional Manner
2. Communication with Students
3. Student Engagement
4. Learning Materials
5. Time Management
6. Experiential Learning
7. Respect for Uniqueness
8. Assessment and Feedback
```

Each category contains weighted rubric items.

---

# 8. Business Rules

Examples include:

- Every rubric group has a name and optional description.
- Every rubric category belongs to exactly one rubric group.
- Every rubric item belongs to exactly one rubric category.
- Every rubric item has a weight.
- Rating scales are per semester.
- Rating values are positive integers.
- Categories have a display order.
- Items have a display order within their category.
- Seed rubrics are marked as seed = true.

---

# 9. Lifecycle

Typical lifecycle:

```
Seed (initial load)

↓

Active

↓

Archived (new rubric version)
```

---

# 10. Domain Events

Examples include:

```
RubricGroupCreated

RubricCategoryAdded

RubricItemAdded

RatingScaleDefined
```

---

# 11. Public Contracts

The Rubric aggregate should expose stable contracts for:

- retrieving rubric groups
- retrieving rubric categories
- retrieving rubric items
- retrieving rating scales
- validating rubric structure

---

# 12. Anti-Patterns

The following are architectural violations.

## Evaluation Ownership

```
Rubric

creates Evaluations
```

Evaluation creation belongs to the Evaluation aggregate.

---

## Result Ownership

```

Rubric

computes Evaluation results
```

Result computation belongs to the Evaluation Result aggregate.

---

# 13. Guiding Principle

The Rubric aggregate is the canonical representation of evaluation criteria.

It defines:

- what categories are evaluated
- what items are rated
- how ratings are weighted
- what rating scales exist

It does not define:

- who evaluates whom
- what ratings are given
- how results are computed
- what certificates are issued

Those responsibilities remain with other aggregates, Domains, and Business Contexts.
