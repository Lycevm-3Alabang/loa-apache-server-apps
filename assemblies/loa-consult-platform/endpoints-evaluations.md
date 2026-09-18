# LOA Consult Platform — Evaluations Module
## Product Assembly Component Specification

**Version:** 0.1
**Status:** Draft
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Covers `api-endpoints.md` §5.6–§5.9. Contracts read from handlers; computation helpers (`computeCategoryAverages`, `computeGeneralRating`, `mapCategoryAveragesToColumns`, `findHighestLowestRubrics`, `computeSentimentScore`, `getRemark`, `groupSnapshotRows`) port as domain services.
> Group gates name Auth tenant groups (§4.0).

---

# 1. Lifecycle & Masking Rules

- Evaluations: DRAFT → SUBMITTED (one-way via submit). Disabled/invalid evaluations exist alongside (`isDisabled`/`isInvalid` markers + disabled set, §6).
- **404-masking:** non-owners, wrong statuses, and disabled evaluations return 404 "Not found" (never 403) on student-facing reads — information-hiding rule, preserved.
- **Lazy compute:** result reads recompute (`computeAll(periodId)`) when stored rows lack category data, then re-read; empty after recompute → empty-shape response (never error).
- **Legacy query aliases preserved:** `semesterId` ≡ `evaluationPeriodId`, `periodId` ≡ `evaluationPeriodId` (read both, prefer the period name).
- **Active period required** for student flows (pending, bootstrap, dispute, create) — 400 "No active evaluation period" otherwise.
- **Remarks scale** (shared): ≥4.5 Outstanding, ≥3.5 Very Satisfactory, ≥2.5 Satisfactory, ≥1.5 Unsatisfactory, else Poor; null-safe.

# 2. Student Evaluations

## `GET /api/v1/evaluations` — read, STUDENT holders
Active period required. Own evaluations enriched (evaluatee name, subject code/name). `{evaluations}`.

## `POST /api/v1/evaluations` — write, STUDENT holders
- With `{id}`: owner check → returns enriched evaluation (200). Non-owner → 403.
- Without: active period (param or current) → enrollment check (`findExisting`, 403) unless `source === "unenrolled"` → get-or-create (UNIQUE period+evaluator+facultySubject) → enriched `{evaluation}` (200, not 201 — preserved).

## `GET /api/v1/evaluations/{id}` — read, owner-masked
Owner + status DRAFT|SUBMITTED + not disabled, else 404. Enriched (evaluatee/subject/section names). Optional `?include=ratings,comments,rubric` (ratings via service; comment single; rubric = period snapshot through `groupSnapshotRows`).

## `GET /api/v1/evaluations/{id}/ratings` — read, owner-masked 404
`{ratings}`.

## `PUT /api/v1/evaluations/{id}/ratings` — write, STUDENT holders + owner-masked 404
Body `{ratings}` → `saveRatings` (upsert per item). `{success:true}`.

## `GET /api/v1/evaluations/{id}/comments` — read, owner-masked 404
`{comment}` (single, may be null).

## `POST /api/v1/evaluations/{id}/comments` — write, STUDENT holders + owner-masked 404
Body `{comment}` → created (201). Sentiment analysis fires **fire-and-forget** (errors swallowed) — placeholder pipeline; Laravel keeps the async-after-write shape (queue job, best-effort).

## `POST /api/v1/evaluations/{id}/submit` — write, STUDENT holders + owner-masked 404
`submitEvaluation` (DRAFT→SUBMITTED) → `{evaluation}`.

## `GET /api/v1/evaluations/pending` — read, STUDENT holders
Active period required. Pending items enriched (faculty name/email, subject code/name). Empty → `{pending: []}`.

## `GET /api/v1/student/evaluations/bootstrap` — write, STUDENT holders
Read-only aggregate (level `write` per catalog — conservative, preserved): `{periods, activePeriodId, activePeriodName, pending (enriched), evaluations (enriched), rubric (snapshot rows|null)}`.

## `POST /api/v1/evaluations/dispute` — write, STUDENT holders
Body `{facultySubjectId, evaluateeId, evaluateeName?, subjectName?}`. Active period + enrollment check (403). Existing evaluation for the triple → invalidate with remarks; else create (`source: "dispute"`) then invalidate by evaluator+period. Always audits `EVALUATION_DISPUTE` (full context JSON). Behind `EMAIL_FEATURE_FLAG`-equivalent, mails ADMIN-group holders via Laravel mail (Gmail-direct code does not port; template + silent per-recipient failure preserved). Returns `{success:true}`.

## `GET /api/v1/evaluation-comments` — read, ADMIN-or-DEAN holders
Query `evaluationPeriodId?` (=`semesterId` alias), `sentimentLabel?`. `{comments}` (filtered list w/ evaluation linkage).

# 3. Evaluation Periods

## `GET /api/v1/evaluation-periods` — read, any session
Query `semesterId?`. Each period with `evaluationCount`. `{periods}`.

## `POST /api/v1/evaluation-periods` — admin, ADMIN holders
Body passed to `createEvaluationPeriod` (validation inside service — verify rules at build). 201 `{period}`.

## `GET /api/v1/evaluation-periods/{id}` — read, any session
`{period}` / 404.

## `PUT /api/v1/evaluation-periods/{id}` — admin, ADMIN holders
Full body to `updateEvaluationPeriod` (service rules — verify at build). `{period}`.

## `DELETE /api/v1/evaluation-periods/{id}` — admin, ADMIN holders
`{success:true}`. (Cascade behavior per FKs; data-loss guard is UI-side — preserved.)

## `POST /api/v1/evaluation-periods/{id}/activate` — admin (+ DEAN grant to preserve §4.4 intent), ADMIN holders in code
`activateEvaluationPeriod` (exclusivity handled in service — verify at build). `{period}`. 500 carries `detail`.

## `POST /api/v1/evaluation-periods/{id}/reset` — admin, ADMIN holders
`resetEvaluationPeriod` (marks evaluations invalid — `isInvalid` lineage). `{success:true}`.

## `GET /api/v1/evaluation-periods/{id}/rubric` — read, any session
Period snapshot `{rubric}` (raw snapshot rows).

## `POST /api/v1/evaluation-periods/{id}/rubric/copy` — read, any session (misnomer preserved)
Returns the same snapshot fetch (no duplication occurs in code). Rated `read` by behavior. Module build must NOT invent copy semantics.

## `POST /api/v1/evaluation-periods/{id}/rubrics/items` — admin, ADMIN holders
Period `{id}` ignored by handler (creates by `categoryId`) — preserved quirk, do not "fix" without frontend sign-off. Body `{categoryId, text, displayOrder, weight?=1}`. 201 `{item}`.

## `PATCH /api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` — admin, ADMIN holders
Full-body `updateItem`. `{item}`.

## `DELETE /api/v1/evaluation-periods/{id}/rubrics/items/{itemId}` — admin, ADMIN holders
`{success:true}`.

# 4. Rubric Groups (standalone editor)

Seed/lock rule (`assertEditable`, all mutating paths): missing → 404; `seed` group → 409 "original… duplicate it"; locked (assigned to active period) → 409 "locked… duplicate it".

## `GET /api/v1/rubric-groups` — read, any session → `{groups}`
## `POST /api/v1/rubric-groups` — admin, ADMIN holders → `{name, description?}` → 201 `{group}`
## `GET /api/v1/rubric-groups/{id}` — read, any session → `{group}` / 404
## `PATCH /api/v1/rubric-groups/{id}` — admin, ADMIN holders + editable → `{name?, description?}` → `{group}`
## `DELETE /api/v1/rubric-groups/{id}` — admin, ADMIN holders + editable → `{success:true}`
## `POST /api/v1/rubric-groups/{id}/items` — admin, ADMIN holders + editable → `{categoryId, text, displayOrder, weight?=1}` → 201 `{item}`
## `PATCH /api/v1/rubric-groups/{id}/items/{itemId}` — admin, ADMIN holders + editable → full-body update → `{item}`
## `DELETE /api/v1/rubric-groups/{id}/items/{itemId}` — admin, ADMIN holders + editable → `{success:true}`
## `POST /api/v1/rubric-groups/{id}/duplicate` — admin, ADMIN holders → body `{name}` → locked check (409) — seed duplication allowed (that is its purpose) → 201 `{group}`
## `GET /api/v1/rubric-groups/{id}/snapshot` — read, any session → `{snapshot}` (raw rows; the `[id]/rubric` twin shapes it via `groupSnapshotRows`)
## `POST /api/v1/rubric-groups/{id}/categories` — admin, ADMIN holders + editable → `{name, displayOrder?}` → 201 `{category}`
## `DELETE /api/v1/rubric-groups/{id}/categories` — admin, ADMIN holders + editable → body `{categoryId}` (400 if missing) → `{success:true}`

# 5. Results Reads (aggregation family)

Shared computation: submitted evaluations → ratings/comments fan-out → category averages → general rating → remarks → sentiment averaging (2-decimal rounding throughout) → remark. Lazy `computeAll` when stored rows lack category data. `evaluationPeriodId` (plus `semesterId`/`periodId` aliases) required everywhere (400 otherwise).

- **Admin** (`requireAdmin` ≈ ADMIN holders): base aggregates `{departments[]}` (with `__unknown__`→"Unassigned" bucket); `departments/{id}` (faculty-user scope, `{department, subjects}` family); `departments/{id}/faculty/{fid}` (404s per entity, `{faculty, subjects/c Malformed}` family); `departments/{id}/groups/{fsid}` (mapping scope + full comments). Nested drill-downs follow the base computation with path-param scoping — exact response keys verified at build per endpoint.
- **Dean** (DEAN holders; own-department scope via `findByDeanId`, empty → empty shape): base `{departments:[own]}`; `department` → `{departmentId|null}`; `departments/{id}`, `/faculty/{fid}`, `/groups/{fsid}` drill-downs mirror admin shapes scoped to own department (verify keys at build); `details` shared breakdown (DEAN/ADMIN any faculty; others own-only; DEAN without department → `{students:[]}`; students anonymized `S1..Sn`).
- **Faculty** (any session + visibility gate): base computes own result `{results:[...], facultyNames}` — 403 unless `is_results_visible`; `subjects` groups own evaluations per mapping (`{subjects}`); `subjects/{id}` single-group variant (same pattern, verify keys at build).

# 6. Results Mutations + Disabled Set

## `POST /api/v1/admin/evaluation-results/invalidate` — admin, ADMIN holders
Body `{evaluationPeriodId|periodId, facultyId?|facultySubjectId?}` (one target required). `bulkDisableByPeriod` + `computeAll` + audit `invalidate_evaluations` (fail-open audit). `{success:true}`.

## `POST /api/v1/admin/evaluation-results/visibility` — admin, ADMIN holders
Body `{evaluationPeriodId|semesterId, facultyIds[] (non-empty), visible}`. `setVisibility`. `{success:true}`. (DEAN access = Auth grant, §7 #10.)

## `GET /api/v1/admin/evaluations/disabled` — read, ADMIN holders → `{evaluations}` (disabled list)
## `DELETE /api/v1/admin/evaluations/disabled` — admin, ADMIN holders → body `{all:true}` (all) or `{ids[]}` (subset) → `{success:true}`
## `POST /api/v1/admin/evaluations/disabled/restore` — admin, ADMIN holders → body `{ids[]}` (non-empty) → `restoreByIds` + audit `restore_evaluations` → `{success:true}`
## `GET /api/v1/admin/evaluations/{evaluationId}/details` — read, ADMIN holders → full assembly `{evaluationId, submittedAt, evaluatorName, categories:[{categoryName, items:[{text, rating}]}], comment, sentimentLabel, sentimentScore, isDisabled}`
## `POST /api/v1/admin/evaluations/{evaluationId}/invalidate` — admin, ADMIN holders → body `{evaluationPeriodId, reason?}` → `invalidateById` + `computeAll` + audit `invalidate_evaluation` → `{success:true}`

---

## Document Control

- **Status:** Draft v0.1
- **Created:** 2026-09-18
- **Source:** 24 evaluation route handlers read verbatim; 8 nested drill-downs pattern-applied (gates + scope + shape-family verified, exact keys flagged per endpoint)
- **Corrects parent spec:** periods POST/PUT/activate + period-items POST/PATCH + rubric-groups POST/PATCH/items/duplicate `write`→`admin`; rubric-copy `write`→`read` (fetch misnomer); evaluation-comments gate ADMIN-or-DEAN holders
- **Verify at build:** actor-ownership inside accept/decline/complete/cancel; `createEvaluationPeriod`/`updateEvaluationPeriod`/`activateEvaluationPeriod` service rules; `createEnrollment`-adjacent `getOrCreateEvaluation` edge cases; nested drill-down exact keys; `listByRole("ADMIN")` → tenant-group query
