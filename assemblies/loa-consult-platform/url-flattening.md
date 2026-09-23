# LOA Consult Platform — Flat URL Scheme (no role prefixes)

| Field | Value |
|-------|-------|
| ID | CONSULT-URL-001 |
| Title | Flat URL scheme |
| Status | Final v1.1 (user-approved 2026-09-23; F2 green) |
| Owner | Consult Platform assembly |
| Version | 1.1 Final |
| Scope | Replace role-prefixed paths (`admin/*`, `dean/*`, `faculty/*`, `student/*`) with flat resources under `/api/v1/`; access decided solely by per-group grants (`aces-*` × read/write/deny/admin); single scoped results endpoint; in-place rename; catalog 118 → 104 gated (results 20 → 15; Auth-owned users writes + users/reference dropped; gate-blocked audit-logs retained pending) |
| Non-goals | Changing levels semantics (owned by Auth grants specs); auth flow; shapes/migrations; frontend cutover (Phase E) |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

Roles baked into URLs (`admin/`, `dean/`, `faculty/`) contradict the group-grant model (user decision): `/users` belongs to Auth; `/departments` gets per-group grants. User decisions 2026-09-23: single scoped results endpoint; deny per cert/auth precedent; rename in place under `/api/v1`; flatten ALL prefixes including `admin/`. Pre-cutover window applies (frontend untouched until Phase E; legacy Next.js `/api/*` paths unaffected).

## Constraints

- **CON-1** — Access MUST be decided solely by grants (`aces-admin`/`aces-dean`/`aces-faculty`/`aces-user` × read/write/deny/admin). Paths MUST NOT encode audience.
- **CON-2** — Deny follows Auth/cert precedent: ordinals deny=-1/read=1/write=2/admin=3, ALLOW iff granted ≥ required; group priority wins, same-priority tier with 2+ granting groups → deny wins; user override replaces group result; JWT `permissions` never contains `deny`; no grant (or no catalog entry) → closed-by-default 403. Explicit `deny` is for exceptions; normal scoping uses absence. Import path deletes rows on `deny` instead of storing (Auth DEFERRED unify — note when authoring matrices).
- **CON-3** — Identity paths (`admin/users*` writes, `import/users/reference`) MUST NOT exist in consult; they belong to Auth (`tenant-member*`). Only link-derived reads stay: `/users/primary`, `/users/attendees`, `/users/{id}/related-data`.
- **CON-4** — Rename happens in place under `/api/v1/` (no version bump). Methods + required levels MUST be preserved per row unless the mapping table says otherwise.
- **CON-5** — Results collapse to ONE scoped surface: server-side scoping by JWT groups. One path serves admin/dean/faculty audiences; visibility rules (e.g., faculty sees own subjects) MUST be enforced in code, not paths.
- **CON-6** — `audit-logs` paths stay gated by the data-model implementability rule (no table yet → unimplemented, 404 documents the gate).

## Goal

### Decisions

- **DEC-1** — Flat resources: `admin/departments` → `/departments`, `admin/department-courses` → `/department-courses`, `admin/subjects` → `/subjects`, `admin/sections` → `/sections`, `admin/faculty-subjects` → `/faculty-subjects`, `admin/student-enrollments` → `/student-enrollments`, `admin/data/*` → `/data/*`, `admin/audit-logs` → `/audit-logs`.
- **DEC-2** — Results: 9 dean/faculty variant paths collapse into the admin-shaped paths renamed flat (`/evaluation-results`, `/evaluation-results/departments/{id}`, `/faculty/{id}`, `/groups/{id}`, `/details`); disabled-set + details/invalidate move under `/evaluations/*`.
- **DEC-3** — `student/evaluations/bootstrap` → `/evaluations/bootstrap`. `evaluation-comments` keeps its name (no churn).
- **DEC-4** — Migration: rename routes + `consult-endpoints.php` + catalog JSON + tests in one slice; re-version `api-endpoints.md` → v2.0 (breaking), modules → v1.1, refresh readiness/admin-import pointers. Frontend untouched (calls legacy Next.js paths until cutover).

### Acceptance — Objective (machine-checkable)

- **ACC-1** — No route path under `/api/v1/` contains `admin/`, `dean/`, `faculty/`, or `student/` segments.
- **ACC-2** — Catalog count = 104 gated + 5 public (public trio/health/count-active untouched; results 20 → 15; Auth-owned users writes + users/reference dropped; gate-blocked audit-logs retained pending); every served catalog entry has a matching route and vice versa.
- **ACC-3** — Old prefixed paths → 404 (no silent redirects); `RouteGatingTest`-style coverage asserts a sample of old paths 404 + new paths gated 401-tokenless/403-level.
- **ACC-4** — Results scoping: faculty JWT sees own subjects only; dean sees department; admin sees all (group-driven, path-identical).
- **ACC-5** — Full suite green pasted by USER.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer confirms the grant matrix reads naturally per resource (no audience guessing from paths).

## Deliverables

- **D-1** — Mapping table below implemented in `routes/api.php` + `config/consult-endpoints.php` + regenerated `consult-endpoints-catalog.json`.
- **D-2** — Re-versioned Finals (`api-endpoints.md` v2.0, modules v1.1) + pointer refresh (readiness, admin-import, test-suite).
- **D-3** — Updated Feature tests (paths) + results-scoping tests.

## Mapping (old → new, methods/levels unchanged unless noted)

| Old path | New path | Note |
|----------|----------|------|
| `/api/v1/admin/departments`, `/{id}` | `/api/v1/departments`, `/{id}` | — |
| `/api/v1/admin/department-courses`, `/{id}` | `/api/v1/department-courses`, `/{id}` | — |
| `/api/v1/admin/subjects`, `/{id}` | `/api/v1/subjects`, `/{id}` | — |
| `/api/v1/admin/sections`, `/{id}`, `/fix-names` | `/api/v1/sections`, `/{id}`, `/fix-names` | — |
| `/api/v1/admin/faculty-subjects`, `/reassign` | `/api/v1/faculty-subjects`, `/reassign` | — |
| `/api/v1/admin/student-enrollments`, `/{id}` | `/api/v1/student-enrollments`, `/{id}` | — |
| `/api/v1/admin/users`, `/deleted`, `/bulk-soft-delete`, `/{id}`, `/{id}/soft-delete`, `/{id}/restore` | — (dropped) | Auth-owned (CON-3) |
| `/api/v1/admin/users/{id}/related-data` | `/api/v1/users/{id}/related-data` | already implemented flat |
| `/api/v1/users/primary`, `/users/attendees` | unchanged | already flat |
| `/api/v1/admin/evaluation-results` | `/api/v1/evaluation-results` | group-scoped index (CON-5) |
| `/api/v1/admin/evaluation-results/departments/{id}` | `/api/v1/evaluation-results/departments/{id}` | dean own-only guard |
| `/api/v1/admin/evaluation-results/faculty/{id}` | `/api/v1/evaluation-results/faculty/{id}` | dean own-dept / faculty self-only guards |
| `/api/v1/admin/evaluation-results/groups/{id}` | `/api/v1/evaluation-results/groups/{id}` | dean own-dept / faculty own-mapping guards |
| `/api/v1/admin/evaluation-results/invalidate`, `/visibility` | `/api/v1/evaluation-results/invalidate`, `/visibility` | — |
| `/api/v1/dean/evaluation-results`, `/department`, `/details`, `/departments/{id}`, `.../faculty/{id}`, `.../groups/{id}` | — (collapsed) | served by scoped surface; `/department` + `/details` kept as `/evaluation-results/department`, `/details` |
| `/api/v1/faculty/evaluation-results`, `/subjects`, `/subjects/{id}` | `/api/v1/evaluation-results` (scoped), `/evaluation-results/subjects`, `/subjects/{id}` | self views with visibility gate |
| `/api/v1/admin/evaluations/disabled` (GET/DELETE), `/disabled/restore` | `/api/v1/evaluations/disabled` (GET/DELETE), `/disabled/restore` | — |
| `/api/v1/admin/evaluations/{id}/details`, `/{id}/invalidate` | `/api/v1/evaluations/{id}/details`, `/{id}/invalidate` | — |
| `/api/v1/admin/audit-logs` (GET/DELETE) | `/api/v1/audit-logs` (GET/DELETE) | still gated by table absence (CON-6) |
| `/api/v1/admin/data/delete-students`, `/export-consultations`, `/reset-db` | `/api/v1/data/delete-students`, `/export-consultations`, `/reset-db` | already implemented under `/admin/data` — rename |
| `/api/v1/data/evaluation-mappings` | unchanged | already flat |
| `/api/v1/student/evaluations/bootstrap` (GET, write) | `/api/v1/evaluations/bootstrap` (method preserved) | — |
| `/api/v1/import/users/reference` | — (dropped) | Auth-owned (CON-3) |
| all other catalog paths | unchanged | already flat |

## Glossary

| Term | Meaning |
|------|---------|
| Flat resource | Path naming the resource only (`/departments`); audience decided by grants |
| Scoped surface | One path serving multiple audiences via server-side JWT-group scoping |
| Closed-by-default | No catalog entry or no grant → 403 |

## References

- `api-endpoints.md` Final v1.0 §5 (pre-flatten source; to be superseded by v2.0)
- `tenant-group-endpoint-grants.md` (Auth: ordinals, priority-wins, deny semantics) + `tenant-endpoint-catalog.md` (closed-by-default)
- Cert `EndpointPolicyMiddleware.php` (deny handling precedent) + `EndpointPolicyMiddlewareTest::test_rejects_deny_level`
- `consult-readiness.md` Final v1.4 (aces-* groups) + `endpoints-admin-import.md` Final v1.0 (ownership)

---

## Document Control

- **Status:** Final v1.1 (F2 green 2026-09-23)
- **Created:** 2026-09-23 from role-prefix gap + scheme answers; reviewed; promoted Final v1.0 2026-09-23; refined v1.1 during F2 (actual flat set 113+5); re-promoted Final v1.1 2026-09-23
- **Next:** re-version affected Finals per DEC-4 (api-endpoints v2.0, modules v1.1) + pointer refresh
