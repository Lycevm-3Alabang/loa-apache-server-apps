# AGENTS.md — LOA Consult Platform Agent Contract

> This file is the standing contract for any AI agent working in `assemblies/loa-consult-platform/`.
> It outranks ad-hoc instructions when they conflict. If a request violates
> Section 1, stop and ask instead of proceeding.
> Companion rules: root `AGENTS.md` (sole entry) + `principles.md` (SDD+TDD detail) + `platform.md` (architecture + gotchas). Change journal: root `TODO.md` + `PROJECT_UPDATES.md`.

---

## 1. Working agreements (must-follow, moving forward)

1. **Spec-first, no exceptions.** No code, migration, config, or dependency changes without a
   written spec the user has explicitly marked **Final**. The loop is:
   discuss → write spec → user approves spec as Final → implement exactly the spec.
   SDD writes the contract; TDD tests it against reality.
2. **No auto-pilot.** Never chain beyond what was approved. Finish the approved
   step, report, and stop. Ask before starting the next phase, even if it seems
   "obvious."
3. **Agent does not run CLIs — the user does.** The agent must NEVER execute
   terminal commands (`docker compose`/`php artisan`/`composer`/`git`/builds/deploys, etc. — even read-only ones). The agent
   provides the exact commands; the user runs them and pastes back output.
   Rationale: the user owns the device, build environment, and credentials.
   Docker runs from repo root only (`loa-platform` project); never an assembly-level compose file.
4. **No breaking changes unless the finalized spec requires them.** Bare response shapes
   (`{appointments}`, `{data}`, `{success:true}`, `{error}` — no envelope), route paths under
   `/api/v1/*`, the `loa_consult` schema, and Auth tenant catalog/grant entries must stay compatible. Any
   migration must be in the spec with rollback noted.
5. **Auth invariant.** Every change must keep JWT local validation working (shared HMAC-SHA256 secret,
   `type=access`, local validation — no HTTP per request) + tenant scoping (`TENANT_SLUG=loa`,
   token `tenant.slug ≠ loa → 403`) + bare shapes until cutover. Never add local roles; groups come
   only from the JWT `groups` claim (Auth-owned).
6. **Keep it cPanel-deployable.** `public/` docroot; `public/.htaccess` MUST forward `Authorization`
   before the front-controller rule (else gated endpoints 401 in prod while passing locally on nginx);
   `JWT_SECRET`/`ENCRYPTION_KEY` byte-identical with Auth; tenant slug must match Auth DB. Never commit secrets.
7. **Keep HealthTest green.** Nothing may break `GET /api/v1/health → {status:ok, service:loa-consult-platform}`.
   Suites run sequentially only (shared `loa_consult_test` DB deadlocks on concurrent runs). No change is
   complete until the user pastes green results.
8. **Document after approved changes.** Update root `TODO.md` (program tracker) + `PROJECT.md` (Phase 2 rows) +
   `PROJECT_UPDATES.md` (Consult section + Last Session Notes) and append a `Current status` entry in Section 3 below.
9. **Spec format standard (all future specs).** Every normative spec MUST live
   as its own file under `assemblies/loa-consult-platform/` (never inline in `AGENTS.md` — §4 is a
   pointer only) and MUST follow the consult template: metadata table
   (ID/Title/Status/Owner/Version/Scope/Non-goals/Layer) + RFC 2119 terminology +
   `Context` + `Constraints` (numbered `CON-*`, MUST/MUST NOT) + `Goal`
   (decisions `DEC-*`, acceptance `ACC-*`) + `Deliverables` (numbered `D-*`) + `Glossary` + `References`.
   No normative change via reformat/polish alone.
10. **Spec-first + TDD with behavioral coverage.** Every future spec MUST separate:
    - **Objective** — deterministic, machine-checkable (`ACC-*`): statuses, shapes, counts, guards
      (409/422/403/404), level gates, idempotency.
    - **Subjective** — human-judged checks stated as observable reviewer steps
      (e.g. "reviewer confirms admin setup flow completes end-to-end in the UI with no error toast").
    TDD MUST cover both: `php artisan test` (user-run, request-level over mocks, one behavior per test,
    `RefreshDatabase`, JWT claims helper — never hardcoded tokens) for logic, plus user-run manual
    checks (health curl, SSO callback) for paths tests cannot prove. No behavior without a `CON-*` +
    `ACC-*` + `D-*`.

---

## 2. App overview and scaffold

**What it is:** LOA Consult Platform — consultation booking + faculty evaluation API, Laravel 12 +
PHP 8.3 + MySQL 8 (`loa_consult`), subdomain `aces-api.lyceumalabang.edu.ph`. **Thin product assembly**:
owns routing/middleware/JWT-validation/RBAC/docs/deploy only; owns **no business logic**. It answers
"How do students book consultations and evaluate faculty?"

**Contexts composed (one assembly, two capabilities):** Consultation (appointments, availability, slots,
attendees, files, PENDING→APPROVED→COMPLETED) + Evaluation (periods, rubrics, ratings, comments, results,
DRAFT→SUBMITTED) over shared Education domains (Department, Course, Semester, Subject, Section, Enrollment,
Student, Faculty, Faculty Loading). Logically independent (no consultation↔evaluation FK; correlation via
events only); single deployable sharing academic actors + Auth claims.

**Features:** SSO trio (callback/refresh/logout, `loa_connect_refresh` cookie) · `jwt.auth` + `jwt.endpoint`
level gates · appointments CRUD-lite + batch + action dispatch + files (base64, owner rule) + Teams sync ·
availability rules · academic CRUD + semesters + impacts/count-active · evaluation lifecycle + 404-masking +
ratings/comments + rubric seed/lock + lazy `computeAll` results · audit logs · (deferred: admin+import contracts,
Phase E reports, cutover).

**Scaffold:**

```
assemblies/loa-consult-platform/
├── app/Http/Controllers/   # Health, Semester, Academic (Appointment/Availability/Evaluation/Auth = slices B/C)
├── app/Http/Middleware/    # JwtMiddleware (ported ✓ Step 3) + EndpointPolicyMiddleware (stub until Step 5)
├── app/Models/ (9)         # Department, DepartmentCourse, Subject, Section, Student, Employee,
│                           # Semester, FacultySubject, StudentEnrollment
├── routes/api.php          # v1 + health + semesters*8 + admin/*16 (~24 routes)
├── config/                 # stock Laravel (jwt/auth-platform/consult-*.php pending per auth-integration §5)
├── database/migrations/    # cache/jobs + 000001-000009 academic + 000010 audit
├── database/seeders/       # DatabaseSeeder (BROKEN: refs missing App\Models\User)
├── tests/                  # Feature/Api/HealthTest (1/1 green) + empty Unit
├── docker/                 # php/Dockerfile + nginx/default.conf (cert-identical)
├── *.md specs              # all Final (api-endpoints, auth-integration, 3 modules, docker-compose, data-model);
│                           # test-suite + runbooks (Draft)
└── AGENTS.md               # this file
```

**Architecture notes:** Eloquent models stay thin persistence (fillable/casts/relations, `HasUuids`, zero logic);
rules live in FormRequests/controllers/services + model events. Contexts never import each other; both may import
Academic. Identity referenced by JWT claims only — no Auth DB reads. Bare shapes preserved until cutover; no
envelope migration without a spec.

---

## 3. Current status (historical tracking — append newest at bottom)

- **2026-09-18 — Scaffold + specs.** Laravel 12.12 via composer (PHP 8.3 pinned), swagger + phpunit 12,
  `HealthTest` green in Docker. Endpoint inventory: 112 `route.ts` files (~142 combos; 118 migrating + 5 public/SSO).
  `api-endpoints.md` Final v1.0, `auth-integration.md` Final v1.2, module drafts, runbook/test-suite/docker drafts.
- **2026-09-19 — Module finals.** `endpoints-academic/appointments/evaluations.md` Draft → Final v1.0;
  `docker-compose-spec.md` → Final v1.0 (root stack wired, `loa_consult` init, secrets sync, `:9002`).
  Academic slice built (9 tables/models/routes). `data-model.md` v1.0 hybrid claim SUPERSEDED by v1.1 first-class Draft.
- **2026-09-22 — Deferred + governed.** Consult DEFERRED by user; focus Auth + Cert. `AGENTS.md` sole entry (lean merge).
  Trackers reconciled (`TODO.md` / `PROJECT.md` / `PROJECT_UPDATES.md` match files). Boundary decision: ONE assembly,
  two contexts (no split). `student.md` / `faculty.md` / `faculty-loading.md` Final v1.1 (first-class cache via SSO
  upsert, FK to domain IDs).
- **2026-09-22 — Assembly contract created.** This `AGENTS.md` (wise_wallet format); referenced from root `AGENTS.md`.
  Spec format standard adopted (§1.9 analogue).
- **2026-09-22 — Readiness + identity fix.** `consult-readiness.md` Draft v1.0 → **v1.2** (v1.1: false "Laravel assembly
  deferred" removed — assembly active; §3–§4 superseded-by `auth-integration`; §8 Laravel backend. v1.2: §9 no `app_users`
  — first-class students/employees; §10 legacy role source only; §13 cross-repo sync duty removed — normative = assembly
  only). `auth-integration.md` Final v1.2 → **v1.3** (§7/§10 #3 aligned: Auth Platform = sole identity authority;
  Identity Kernel = concepts). Trackers reconciled (TODO / PROJECT / PROJECT_UPDATES; PROJECT_UPDATES + dependency-rules
  consult-deferred markers cleared).
- **2026-09-22 — Port plan filed in spec (user).** `auth-integration.md` v1.3 Final → **v1.4 Draft** (§11 sequenced
  landing added; re-promotion to Final gates code per Rule 0). Trackers updated.
- **2026-09-22 — v1.4 re-Final + Step 1 config.** User approved §11 → Final v1.4. Landed `config/jwt.php` +
  `config/auth-platform.php` (verbatim cert) + `config/consult-platform.php` (tenant_slug=loa, loa_connect_refresh,
  cert-only keys dropped) + `phpunit.xml.dist` test secrets (JWT/encryption/tenant/cookie).
- **Current status (2026-09-22) — Phase 0 + Steps 1–2 complete.** `data-model.md` v1.2 → **Final v1.3**
  (Option A, user-approved): Final = shape ≠ codeable; §3 Status legend + column (6 Implemented / 3 Delta pending /
  17 Specified—not migrated incl. `audit_logs`/`bug_reports`); §7 authority note; no shape changes; §4 pointer updated.
  Step 1 HealthTest green pasted. Step 2 full services trio landed verbatim cert →
  `app/Services/{JWTService,EncryptionService,AuditLogger}.php` (content match; hash diff = CRLF only); HealthTest
  green pasted. Residual: `AuditLogger` uncallable (no model/table; cert org-FK/`source`/`entity_*` vs `data-model`
  L109) — **open at Step 6** (class-C). Trackers reconciled (TODO / PROJECT / PROJECT_UPDATES).
  **Stopped — Step 3 (`JwtMiddleware`) not started; needs user yes.**
- **Current status (2026-09-22) — Step 3 complete.** `JwtMiddleware` stub → cert port (only deltas:
  `consult-platform.tenant_slug` default `loa`, `consult_user` attr); error shapes verbatim (401 missing/invalid,
  403 tenant_mismatch). `tests/Unit/JwtMiddlewareTest.php` — 6 tests (valid passes · 401 missing · invalid ·
  expired · wrong-type · 403 tenant mismatch). **Full suite green pasted.** Routes still ungated (Step 7).
  Trackers updated. **Stopped — Step 4 (`config/consult-endpoints.php` catalog) not started; needs user yes.**
- **Current status (2026-09-22) — Steps 4–5 complete.** Step 4: `config/consult-endpoints.php` generated from
  `api-endpoints.md` §5 (public 5 + catalog 118; static-before-param ordering); Auth JSON counterpart deferred to
  deploy-time per user choice. Step 5: `EndpointPolicyMiddleware` stub → cert port (only delta:
  `consult-endpoints` config re-point; `jwt_claims` attr confirmed at port time; ordinals verbatim).
  `tests/Unit/EndpointPolicyMiddlewareTest.php` — 5 tests (public tokenless pass · 403 closed-by-default ·
  403 insufficient_level · sufficient pass + `jwt_endpoint_level` stored · real catalog 118+5).
  **Full suite green pasted.** Routes still ungated (Step 7). Trackers updated.
  **Stopped — Step 6 (auth trio) not started; needs user yes.**
- **Current status (2026-09-22) — Step 6 landed, green pending.** `AuthCallbackController` (cert + email-domain
  upsert: onmicrosoft→`students`, lyceumalabang→`employees`; name-only refresh; `SSO-` student_number placeholder on
  create; explicit-UUID create since models lack `HasUuids`; unknown domain→no row; audit fail-soft) ·
  `AuthRefreshController` (config re-point) · `AuthLogoutController` (config re-point + env-driven Secure deviation) ·
  `AuditLogger` reshaped to `data-model` L119 (no org/source/entity cols; no model/table yet — gate holds) · public
  `auth/*` trio routes (throttle in Step 7) · `tests/Feature/Api/AuthTrioTest.php` — 16 tests (incl. ≤10-char
  placeholder + unknown-domain no-row asserts; `assertPlainCookie` — api routes skip `EncryptCookies`). Red-triage
  fixes: `000010` fully guarded, `tests/bootstrap.php` pins `loa_consult_test` (phpunit `force` loses to container
  `$_SERVER`). **Full suite green pasted 2026-09-22.** Routes still ungated (Step 7). Trackers updated.
  **Stopped — Step 7 needs user yes.**
- **Current status (2026-09-22) — Step 7 landed, green pending.** `routes/api.php`: `auth/*` public +
  `throttle:10,1` on callback/refresh (cert pattern verbatim) · health + count-active public · semesters/admin
  under `['jwt.auth','jwt.endpoint']` (removed duplicate public `GET /semesters` — catalog rates it gated `read`).
  `tests/Feature/Api/RouteGatingTest.php` — 5 tests (health + count-active public · semesters/admin 401 tokenless ·
  callback reachable with 400). Trackers updated.
  **Full suite green pasted 2026-09-22 — §11 auth-layer port COMPLETE. Stopped — next phase needs user yes.**
- **Current status (2026-09-22) — red-suite triage, fixes landed.** AuthTrio 15 red (unit suites green):
  (A) `000010` sections block dropped never-existing `is_disabled` + re-added existing cols — Type A, fixed to
  `created_by`/`updated_by` only (up + down); (B) suite hit `loa_consult` app DB — phpunit `force` loses to container
  `$_SERVER` — Type B, fixed via `tests/bootstrap.php` (auth precedent, pins `loa_consult_test`) + phpunit bootstrap
  rewire. Dev `loa_consult` was wiped by RefreshDatabase — user recovery + rerun pending.
- **Current status (2026-09-22) — B1 green pasted.** `000011` academic baseline delta (§7: sections course-link +
  program, mappings `semester_id`, enrollments section links; slice-C excluded) + model convergence + 3 structure
  tests. Repairs en route: FK-before-column/unique order + student relink, DDL-builds-on-close guards via
  information_schema, short trio unique (conventional 74 > MySQL 64). Trackers updated.
  **Stopped — B2 (appointment-family tables) needs user yes.**
- **Current status (2026-09-22) — B2 green pasted.** `000012` (§3.2: appointments + slots + attendees +
  files + availability rules) + 5 thin models + 3 structure tests. Trackers updated.
  **Stopped — B3 (availability endpoints) needs user yes.**
- **Current status (2026-09-22) — B3 green pasted.** `AvailabilityRuleController` (groups-claim ownership, forced
  self, ADMIN-other + fail-soft audit, 400 validation) + gated routes + 10 tests. Trackers updated.
  **Stopped — B4 (appointments endpoints) needs user yes.**
- **Current status (2026-09-22) — B4 green pasted.** `AppointmentController` (10 routes, legacy-verified
  conflicts/slots/rules, retry-sync stubbed) + 12 tests. Repairs: route order, single-level perms, staff-only
  creator rule, employees-only attendees. Trackers updated.
  **Stopped — B5 (academic hardening) needs user yes.**
- **Current status (2026-09-22) — B5 green pasted, slice B COMPLETE.** Academic hardening (409s, quirks,
  camelCase, no-change 400s, fail-soft audit) + `000013` + per-semester impacts + hardening tests. Trackers updated.
  **Stopped — slice C (evaluations) needs user yes.**
- **Current status (2026-09-22) — C1 green pasted.** `000014` (10 evaluation tables per §3.3/§7 order) + 10 thin
  models + 4 structure tests. Trackers updated.
  **Stopped — C2 (periods/rubrics endpoints) needs user yes.**
- **Current status (2026-09-22) — C2 green pasted.** Periods (CRUD + activate/reset/snapshot/items-quirk) +
  rubric groups (seed/lock 409s, duplicate, snapshot, categories) + 16 tests. Trackers updated.
  **Stopped — C3 (evaluations lifecycle) needs user yes.**
- **Current status (2026-09-22) — C3 green pasted.** Evaluations lifecycle (masking, get-or-create, pending,
  bootstrap, dispute, comments) + flow tests. Trackers updated.
  **Stopped — C4 (results/computeAll) needs user yes.**
- **Current status (2026-09-22) — C4 green pasted, slice C COMPLETE.** ResultsService (computeAll, lazy,
  visibility, camelCase aggregates) + 20 result routes + tests; reassign/enrollment side effects wired. Trackers
  updated. **Stopped — next phase needs user yes.**
- **Current status (2026-09-23) — trackers reconciled + test-suite Final v1.0.** TODO boxes (Auth/B/C) ticked COMPLETE, auth row Steps 1–7, PROJECT Phase 2 B/C Done, UPDATES Consult Status/Next current. `test-suite.md` Draft v0.1 → Final v1.0 (§1.9 template, CON/ACC/D, user-approved). Trackers updated.
  **Stopped — next spec or phase needs user yes.**

---

## 4. Specs (pointer only)

**Normative text lives in the assembly `*.md` files; implement exactly those. This section is a pointer only.**

| Spec | Status |
|---|---|
| `api-endpoints.md` v1.0 | FINAL — 118 routes, levels, ground truth |
| `auth-integration.md` v1.4 | FINAL — SSO/JWT/middleware/provisioning + §11 port plan (Steps 1–7 ✓ suite green 2026-09-22, port COMPLETE) |
| `endpoints-academic/appointments/evaluations.md` v1.0 | FINAL — module contracts |
| `docker-compose-spec.md` v1.0 | FINAL — root-stack wiring `:9002` |
| `data-model.md` v1.3 | FINAL — shape contract (source-verified M17/M19/M21/M26/M27/M30/M31); §3 Status column gates implementability (6 Implemented / 3 Delta pending / 17 Specified—not migrated); §7 baseline delta |
| `test-suite.md` v1.0 | FINAL — auth + B/C contract (CON/ACC/D), user-run sequential |
| `LOCAL-DEV-RUNBOOK.md` v0.1 | DRAFT — local dev setup |
| `DEPLOY.md` v0.1 | DRAFT — deployment skeleton |
| `FRONTEND-INTEGRATION.md` v0.1 | DRAFT — cutover checklist skeleton |
| `consult-readiness.md` v1.2 | DRAFT — historical Next.js SSO notes; Auth-provisioning checklist only |
| `README.md` v1.0 | DRAFT — assembly composition (see §2) |
