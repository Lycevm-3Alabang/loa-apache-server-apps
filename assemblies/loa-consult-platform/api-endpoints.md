# LOA Consult Platform — API Endpoints
## Product Assembly Component Specification

**Version:** 2.1
**Status:** Final (tenant rename `loa` → `loa-consultation`, user-approved 2026-09-24)
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> **Priority spec (once Final).** This is the source of truth for the LOA Consult Platform's REST API.
> Ground truth is the **route.ts scan of `D:\loa\e-consultation\app\api` (2026-09-18: 112 files, ~142 method+path combos, of which 118 migrate to Laravel)** — NOT `specs/endpoint-catalog.md` (143 entries, drift recorded in §7).
> It refines `assemblies/loa-consult-platform/README.md` §9 (REST conventions corrected: PATCH/PUT per code; aspirational gaps listed in §6).

---

# 1. Purpose

It answers:

> **"What endpoints does the LOA Consult Platform expose, what do they accept, what do they return, and who may call them?"**

Requirements extracted from the `e-consultation` Next.js app:

- `app/api/**/route.ts` — route + method inventory (ground truth, §5)
- `supabase-schema.sql` — Postgres schema (adapted to MySQL 8, see `data-model.md` at spec time)
- `features/*/*.service.ts` — business rules (ported into module specs at spec time)
- `business-contexts/consultation/`, `business-contexts/evaluation/` — booking/evaluation rule owners (Draft). Per AI-GUIDE, assemblies contain no business logic: module specs must comply with these contexts, never duplicate their rules.

The API is **API-first and level-gated**. Authentication is delegated to the LOA Auth Platform (JWT bearer); the Consult Platform enforces access using the Auth Platform's **level-based endpoint grants** (`<level>:<path>` entries in the JWT `permissions` claim), per `tenant-group-endpoint-grants.md`.

---

# 2. Scope

## 2.1 In Scope (this spec)

| Group | Endpoints | required_level |
|-------|-----------|----------------|
| Auth (SSO) | callback, refresh, logout | public (SSO payload / cookie) |
| Appointments | CRUD-lite + batch + faculty-booked + action + files + retry-sync + student-cancel + teams-link | `read` (get/list), `write` (all mutations) |
| Availability | list + create | `read` / `write` |
| Admin users | link reads only (`/users/primary`, `/users/attendees`, `/users/{id}/related-data`); all user writes Auth-owned | `read` |
| Academic | departments, department-courses, subjects, sections, faculty-subjects, enrollments (flat resources) | `read` (lists) / `write` (department-courses POST only) / `admin` (all other mutations, per module spec) |
| Semesters | CRUD + impacts + count-active | `read` / `admin` (code gates ALL mutations to ADMIN-group holders); count-active public |
| Evaluations | CRUD-lite + ratings + comments + submit + pending + dispute + bootstrap | `read` / `write` |
| Evaluation periods | CRUD + activate/reset + rubric copy + rubric items | `read` / `write` / `admin` (delete/reset) |
| Evaluation results | single scoped surface (`/evaluation-results*`, group-driven per `url-flattening.md`) + invalidate + visibility + disabled set | `read` (all reads), `admin` (invalidate/visibility/restore) |
| Rubric groups | CRUD + items + duplicate + snapshot + categories | `read` / `write` / `admin` (delete) |
| Import | preview + per-domain reference + domain imports (users/reference Auth-owned, absent) | `read` (reference), `admin` (preview/import) |
| Data & audit | audit-logs (pending table — unserved) + delete-students + export-consultations + reset-db + evaluation-mappings | `read` / `admin` (destructive) |
| User lookup | primary + attendees | `read` |
| Health | health check | public |

## 2.2 Out of Scope (deleted, auth-owned, or deferred)

| Feature | Reason |
|---------|--------|
| Self-hosted auth (`[...nextauth]`, activate, forgot/change password) | Owned by Auth Platform. Deleted at cutover. |
| Access config + user permissions (`/admin/access-config/*`, `/admin/user-permissions/*`, `group_access`, `user_permissions`) | Replaced by Auth Platform catalog/grants. No Laravel equivalent. Frontend untouched until cutover. |
| Reports (`/reports/health|backlog|coverage|demand|distribution|responsiveness|sentiment`) | **Deferred to Phase E.** No REST routes exist; Server Components compute directly. |
| Bug reports (`/api/bug-reports`, `/api/bug-reports/{id}`) | Support tool, not domain. Stays in Next.js. |
| Forbidden telemetry (`POST /api/audit/forbidden`) | Client-lock telemetry. Stays in Next.js. |
| README §9 aspirational (`PUT .../accept|decline|complete|cancel`, `sync-teams`, period `subjects`/`enrollments`, `evaluations/submitted`, sentiment) | **No route files exist** (see §6). Confirm with frontend team before speccing. |

---

# 3. Cross-Cutting Conventions

## 3.1 Base URL & Versioning

```
https://aces-api.lyceumalabang.edu.ph/api/v1
```

- Version in the URL path (`/api/v1/...`). Next.js `/api/*` prefix maps 1:1 onto Laravel `/api/v1/*` (§5).
- Media type `application/json` unless stated (multipart for appointment files).
- No trailing slashes.

## 3.2 Authentication

- All endpoints except Public group and SSO group require `Authorization: Bearer <access_token>`.
- JWT issued by LOA Auth Platform; validated **locally** with shared `JWT_SECRET` (HMAC-SHA256) — no HTTP call per request.
- Two middleware aliases gate every non-public route (mirrors cert):
  - `jwt.auth` — signature, `type=access`, `exp`, tenant-claim validation.
  - `jwt.endpoint` — level check against local catalog mirror + JWT `permissions` claim.
- `401` missing/expired/invalid. `403` valid token, insufficient level (or tenant mismatch).
- **cPanel gotcha (from AI-GUIDE):** `public/.htaccess` MUST forward the `Authorization` header to PHP-FPM, else every gated endpoint 401s in production while passing locally.

## 3.3 Tenant Scoping

- LOA Consult runs its own tenant (`TENANT_SLUG=loa-consultation`, cert pattern like cert's `loa-e-cert`). Clients never send tenant identifiers.
- Identity (users, groups, membership) is sourced from Auth-issued JWT claims, with member management via the Auth API (`tenant-app-api.md`); all consult domain data lives in the Consult MySQL database. No cross-database reads.
- Token `tenant.slug` ≠ `loa-consultation` → `403`.

## 3.4 Response Shapes (as implemented)

- Success returns bare resource keys: `{ "appointments": [...] }`, `{ "appointment": {...}, "conflicts": [...] }`, `{ "user": {...} }`, `{ "users": [...], "departments": [...] }`, `{ "totalRows": n, "rows": [...], "errors": [...] }`. Variants in code: `{ "data": ... }` (semesters), `{ "success": true }` (deletes). There is NO `{data,meta}` collection envelope.
- User bulk result: `{ "created": n, "updated": n, "failed": n }` (+ audit log entry). Duplicate email returns 200 with the existing user, not 409.
- Errors: `{ "error": "<message>" }` (string; occasionally an object with message/code/details/hint, or extra `details`/`conflicts` keys). Statuses used: 200 / 201 / 400 / 401 / 403 / 404 / 500.
- Cert-style `{data,meta}` envelope: ADOPTION DECISION at build time. The frontend is untouched initially, so Laravel must return these same bare shapes; envelope migration (if ever) is cutover work, not Phase B–D.

## 3.5 Pagination (as implemented: none)

- Code has no limit/offset: list routes fetch all rows (`listAll`) and filter in memory (`q` param on appointments). No `meta`, no `has_more`.
- Server-side pagination is a build-time performance decision for large tables (users, audit logs); it must preserve the bare response shapes in §3.4 until cutover. Page-size defaults deferred to module specs.

## 3.6 IDs & Timestamps

- IDs: UUID strings. Timestamps: RFC 3339 UTC. Dates: `YYYY-MM-DD`.

## 3.7 Uploads & Imports (as implemented)

- Appointment files (`POST /appointments/{id}/files`): **base64 JSON** — `{ files: [{fileName, fileType, fileData, fileSize}] }`. 5 MB max per file, images only (PNG/JPEG/GIF/WebP), dedup by fileName+fileSize, faculty-owner enforced (403 otherwise). Multipart migration is a build decision, not assumed. Per-endpoint size limits specified in module specs (cert `body-size-limits.md` pattern).
- Import preview (`POST /import/preview`): **CSV multipart upload** (`file` + `type` fields via `formData`), returns `{totalRows, rows, errors}` with per-row `emailExists`/`existingName`. Permitted to ADMIN- and DEAN-group holders (Auth tenant groups).
- User bulk import (`POST /admin/users` with `{users: [...]}`): JSON array with `preview:true` dry-run mode; result `{created, updated, failed}`.
- CSV parsing location (frontend vs backend) decided per module spec at build time.

## 3.8 Bulk Semantics (as implemented)

- Appointment batch (`POST /appointments/batch`): one booking across `facultyIds` sharing a `sessionGroupId`; returns `{appointment, sessionGroupId, conflicts}` (201). Rule: creator cannot be the student participant (400).
- No generic `{success, failed, errors}` bulk envelope exists in code. If Laravel introduces one for new bulk paths, module specs must define it — it is not current behavior.
- Consult emails (currently Vercel Workflows + Nodemailer in Next.js) move to Laravel mail + queue at build time (cert `queue-infrastructure-spec.md` pattern); module specs define triggers per domain.

---

# 4. Authorization Model

## 4.0 No Local Roles (binding rule)

`aces-admin`, `aces-dean`, `aces-faculty`, `aces-user` (student) exist **only as auth-app tenant groups**. Consult owns, stores, and defines none of them. Consequences:

- No role column on any table; no pipe-delimited role parsing; no `hasRole()`/`requireAdmin()`-style local checks — those are Next.js legacy being retired.
- Group membership is read-only input: the JWT `groups` claim (Auth vocabulary). Laravel evaluates "caller holds tenant group X" only.
- Endpoint access always resolves through §4.1 levels + §4.5 group-membership scoping — never through a local role.
- Paths are flat resources and authorize nothing (§5; `url-flattening.md` Final v1.1 removed the legacy `/admin`, `/dean`, `/faculty`, `/student` namespaces in v2.0). No role-prefixed routes.
- Backend endpoints are domain-unified (evaluation results served from one surface scoped by caller); role-split presentation (`/faculty/...`, `/student/...` pages) lives in the frontend, which is untouched.
- New groups, if ever needed, are created in Auth — never in Consult.

## 4.1 Level-Based Enforcement

1. Every non-public endpoint has a `required_level` (`read` | `write` | `admin`) in the Auth Platform catalog (§5 is the import payload).
2. Auth resolves effective level per endpoint (group priority, deny-wins on ties, user overrides last) into JWT `permissions` as `<level>:<path>`.
3. Consult validates locally against a **local catalog mirror** (`config/consult-endpoints.php` at build time). No DB/HTTP per request.

## 4.2 Levels

| Level | Ordinal | Meaning |
|-------|---------|---------|
| `read` | 1 | View / list |
| `write` | 2 | Create / update / non-destructive mutations |
| `admin` | 3 | Destructive / sensitive (delete users, reset DB, invalidate evaluations, imports) |

Higher covers lower (`admin` satisfies `read`-required). `admin` is operational: granted to the ADMIN tenant group only.

## 4.3 JWT `permissions` Claim

`<level>:<path>` entries, `{param}`-aware (`{id}` matches one segment). Only non-`deny` published. Detail in `auth-integration.md`.

## 4.4 Group → Grant Mapping (Auth-owned)

Tenant groups (`aces-admin`, `aces-dean`, `aces-faculty`, `aces-user`) are created, assigned, and granted **in Auth only** (provisioning: `auth-integration.md` §8, `consult-readiness.md` Final v1.4). This spec declares no grants. Capability needs per group, for Auth provisioning:

- `aces-admin` group: `admin` on every cataloged path.
- `aces-dean` group: `read` across academic/users/semesters/periods/results/appointments/import/data; `write` on department-courses POST. `admin`-grants needed on: visibility (§7 #10), period-activate, department-courses DELETE, import-preview. No destructive.
- `aces-faculty` group: `read` on appointments/results/periods/rubrics/semesters/lookups; `write` on appointment-actions, teams-link, availability-rules.
- `aces-user` group (student): `write` on own appointments/evaluations/bootstrap; `read` on own views plus rubric/period/semester reads.

Authoritative matrix: Auth provisioning records (stale copy in e-consultation `specs/endpoint-catalog.md` §5 — levels stand, methods do not).

## 4.5 Scoping (group membership + ownership, as implemented in route handlers)

Code gates below name Auth tenant groups; Laravel evaluates them as JWT `groups`-claim membership — never a local role:

- **Booking owner** — callers holding STUDENT book as themselves; holders of FACULTY/DEAN may book on behalf (`body.studentId`) or create internal meetings (`studentId` null); creator ≠ student participant (400).
- **Files owner** — upload requires FACULTY/DEAN-group membership and `appointment.facultyId === caller id` (403 otherwise).
- **Evaluation owner** — list/create require STUDENT-group membership; create enforces enrollment unless `source === "unenrolled"`; per-id access enforces `evaluatorId === caller id` (403).
- **User admin** — Auth-owned (v2.0): user writes live in the Auth API; consult exposes link reads only (`/users/primary`, `/users/attendees`, `/users/{id}/related-data` via email link).
- **Import preview** — `aces-admin`- or `aces-dean`-group membership (preserve DEAN via grant, §5.10).
- Provisional (confirm in module specs): availability-rule ownership (`created_by`); appointment `{action}` faculty-match; dean department-scope.
- **Results scoped surface** — one flat surface (`/evaluation-results*`); admin sees all, dean own department, faculty own results (visibility-gated); explicit `deny` reserved for exceptions, absence denies otherwise.

---

# 5. Route Summary

Next path → Laravel `/api/v1` path (prefix swap only). `required_level` per method. Path prefixes are namespaces, not authorization (§4.0).

## 5.1 Appointments → `AppointmentController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/appointments` | `read` |
| POST | `/appointments` | `write` |
| GET | `/appointments/{id}` | `read` |
| POST | `/appointments/batch` | `write` |
| GET | `/appointments/faculty-booked` | `read` |
| POST | `/appointments/{id}/{action}` | `write` |
| POST | `/appointments/{id}/files` | `write` |
| POST | `/appointments/{id}/retry-sync` | `write` |
| POST | `/appointments/{id}/student-cancel` | `write` |
| POST | `/appointments/slots/{slotId}/teams-link` | `write` |

## 5.2 Availability → `AvailabilityRuleController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/availability-rules` | `read` |
| POST | `/availability-rules` | `write` |

## 5.3 Users link reads → `UserLinkController` (v2.0: user writes Auth-owned, absent here)

| Method | Path | Level |
|--------|------|-------|
| GET | `/users/primary` | `read` |
| GET | `/users/attendees` | `read` |
| GET | `/users/{id}/related-data` | `read` |

## 5.4 Academic → `AcademicController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/departments` | `read` |
| POST | `/departments` | `admin` |
| PATCH | `/departments/{id}` | `admin` |
| GET | `/department-courses` | `read` |
| POST | `/department-courses` | `write` |
| DELETE | `/department-courses/{id}` | `admin` |
| POST | `/subjects` | `admin` |
| PATCH | `/subjects/{id}` | `admin` |
| POST | `/sections` | `admin` |
| PATCH | `/sections/{id}` | `admin` |
| POST | `/sections/fix-names` | `admin` |
| POST | `/faculty-subjects` | `admin` |
| POST | `/faculty-subjects/reassign` | `admin` |
| POST | `/student-enrollments` | `admin` |
| DELETE | `/student-enrollments/{id}` | `admin` |

## 5.5 Semesters → `SemesterController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/semesters` | `read` |
| POST | `/semesters` | `write` |
| GET | `/semesters/{id}` | `read` |
| POST | `/semesters/{id}` | `admin` |
| PATCH | `/semesters/{id}` | `admin` |
| DELETE | `/semesters/{id}` | `admin` |
| GET | `/semesters/{id}/impacts` | `admin` |
| GET | `/semesters/count-active` | public |

## 5.6 Evaluations → `EvaluationController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/evaluations` | `read` |
| POST | `/evaluations` | `write` |
| GET | `/evaluations/{id}` | `read` |
| GET | `/evaluations/{id}/ratings` | `read` |
| PUT | `/evaluations/{id}/ratings` | `write` |
| GET | `/evaluations/{id}/comments` | `read` |
| POST | `/evaluations/{id}/comments` | `write` |
| POST | `/evaluations/{id}/submit` | `write` |
| GET | `/evaluations/pending` | `read` |
| POST | `/evaluations/dispute` | `write` |
| GET | `/evaluation-comments` | `read` |
| GET | `/evaluations/bootstrap` | `write` (method-correction candidate: POST; see §7) |

## 5.7 Evaluation periods → `EvaluationPeriodController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/evaluation-periods` | `read` |
| POST | `/evaluation-periods` | `admin` |
| GET | `/evaluation-periods/{id}` | `read` |
| PUT | `/evaluation-periods/{id}` | `admin` |
| DELETE | `/evaluation-periods/{id}` | `admin` |
| POST | `/evaluation-periods/{id}/activate` | `admin` |
| POST | `/evaluation-periods/{id}/reset` | `admin` |
| GET | `/evaluation-periods/{id}/rubric` | `read` |
| POST | `/evaluation-periods/{id}/rubric/copy` | `read` |
| POST | `/evaluation-periods/{id}/rubrics/items` | `admin` |
| PATCH | `/evaluation-periods/{id}/rubrics/items/{itemId}` | `admin` |
| DELETE | `/evaluation-periods/{id}/rubrics/items/{itemId}` | `admin` |

## 5.8 Evaluation results → `EvaluationResultController` (v2.0: single scoped surface)

| Method | Path | Level |
|--------|------|-------|
| GET | `/evaluation-results` | `read` (admin all / dean own-dept / faculty own) |
| GET | `/evaluation-results/department` | `read` |
| GET | `/evaluation-results/details` | `read` |
| GET | `/evaluation-results/subjects` | `read` |
| GET | `/evaluation-results/subjects/{facultySubjectId}` | `read` |
| GET | `/evaluation-results/departments/{departmentId}` | `read` |
| GET | `/evaluation-results/faculty/{facultyId}` | `read` |
| GET | `/evaluation-results/groups/{facultySubjectId}` | `read` |
| POST | `/evaluation-results/invalidate` | `admin` |
| POST | `/evaluation-results/visibility` | `admin` |
| GET | `/evaluations/disabled` | `read` |
| DELETE | `/evaluations/disabled` | `admin` |
| POST | `/evaluations/disabled/restore` | `admin` |
| GET | `/evaluations/{evaluationId}/details` | `read` |
| POST | `/evaluations/{evaluationId}/invalidate` | `admin` |

## 5.9 Rubric groups → `RubricGroupController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/rubric-groups` | `read` |
| POST | `/rubric-groups` | `admin` |
| GET | `/rubric-groups/{id}` | `read` |
| PATCH | `/rubric-groups/{id}` | `admin` |
| DELETE | `/rubric-groups/{id}` | `admin` |
| POST | `/rubric-groups/{id}/items` | `admin` |
| PATCH | `/rubric-groups/{id}/items/{itemId}` | `admin` |
| DELETE | `/rubric-groups/{id}/items/{itemId}` | `admin` |
| POST | `/rubric-groups/{id}/duplicate` | `admin` |
| GET | `/rubric-groups/{id}/snapshot` | `read` (provisional; catalog claimed POST — see §7) |
| POST | `/rubric-groups/{id}/categories` | `admin` |
| DELETE | `/rubric-groups/{id}/categories` | `admin` (body `{categoryId}`, 400 if missing) |

## 5.10 Import → `ImportController`

| Method | Path | Level |
|--------|------|-------|
| POST | `/import/preview` | `admin` |
| GET | `/import/departments-courses/reference` | `read` |
| POST | `/import/departments-courses` | `admin` |
| GET | `/import/faculties/reference` | `read` |
| POST | `/import/faculties` | `admin` |
| GET | `/import/students/reference` | `read` |
| GET | `/import/students` | `read` |
| POST | `/import/students` | `admin` |
| GET | `/import/subjects/reference` | `read` |
| GET | `/import/sections/reference` | `read` |

Code permits `aces-admin`- and `aces-dean`-group holders on preview — preserve via DEAN grant (module spec). `GET /import/users/reference` is Auth-owned (tenant-member endpoints); no consult equivalent.

## 5.11 Data & audit → `DataAuditController`

| Method | Path | Level |
|--------|------|-------|
| GET | `/audit-logs` | `read` (pending table — unserved until `audit_logs` migrates) |
| DELETE | `/audit-logs` | `admin` (pending table — unserved until `audit_logs` migrates) |
| POST | `/data/delete-students` | `admin` |
| POST | `/data/export-consultations` | `admin` |
| POST | `/data/reset-db` | `admin` |
| GET | `/data/evaluation-mappings` | `read` |

## 5.12 User lookup

Moved to §5.3 in v2.0 (single users-link family). Both accept `?department=` filter (booking flows).

## 5.13 Health (public)

| Method | Path | Auth |
|--------|------|------|
| GET | `/health` | public |

Auth SSO group (`POST /auth/callback|refresh|logout`, public throttled) specified in `auth-integration.md`.

**Total: 104 JWT-gated combos + 5 public (health, count-active, callback, refresh, logout).** Gated = 10 appointments + 2 availability + 3 users-link + 15 academic + 7 semesters + 12 evaluations + 12 periods + 15 results + 12 rubrics + 10 import + 6 data (incl. 2 pending-table audit-logs).

---

# 6. Aspirational Gaps (in README §9, no route files — NOT specced)

- `PUT /appointments/{id}/accept|decline|complete|cancel` — code uses `POST /appointments/{id}/{action}`. Do not spec PUT variants unless frontend team confirms.
- `POST /api/admin/sync-teams`, period `subjects`/`faculty-subjects`/`enrollments`/`enrollment-stats`, `GET /evaluations/submitted`, sentiment endpoints — claimed in README eval-module tables, absent from `app/api`. Confirm before speccing.
- `GET /api/v1/reports/*` (7 types) — Phase E.

---

# 7. Design Decisions & Notes

| # | Decision / Finding | Rationale |
|---|-------------------|-----------|
| 1 | Ground truth = route.ts scan, not `endpoint-catalog.md` | Catalog (143) drifts: `[action]` POST-only; teams-link POST-only; bootstrap GET; snapshot GET; categories POST+DELETE; `semesters/[id]` extra POST; `audit-logs` DELETE; academic singletons PATCH/DELETE-only; catalog `PATCH /appointments/{id}` has no route file (mutations via `POST [action]`); catalog `GET /api/audit/forbidden` vs actual POST |
| 2 | `PUT` kept where code uses PUT (ratings, periods) | Spec follows code; no REST-normalization without frontend-team sign-off (frontend untouched) |
| 3 | Bootstrap rated `write` despite GET method | State-changing semantics; method-correction candidate (POST) for module spec |
| 4 | Snapshot rated provisional `read` | Code is GET; catalog claimed POST — module spec must confirm whether it mutates |
| 5 | `DELETE /admin/audit-logs`, `DELETE .../categories`, `DELETE .../disabled` provisional `admin` | No catalog entries; destructive → `admin` by convention |
| 6 | Level labels are the contract; enforcement uses code ordinals | `EndpointPolicyMiddleware` compares `deny=-1`, `read=1`, `write=2`, `admin=3` (verified in cert code). Keep labels identical across repos; Auth owns grant resolution, consult mirrors the middleware verbatim |
| 7 | `.htaccess` Authorization rule required | AI-GUIDE cPanel gotcha; else all gated endpoints 401 in prod |
| 8 | `JWT_SECRET`/`ENCRYPTION_KEY` byte-identical with Auth | AI-GUIDE gotcha; verify via tinker post-deploy, never commit |
| 9 | RESOLVED from code (2026-09-18) | `POST /semesters/{id}` = activate action (`activateSemester`, ADMIN-group-only, audit ACTIVATE_SEMESTER) → `admin`; semesters PATCH likewise ADMIN-group-only in code → `admin`. `DELETE .../categories` takes body `{categoryId}` (400 if missing), ADMIN-group-only with seed/lock guard. `DELETE .../disabled` takes `{all:true}` or `{ids:[...]}`, ADMIN-group-only (`requireAdmin`). Table levels corrected |
| 10 | RESOLVED (2026-09-18): levels stay Auth-driven, no local roles | Consult strips local admin/role concepts — access comes from auth-app tenant groups (cert pattern). Visibility remains `required_level=admin`; whether DEAN toggles it is a grant in Auth (`admin` on that path for the DEAN group), default ADMIN-group-only. No level change, no local role logic |
| 11 | Contracts reflect code, not cert | Bare shapes (§3.4), no pagination (§3.5), base64 files + CSV preview (§3.7), `{created,updated,failed}` bulk (§3.8). Cert-envelope/multipart/JSON-import adoption are build decisions, never assumed |
| 12 | Local roles stripped (2026-09-18) | `ADMIN`/`DEAN`/`FACULTY`/`STUDENT`/`GUEST` exist only as auth-app tenant groups (§4.0). No role column, no pipe parsing, no local checks; gates are JWT group-membership + levels. Backend unification of group-split paths deferred to module specs; role-split presentation stays frontend (untouched) |
| 15 | Academic levels corrected (module spec, 2026-09-18) | Code gates ADMIN-group holders on all academic POST/PATCH (not `write`); impacts ADMIN-only; dept-courses POST stays `write` (ADMIN-or-DEAN holders); DEAN grants on dept-courses DELETE + import-preview. Semesters GET has no code auth — hardened to `read` |
| 16 | Appointments module clean (2026-09-18) | All 12 combos `read`/`write` as specced — code gates are group-membership only, no level corrections. Dispatch table, batch/single semantics, slot-link validation, availability self/other rules captured in module |
| 17 | Evaluations levels corrected (module spec, 2026-09-18) | Periods POST/PUT/activate + period-items POST/PATCH + rubric-groups POST/PATCH/items/duplicate `write`→`admin` (code gates ADMIN holders); rubric-copy `write`→`read` (fetch misnomer, no duplication in code); evaluation-comments gate ADMIN-or-DEAN holders |
| 18 | Admin+import module DEFERRED (2026-09-18) | §5.3/5.10/5.11/5.12 inventoried but not contracted: user management mirrors Auth identity, access-config/permissions already live in the Auth catalog, destructive data ops need Auth-governed handling — enforcement, not consult domain. Detailed contracts deferred to implementation phase; §5 stands as the endpoint list |
| 19 | Frontend 403-contract (app scan, 2026-09-18) | UI locks per-endpoint on 403 (`setLockedEndpoint`, `LockedTab`) and reports via `POST /api/audit/forbidden` (`lib/api/client.ts`). Laravel must return JSON 403 — never redirects or login pages — on every gated API route |
| 20 | Dormant watchlist (app scan, 2026-09-18) | No fetch callers observed for: `GET /api/evaluations/pending`, `POST /api/import/preview`, period `rubric` GET / `rubric/copy`, rubric `duplicate`/`snapshot`, `GET /api/faculty/evaluation-results/subjects/{id}`. Routes exist → retained in catalog; usage to confirm at cutover (server-driven or dormant). Dead ref only: commented-out `/api/faculty/search` (no such route — ignore) |
| 21 | Redundancy audit (2026-09-18) | Exact duplicates (one impl, unify at module time): period `rubric` GET ≡ `rubric/copy` POST (identical fetch — alias then drop at cutover); period `rubrics/items*` ≡ group `items*` (same repo calls, but period variants skip the seed/lock guard — unify on the stronger guard). Cutover drops (frontend migrates first): `rubric/copy`, POST-evaluations-with-`{id}` branch (≡ GET-by-id), `pending` if bootstrap covers all callers. Overlaps kept with distinct scope: single vs bulk invalidates, result reads per prefix, bootstrap composite, activate vs PATCH-isActive. Unread handler: `DELETE /api/admin/audit-logs` (verify at build) |
| 22 | URL flattening (2026-09-23, `url-flattening.md` v1.1) | Role prefixes removed in place under `/api/v1`: single scoped results surface (group-driven), Auth-owned paths dropped (users writes, users/reference), gate-blocked paths retained pending (audit-logs). Catalog 118 → 104 gated; methods/levels otherwise unchanged |
| 13 | Users final shape (lib/db + migrations, 2026-09-18) | `evaluationPeriodId` renamed to `semester_id` (Step 9); `deleted_at` backs soft-delete endpoints; no `evaluation_eligible` (README-only, never migrated). data-model §4 corrected |
| 14 | Query shapes for module specs (lib/db/common.ts) | Appointment detail = appointment + student/faculty brief + attendees(+user brief) + timeSlots; history variant drops student join + attendees. PostgREST embeds pin FK names (`users!appointments_studentId_fkey`) — replace with Eloquent relationships. Ratings fan-out (ratings→items→categories + in-memory maps) becomes JOINs. `toUserWithRole` pipe-join retired by §4.0 |

---

## Document Control

- **Status:** Final v2.1 (tenant rename `loa` → `loa-consultation`, user-approved 2026-09-24)
- **Created:** 2026-09-18
- **Source:** route.ts scan (`D:\loa\e-consultation\app\api`, 112 files) + 50 handlers read verbatim + frontend usage scan
- **v2.0:** flat resources (104+5), aces-* groups, single scoped results, Auth-owned drops documented; code implemented + green (F1/F2)
- **Next:** code/config/test slug updates + Auth re-provisioning (`loa-consultation`); modules pointer refresh (test-suite, admin-import)
