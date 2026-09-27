# LOA Consult Platform — Reports Endpoints (Phase E)
## Product Assembly Component Specification

| Field | Value |
|-------|-------|
| ID | CONSULT-REPORTS-001 |
| Title | Consult Platform Reports as Laravel API |
| Status | Final v1.0 (user-approved 2026-09-26; scan-verified; implementation gates open per Rule 0) |
| Owner | Consult Platform assembly |
| Version | 1.0 Final |
| Scope | 7 report families as flat Laravel REST reads (+ sentiment analyze writes) over shared academic actors + Auth claims; single-department vs all-departments scoping; common date/status filters |
| Non-goals | Frontend rewrite itself (cutover executes it); CSV/PDF export bytes (stays in UI components unless Phase E+ scopes it); changing B/C/D shapes; local roles; cross-app DB reads |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

Reports exist today only as Next.js Server Components computing directly against Supabase — there are zero `app/api/reports/**` REST routes (verified 2026-09-26: 112 `route.ts` files, 20 top-level `app/api/*` entries, no `reports/`). Logic lives in `features/reports/**`: 6 read controllers (`backlog`, `coverage`, `demand`, `distribution`, `responsiveness`, `admin-reports`/`reports.service` health) sharing the `{ startDate?, endDate?, status? }` filter shape and `departmentId | null` scope (`null` = all departments, `"All Departments"` merge), plus `sentiment.service` (per-comment analyze + batch of 50 unanalyzed `evaluation_comments`). Pages render at 20 `app/*/reports/**/page.tsx` (admin consultations, dean, faculty, evaluations-sentiment/detail). `api-endpoints.md` Final v2.1 §2.2 defers reports to Phase E. This spec defines the Laravel target surface so TDD can interrogate it without inventing behavior; frontend cutover executes against it per `FRONTEND-INTEGRATION.md`.

## Constraints

- **CON-1** — Every report route MUST sit under `/api/v1/reports/*` (flat, in-place v1 per `url-flattening.md`), behind `['jwt.auth','jwt.endpoint']`, with `jwt.auth` before `jwt.endpoint`.
- **CON-2** — Auth invariant MUST hold: JWT local validation (shared HMAC-SHA256, `type=access`), tenant scoping (`TENANT_SLUG=loa-consultation`, token `tenant.slug ≠ loa-consultation → 403`), groups only from the JWT `groups` claim. No local roles.
- **CON-3** — Responses MUST be bare shapes (`{report}`, `{data}`, `{success:true}`, `{error}` — no envelope), consistent with the assembly until cutover.
- **CON-4** — Reads MUST be level `read`; sentiment analyze/batch writes MUST be level `write`; all-departments scope (`department_id` absent) MUST require level `admin`. Closed-by-default 403s stay JSON, never redirects (frontend `setLockedEndpoint` pattern).
- **CON-5** — Filters MUST be query-only: `start_date?`, `end_date?` (YYYY-MM-DD), `status?` (pending/approved/completed/cancelled/rejected, case-insensitive), `department_id?` (UUID; absent = all-departments, admin only). Unknown department MUST be 404; invalid filter MUST be 422.
- **CON-6** — Aggregation MUST execute in MySQL (`loa_consult`) via service/repository (no per-row N+1); raw lists MUST paginate (`page`, `per_page`, default 50, max 200).
- **CON-7** — Nothing MUST break `GET /api/v1/health → {status:ok, service:loa-consult-platform}`; suites run sequentially only (`loa_consult_test`); agent NEVER runs tests — USER runs from repo root.
- **CON-8** — No behavior without a `CON-*` + `ACC-*` + `D-*`; no code until this spec is promoted to Final.

## Goal

### Decisions

- **DEC-1** — Surface: 7 flat reads `GET /reports/{health,backlog,coverage,demand,distribution,responsiveness,sentiment}` + 2 sentiment writes (`POST /reports/sentiment/analyze`, `POST /reports/sentiment/batch`). Scoping mirrors legacy controllers: `department_id` present = that department; absent = all-departments merge with `departmentName: "All Departments"` (admin only).
- **DEC-2** — Health = dean-department stats shape (`departmentName`, `departmentId`, `stats[]`, `rawAppointments` paginated, `summaries[]`, `departmentFrequency[]`, `facultyFrequency[]`, yearly frequencies) per `reports.service:getDeanDepartmentStats`; admin variant adds `departments[]` summaries + `selectedDepartmentId` per `admin-reports.controller`.
- **DEC-3** — Backlog aging buckets are fixed labels `0 - 3 Days | 4 - 7 Days | 8 - 14 Days | More Than 14 Days` with summary `{totalPending, totalApproved, totalUnresolved, oldestDays, oldestDate, oldestFaculty, oldestStudent}` + `byFaculty[]`.
- **DEC-4** — Coverage returns `{overall:{totalStudents, studentsWithConsultations, studentsWithoutConsultations, coveragePercentage}, byDepartment[], trend[]}` with running-total monthly trend.
- **DEC-5** — Demand returns `{daily, weekly, monthly, departmentName}` merged across departments when unscoped.
- **DEC-6** — Distribution returns `{entries[] (with recalculated departmentShare), departmentTotal, departmentName, totalConsultations, completedConsultations, pendingConsultations}`.
- **DEC-7** — Responsiveness returns `{stats:{averageHours, medianHours, fastestHours, slowestHours, totalResponded}, byFaculty[], distribution[], departmentName}` with weighted-average merge when unscoped.
- **DEC-8** — Sentiment read returns stored `{score, label, analyzedAt}` per comment; `analyze` recomputes one comment (write); `batch` analyzes up to 50 unanalyzed (write, returns `{analyzed}`).
- **DEC-9** — Topology decision (Vercel rewrite vs direct+CORS) stays OPEN until cutover per `FRONTEND-INTEGRATION.md`; the Laravel contract here is topology-independent.

### Acceptance — Objective (machine-checkable)

- **ACC-1** — `GET /reports/health?department_id={uuid}` 200 bare shape with `departmentName`, `stats[]`, paginated `rawAppointments`, `summaries[]`, frequency arrays; unknown UUID → 404.
- **ACC-2** — `GET /reports/health` (no `department_id`) tokenless → 401; non-admin token → 403; admin token → 200 `departmentName: "All Departments"`.
- **ACC-3** — `GET /reports/backlog` buckets equal exactly the 4 DEC-3 labels with counts summing to `summary.totalUnresolved`; `totalPending`/`totalApproved` match filtered entries.
- **ACC-4** — `GET /reports/coverage` `overall.coveragePercentage` equals `round(studentsWithConsultations/totalStudents*100)` (0 when no students); trend `studentsWithConsultations` is non-decreasing.
- **ACC-5** — `GET /reports/demand`, `/distribution`, `/responsiveness` 200 with DEC-5–DEC-7 shapes; `status=completed` filters to COMPLETED rows; `status=bogus` → 422; malformed date → 422.
- **ACC-6** — `GET /reports/sentiment?evaluation_id={id}` 200 stored score/label; `POST /reports/sentiment/analyze` (write) 200 recomputed; tokenless write → 401; read-level token on write → 403.
- **ACC-7** — `POST /reports/sentiment/batch` returns `{analyzed: n≤50}`; second immediate call returns `{analyzed: 0}` when queue drained.
- **ACC-8** — Full suite green pasted by USER (`docker compose exec consult-app php artisan test` from repo root, sequential); HealthTest unbroken.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer confirms each of the 7 Laravel report payloads matches the legacy Server Component numbers on a sampled department + date range (same filters, same totals).
- **ACC-S2** — Reviewer confirms locked-UI behavior: an admin-only unscoped URL pasted as a faculty user surfaces the locked tab (JSON 403), not a breakage or redirect.

## Deliverables

- **D-1** — `ReportService` (+ repository aggregation over `loa_consult`, paginated raw lists, weighted/merges per DEC-4–DEC-7).
- **D-2** — `ReportController` + flat gated routes `GET /reports/{health,backlog,coverage,demand,distribution,responsiveness,sentiment}` + `POST /reports/sentiment/{analyze,batch}` in `routes/api.php` (statics before params) + `config/consult-endpoints.php` catalog rows (7 read + 2 write) + Auth JSON counterpart at deploy time.
- **D-3** — `tests/Feature/Api/ReportsTest.php` (ACC-1–ACC-8, one behavior per test, `RefreshDatabase`, JWT claims helper, never hardcoded tokens) + user-run manual checks (ACC-S1–ACC-S2).
- **D-4** — USER-run runbook entries (repo-root `docker compose exec consult-app php artisan test`, single-file and `--filter ReportsTest` variants, sequential only).

## Glossary

| Term | Meaning |
|------|---------|
| Unscoped | `department_id` absent → all-departments merge, `departmentName: "All Departments"`, admin only |
| Bare shapes | `{report}`, `{data}`, `{success:true}`, `{error}` with no envelope; preserved until cutover |
| Green pasted | USER ran suite and pasted passing output; required for completeness per §1.7 |
| Phase E | Reports-as-Laravel-API + cutover (this spec); no REST report routes exist today |

## References

- `api-endpoints.md` Final v2.1 §2.2 (reports deferred, no REST routes), §5 (104+5 catalog conventions)
- `auth-integration.md` Final v1.5 §2 (topology A/B), §9 (group matrices), §11 (port pattern)
- `data-model.md` Final v1.3 (shape contract; appointments/evaluations/academic tables reports aggregate)
- `url-flattening.md` Final v1.1 (flat scheme, in-place v1)
- `test-suite.md` Final v1.3 (CON-11 bypass, sequential user-run contract)
- `FRONTEND-INTEGRATION.md` Final v1.0 (cutover handoff; topology OPEN)
- Legacy ground truth (read-only): `features/reports/{backlog,coverage,demand,distribution,responsiveness,admin-reports}.controller.ts`, `reports.service.ts`, `sentiment.service.ts`, `reports.repository.ts`, 20 `app/*/reports/**/page.tsx`
- Root `AGENTS.md` (Rule 0/0.5, No Auto-Pilot) + assembly `AGENTS.md` §1.9–§1.10 (spec format, behavioral coverage)

---

## Document Control

- **Status:** Final v1.0 (user-approved 2026-09-26; scan-verified: 112 route.ts, no `app/api/reports/**`; 7 families mapped from `features/reports/**`)
- **Created:** 2026-09-26 as v0.1 Draft (scan-first); promoted Final v1.0 2026-09-26 with no normative change
- **Next:** implement D-1–D-4 exactly to spec (needs user yes per step; TDD per §1.10)
