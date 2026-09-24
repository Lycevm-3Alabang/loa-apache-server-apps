# TODO — Consult Platform Spec Program

> **ACTIVE 2026-09-24** — Consult spec program resumed (spec-first); Auth + Cert deferred (working, no changes). Reconciled 2026-09-24: SPEC STATUS matches assembly Finals (104+5, tenant `loa-consultation`); Phase D deferred markers superseded by Final v1.1 + baselines green. Boundary decision: ONE assembly, two contexts (Consultation + Evaluation, no split).

**Updated:** 2026-09-24
**Scope:** `assemblies/loa-consult-platform/` specs, with Auth + Cert as reference/basis.
**Rule:** No implementation code until the relevant spec `.md` file is Final (AGENTS.md Rule 0).

---

## NOW (closed — all checked 2026-09-23)

- [x] 2026-09-19 — Academic slice partial — migrations 000001-000009 (departments, department_courses, subjects, sections, students, employees, semesters, faculty_subjects, student_enrollments) + 000010 audit → 9 models → `AcademicController` + `SemesterController` → ~24 routes (no auth middleware yet; stubs pass-through)

- [x] Auth layer — middleware + controllers per `auth-integration.md` Final v1.4 §10–§11 (SSO callback/refresh/logout, `jwt.auth`/`jwt.endpoint`) — Steps 1–7 landed (config · services · JwtMiddleware · catalog · EndpointPolicy · auth trio · gate routes); **full suite green pasted 2026-09-22 — auth-layer port COMPLETE per §11**

- [x] 2026-09-22 — `data-model.md` Final v1.2 → **Final v1.3** (Option A status split, user-approved): Final = shape contract only; §3 Status legend + column (6 Implemented / 3 Delta pending / 17 Specified—not migrated, incl. `audit_logs`/`bug_reports`); §7 implementability authority; no shape changes

---

## AFTER FINAL SPECS (implementation gates)

- [x] Domain slice B — appointments/academic/semesters — **COMPLETE green pasted** (B1 deltas + B2 tables + B3 availability + B4 appointments + B5 academic hardening)

- [x] Domain slice C — evaluations/periods/rubrics/results — **COMPLETE green pasted** (C1 tables + C2 periods/rubrics + C3 lifecycle + C4 results/compute)

- [x] 2026-09-23 — Implementation phase planning **FLUSHED (user-approved draft)**: P0 scaffold done (`docker-compose-spec.md` Final v1.0) · P1 C-Auth done (`auth-integration.md` Final v1.4 §11 Steps 1–7: Jwt 6 + Policy 5 + Trio 16 + Gating 5 green) · P2 Slice B done (B1 `000011` + B2 `000012` 5 models + B3 2 routes 10/10 + B4 10 routes 12/12 + B5 `000013` hardening; semesters 7+1) · P3 Slice C done (C1 `000014` 10 tables/models 4/4 + C2 periods 12 + rubrics 12 + C3 lifecycle 12 + C4 `ResultsService` + 15 scoped results; C2 7 methods green, 16-claim fixed) · P4 Slice D baselines green (link-reads 3 + import 10 + data 4 served, audit-logs 2 pending by gate; config audit-logs flattened fixed) · P5 Flatten F1/F2 done (104+5 normative; full suite **120 passed green 2026-09-23**) · **P6 PARKED 2026-09-23 — get back later**: runbook promotions + DEC-5 + Phase E + §8 provisioning (see PLANNED). Cross-gates: `TENANT_SLUG=loa`, secrets identical, `.htaccess` Authorization, bare shapes, no local roles, sequential `loa_consult_test`, HealthTest green + user paste

---

## PLANNED (deferred)

- [x] SUPERSEDED 2026-09-23 — Admin+import module (was DEFERRED 2026-09-18): contract landed as `endpoints-admin-import.md` Final v1.1 + Phase D baselines green 2026-09-23 (link-reads / import-domain / data-audit); consult holds link-reads only, user writes Auth-owned per DEC-1/DEC-2

- [x] SUPERSEDED 2026-09-23 — Phase D spec slice (was DEFERRED with admin+import above): `endpoints-admin-import.md` Final v1.1 covers admin-users + import + data-audit; `/service/*` proxy decision still OPEN per DEC-5 (no proxy code until recorded)

- [ ] PARKED 2026-09-23 — get back later — Phase E: reports as Laravel API + cutover (frontend rewrite decision: Vercel rewrite vs direct+CORS)

- [ ] PARKED 2026-09-23 — get back later — Auth provisioning per `auth-integration.md` §8 at deploy time (tenant, groups, catalog import, grants, secrets)

- [x] COMPLETE 2026-09-24 — Runbook promotions to Final (`LOCAL-DEV-RUNBOOK.md` / `DEPLOY.md` / `FRONTEND-INTEGRATION.md` v0.1 Draft → Final v1.0)

- [ ] PARKED 2026-09-23 — get back later — DEC-5 `/service/*` proxy decision (record before any proxy code)

---

## SPEC STATUS (consult platform)

| Spec | Version | Status | Blocks |
|------|---------|--------|--------|
| `api-endpoints.md` | v2.1 | **Final** | flat 104+5 root spec (F1/F2 green); tenant `loa-consultation` (own-tenant cert pattern, user-approved 2026-09-24); v2.0 `loa` in git history |
| `auth-integration.md` | v1.5 | **Final** | §11 port plan; Steps 1–7 landed — full suite green 2026-09-22, port COMPLETE; tenant `loa-consultation` (user-approved 2026-09-24) |
| `data-model.md` | v1.3 | **Final** | — (shape contract; §3 Status gates implementability: 6 Implemented / 3 Delta pending / 17 Specified—not migrated; source-verified M17/M19/M21/M26/M27/M30/M31) |
| `endpoints-academic.md` | v1.1 | **Final** | flat paths (green) |
| `endpoints-appointments.md` | v1.0 | **Final** | — (build-time carry-forward: actor-ownership, Teams sync) |
| `endpoints-evaluations.md` | v1.1 | **Final** | flat paths + scoped results (green) |
| `endpoints-admin-import.md` | v1.1 | **Final** | Phase D contract (v2.0 pointers); baselines green 2026-09-23 (link-reads / import-domain / data-audit); DEC-5 `/service/*` proxy still OPEN |
| `url-flattening.md` | v1.1 | **Final** | Flat URL scheme (104+5 actual per ACC-2 + `api-endpoints.md` v2.0 §5 Total; 113+5 was F2 intermediate, superseded; single scoped results, cert/auth deny, in-place v1 rename) |
| `test-suite.md` | v1.2 | **Final** | test contract for auth-layer + B/C (CON/DEC/ACC/D); user-run sequential; v2.1 pointers; tenant `loa-consultation` |
| `docker-compose-spec.md` | v1.1 | **Final** | — (root stack wiring: §2 blocks + §3 init + §4 secrets + §7 scripts; tenant `loa-consultation`) |
| `LOCAL-DEV-RUNBOOK.md` | v1.0 | **Final** | local dev setup (wired + green 2026-09-23) |
| `DEPLOY.md` | v1.0 | **Final** | deployment (cert-patterned, deploys on approval) |
| `FRONTEND-INTEGRATION.md` | v1.0 | **Final** | cutover phase (backend green; topology OPEN until cutover) |
| `consult-readiness.md` | v1.6 | **Final** | provisioning checklist (aces-* + linkage, pointer-only 104+5, v2.1 pointers, tenant `loa-consultation`) |
| `AGENTS.md` (assembly contract) | v1.0 | **Final** | — (working agreements, scaffold, status, spec pointers) |
| `student.md` / `faculty.md` / `faculty-loading.md` (education domains) | v1.1 | **Final** | — (first-class cache via SSO upsert, FK to domain IDs; unblock data-model Final) |

---

## DONE

- [x] 2026-09-24 — Runbook promotions to Final (user-approved): `LOCAL-DEV-RUNBOOK.md` v0.1 → **v1.0** (wiring gate MET, no-seed, test-DB pin), `DEPLOY.md` v0.1 → **v1.0** (cert-patterned cPanel guide: PHP 8.3, dist, no-seed migrate, slug checklist, cron), `FRONTEND-INTEGRATION.md` v0.1 → **v1.0** (backend-done state, topology OPEN until cutover); consult spec program now 100% Final (P6 left: DEC-5 + Phase E + §8)

- [x] 2026-09-24 — Tenant rename `loa` → `loa-consultation` (user-approved, own-tenant cert pattern): `api-endpoints.md` Final v2.0 → **v2.1**, `auth-integration.md` Final v1.4 → **v1.5**, `consult-readiness.md` Final v1.5 → **v1.6**, `test-suite.md` Final v1.1 → **v1.2**, `docker-compose-spec.md` Final v1.0 → **v1.1**; code/config/tests/runbooks/AGENTS updated (3 middleware-config defaults, phpunit+bootstrap, 11 test files, `.env.example`, DEPLOY/FRONTEND-INTEGRATION); pending (user runs): real `.env` slug, Auth re-provisioning (`loa-consultation` tenant + aces-* + catalog/grants), suite re-green paste

- [x] 2026-09-23 — `mega.ps1` re-run green (user-reported): tests green → dumps in progress (auth zipped, cert in progress, consult queued) → **all 3 zipped successfully**.

- [x] 2026-09-23 — Cert `test_pdf_allows_admin` 500 fixed (user-approved): `PdfService::streamCertificatePdf()` called non-existent `$pdf->inline()` (installed barryvdh exposes `stream/download/output/save` only) → one-line fix to `$pdf->stream()`; probe added + reverted byte-identical. **Full cert suite green: 249 passed (738 assertions)**

- [x] 2026-09-23 — Consult wired into Docker pipelines (user-requested, per `docker-compose-spec.md` Final §7): new `assemblies/loa-consult-platform/generate-dist.ps1` (cert twin + `_stage` exclusion) · `scripts/build-all.ps1` + consult in `$apps` · `scripts/reset-all.ps1` + consult cache/migrate/swagger steps (NO seed — none per spec §7) · `scripts/run-tests.ps1` + consult block (`php artisan test`, `loa_consult_test` ensure, `-ConsultFilter`) · `dump.ps1` `-Target consult` (dist + `loa_consult` SQL, skips auth/cert-only passes) · `mega.ps1` Step 5 Dump Consult (6 steps total). Auth/cert blocks untouched; root compose + init.sql already wired. 2026-09-23 red-triage (Type A): consult `l5-swagger:generate` exits 1 — app/ has zero OpenApi attributes (no `#[OA\Info]`; cert keeps its Info in `CertificateTemplateController`) — reset-all consult swagger made best-effort (warn + continue; `$global:LASTEXITCODE` scoping fix after bare assignment failed to propagate to mega). 2026-09-23 red-triage (Type C gap): `init.sql` created `loa_consult` but not `loa_consult_test`, so direct `migrate → php artisan test` failed on fresh volumes (user created it by hand) — added `loa_consult_test` + grant to `init.sql` (additive; existing volumes unaffected, `run-tests.ps1` ensure already covered test-time). 2026-09-23 1044 episode closed: `loa`@`%` grant on `loa_consult_test` verified live (PDO connect OK; single mysql on network) + **consult suite re-green: 120 passed (594 assertions)**. Note: cert effectively tests against `loa_cert` app DB (no `$_SERVER`-pinning bootstrap like consult's `tests/bootstrap.php`) — cert-deferred, untouched. **Pipeline COMPLETE green (user-reported 2026-09-23): `.\mega.ps1` end-to-end (reset → auth+cert+consult tests → 3 dumps).**

- [x] 2026-09-23 — Spec-owned drift fixed (user-approved): assembly `AGENTS.md` §4 113+5 → 104+5 per ACC-2 · `url-flattening.md` Doc Control 113+5 → 104+5 (113+5 = F2 intermediate) · `endpoints-admin-import.md` Refs config 118 → 104 gated flattened · `config/consult-endpoints.php` L131-132 `/admin/audit-logs` → `/audit-logs` per DEC-1 (flat). **Full suite green pasted 2026-09-23: 120 passed (594 assertions)** incl Policy `real catalog has 104 gated plus 5 public`; migrations 000001–000014 DONE
- [x] 2026-09-23 — Tracker reconciliation (user-approved): header ACTIVE/Updated → 2026-09-23; Phase D deferred markers → SUPERSEDED (Final v1.1 + baselines green); counts → **104+5** normative (`api-endpoints.md` v2.0 §5 Total + `url-flattening.md` ACC-2 + `consult-readiness.md` CON-2 + `config/consult-endpoints.php` 104 gated); 118+5 = v1.0 pre-flatten history, 113+5 = F2 intermediate. Open drift list below RESOLVED 2026-09-23 by the drift-fix entry above (AGENTS §4, url-flattening Doc Control, admin-import Refs, config audit-logs flat; suite re-green 120).
- [x] 2026-09-23 — Phase D link-reads baseline **green (user)**: `UserLinkController` (primary/attendees/related-data via email link, no Auth fetch) + 3 gated read routes + `UserLinkTest` 8/8; writes absent by design (DEC-1); catalog §5.3 writes drift open (Type C)
- [x] 2026-09-23 — Phase D import-domain baseline **green (user)**: `ImportController` (preview dry-run + natural-key idempotent upserts + references) + 10 gated routes + `ImportTest` 7/7; red-triage fixes (Type A): explicit UUID for students/employees (no HasUuids), `student_number` required on create; `import/users/reference` absent by design (Auth duty)
- [x] 2026-09-23 — Phase D data/audit baseline **green (user)**: `DataController` (delete-students + reset-db double-guarded, export-consultations, evaluation-mappings) + 4 gated routes + `DataAuditTest` 8/8; per-resource DELETE stays on owner controllers, bulk multi-entity only in `DataController` (kept per user); `audit-logs` absent by gate (no table)
- [x] 2026-09-23 — Auth-side consult catalog **authored (user-requested)**: `assemblies/loa-auth-platform/database/json/consult-endpoints-catalog.json` v1.0 (118 entries, transcribed from consult `api-endpoints.md` Final v1.0 §5) as bulk-import path for deploy-time provisioning; unserved paths included verbatim (grants decide; 404/closed-by-default until served)
- [x] 2026-09-23 — URL flattening F1 **green (user)**: non-results paths flat (academic 15, related-data, data 3, disabled/details/invalidate 5, bootstrap relocated above `{id}`); catalog + 5 test files updated path-only; results collapse deferred to F2
- [x] 2026-09-23 — URL flattening F2 **green (user)**: results 20 → 15 single scoped surface (group-switch index, own-dept/self guards, aces-* checks, 6 dean methods removed) + `ResultsTest` rewrite with scoping test + catalog/JSON regen (reported 113+5 at F2; reconciled 2026-09-23 to **104+5** per `api-endpoints.md` v2.0 §5 Total + `url-flattening.md` ACC-2) + url note re-Final v1.1; red-triage: disabled-set route shadowing (`{id}` registered first — statics moved above), policy count 118→104 gated (flattened current)
- [x] 2026-09-22 — Red-suite triage (AuthTrio 15 red, suite otherwise green): (A) 000010 sections block dropped never-existing `is_disabled` + re-added existing cols — Type A, fixed to `created_by`/`updated_by` only incl. down(); (B) suite ran against `loa_consult` app DB — phpunit `force` loses to container `$_SERVER` (Type B test-infra, fixed via `tests/bootstrap.php` auth-precedent + phpunit bootstrap rewire). Dev `loa_consult` wiped by RefreshDatabase — user recovery + rerun pending
- [x] 2026-09-22 — B1 **green pasted**: `000011` academic baseline delta + model convergence + `AcademicDeltaTest`; repairs en route: sections FK-drop, guarded idempotence (information_schema checks — DDL builds on closure close), FK-before-unique order + student relink, short trio unique `enr_stu_map_sem_unique` (74-char conventional > MySQL 64)
- [x] 2026-09-22 — B3 **green pasted**: `AvailabilityRuleController` + gated routes + `AvailabilityTest` 10/10
- [x] 2026-09-22 — B4 **green pasted**: `AppointmentController` (10 routes, legacy-verified conflicts/slots/rules) + `AppointmentTest` 12/12; repairs: route order (specific before `{action}`), single-level test perms, creator≠student staff-only, employees-only attendees
- [x] 2026-09-22 — B5 **green pasted**: academic hardening to module Final + `000013` section-link carry-forward + per-semester impacts + `AcademicHardeningTest`
- [x] 2026-09-22 — C1 **green pasted**: `000014` evaluation tables + 10 thin models + `EvaluationTablesTest` 4/4
- [x] 2026-09-22 — C2 **green pasted**: periods + rubric-groups endpoints + `PeriodsRubricsTest` (one Type-B test-typo fix en route)
- [x] 2026-09-22 — C3 **green pasted**: evaluations lifecycle + `EvaluationFlowTest` (one Type-B seed-unique fix en route)
- [x] 2026-09-22 — C4 **green pasted**: results service + 20 routes + `ResultsTest`; repairs: restore test perm, AuditLogger catchable guard

- [x] 2026-09-22 — **Step 7** gate routes **full suite green pasted — §11 auth-layer port COMPLETE**: `auth/*` public + `throttle:10,1` on callback/refresh (cert pattern) · health + count-active public · semesters/admin under `['jwt.auth','jwt.endpoint']` (removed duplicate public `GET /semesters`) · `tests/Feature/Api/RouteGatingTest.php` 5 tests (public stays public · gated 401 tokenless · callback reachable)
- [x] 2026-09-22 — **Step 6** auth trio landed **full suite green pasted** (`AuthCallbackController` + email-domain upsert + `SSO-` placeholder ≤10 chars + fail-soft audit · refresh/logout · `AuditLogger` L119 reshape · public `auth/*` trio · `AuthTrioTest` 16 tests; red-triage fixes: `000010` guarded, `tests/bootstrap.php` test-DB pin)
- [x] 2026-09-22 — **Step 5** `EndpointPolicyMiddleware` stub → cert port (config re-point `cert-endpoints` → `consult-endpoints` only; `jwt_claims` attr confirmed at port time; ordinals read1/write2/admin3/deny-1 verbatim); `tests/Unit/EndpointPolicyMiddlewareTest.php` 5 tests (public tokenless pass / 403 closed-by-default / 403 insufficient_level / sufficient pass+level stored / real catalog 118+5) — **full suite green pasted**
- [x] 2026-09-22 — **Step 4** `config/consult-endpoints.php` generated from `api-endpoints.md` Final v1.0 §5: public 5 (health, count-active, auth trio) + catalog 118 gated rows; Auth JSON counterpart deferred to deploy-time per user choice (no Auth spec/files now)
- [x] 2026-09-22 — **Step 3** `JwtMiddleware` stub → cert port (deltas only: `consult-platform.tenant_slug=loa`, `consult_user` attr); error shapes verbatim; `tests/Unit/JwtMiddlewareTest.php` 6 tests (valid / 401 missing / invalid / expired / wrong-type / 403 tenant_mismatch) — **full suite green pasted**
- [x] 2026-09-22 — `tests/Unit/JwtMiddlewareTest.php` written with Step 3 (covered in suite-green paste above)
- [x] 2026-09-22 — **Phase 1 closed:** Step 1 HealthTest green pasted (config trio verified)
- [x] 2026-09-22 — **Phase 2** Step 2 **services trio** landed verbatim cert → consult: `app/Services/{JWTService,EncryptionService,AuditLogger}.php` — HealthTest green pasted. Residual: `AuditLogger` uncallable (no `App\Models\AuditLog`, no `audit_logs` table; cert `organization_id` vs `data-model` L109 shape) — **open at Step 6**, not Step 2
- [x] 2026-09-22 — **Phase 0** `data-model.md` v1.2 → **Final v1.3** (Option A, user-approved “okay”): Status banner/legend/column; 6/3/17 split; §7 authority note; assembly AGENTS §4 pointer updated; no shape changes; trackers reconciled
- [x] 2026-09-22 — `auth-integration.md` §11 approved → **Final v1.4**; **Step 1 config trio** landed (`jwt.php`, `auth-platform.php` verbatim; `consult-platform.php` renamed keys; `phpunit.xml.dist` test secrets added)
- [x] 2026-09-22 — Port plan filed in spec (user): `auth-integration.md` v1.3 Final → **v1.4 Draft** (§11 sequenced landing added); re-promotion to Final gates code per Rule 0
- [x] 2026-09-22 — Review pass: fixed internal `auth-integration.md` version refs (consult-readiness §1, LOCAL-DEV-RUNBOOK, FRONTEND-INTEGRATION → v1.3; runbook data-model → Final v1.2); cleared education-domain deferred marker
- [x] 2026-09-22 — Identity ownership decision (user): students/employees correct; `app_users` mirrored/duplicated Auth + Identity Kernel → Classification C. `auth-integration.md` Final v1.2 → **v1.3** (§7/§10 #3: first-class upsert; Auth Platform = sole identity authority); `consult-readiness.md` Draft v1.1 → **v1.2** (§9 rewrite, §10 legacy-clarify, §13 cross-repo sync removed — normative = assembly only); `dependency-rules.md` consult-deferred markers cleared; trackers reconciled
- [x] 2026-09-22 — `consult-readiness.md` Draft v1.0 → **v1.1** (removed false "Laravel assembly is deferred" claim → assembly active; §3–§4 superseded-by banners → `auth-integration.md` Final v1.2; §8 table: Laravel backend, cPanel deploy, local `loa_consult` store)
- [x] 2026-09-22 — `assemblies/loa-consult-platform/AGENTS.md` created (wise_wallet format: 10 working agreements, scaffold, status journal, spec pointer) + referenced from root `AGENTS.md` (exclusive standing contract for consult work)
- [x] 2026-09-22 — Spec format standard adopted (wise_wallet `06-web-warning-cleanup` shape: metadata + RFC 2119 + Context/Constraints CON-*/Goal DEC-*/ACC-*/Deliverables D-* + Glossary + References); applies to all future consult specs
- [x] 2026-09-22 — `student.md` / `faculty.md` / `faculty-loading.md` Draft v1.0 → **Final v1.1** (`name`/`email` first-class cache via SSO upsert, Auth promotion optional; loading refs `employees.id` FK, never opaque sub; resolves G6/G7)
- [x] 2026-09-22 — Boundary decision (ONE assembly, two contexts, no split): no consultation↔evaluation FK, shared academic actors + Auth claims only, events-only correlation; spec hardening list filed (README diagram, Owner column, no-cross-imports, joint history contract)
- [x] 2026-09-22 — Governance re-check (`AGENTS.md` ↔ `principles.md` ↔ `platform.md`): 8 Type-C findings filed (G1 layer names, G2 automotive template, G3 comms vocab, G4 missing Correlation, G5 stale deferred, G6 app_users, G7 opaque-sub, G8 subject typo)
- [x] 2026-09-22 — Trackers reconciled: `TODO.md` SPEC STATUS + DONE (struck void `app_users` + `data-model` Final claims), `PROJECT.md` Phase 2 + assembly row, `PROJECT_UPDATES.md` Consult section
- [x] 2026-09-22 — Auth + Cert spec-vs-code audits (read-only, Type A/B/C filed; Auth 10A/6C, Cert 7A/4B/7C) — DEFERRED after, no implementation
- [ ] SUPERSEDED 2026-09-22 — `app_users` claim void: no app_users file in database/migrations/ (12 files), grep app_users|AppUser = 0 hits; v1.1 uses students/employees first-class. HealthTest green = health only.
- [x] 2026-09-19 — `docker-compose-spec.md` Draft v0.1 → **Final v1.0** (verified root stack auth+cert already wired; corrected init.sql §3, added §4 shared secrets sync, §5 port map, §7 scripts reset-all.ps1 update; consult .env `ENCRYPTION_KEY` fixed to match auth)
- [ ] SUPERSEDED 2026-09-22 — `data-model.md` v1.0 hybrid void; actual file is Draft v1.1 first-class (students/employees, no app_users cache) pending Final.
- [x] 2026-09-19 — `endpoints-academic.md` Draft v0.1 → **Final v1.0** (verified vs data-model v1.0 + api-endpoints §5.4/§5.5; levels match; needs re-verify vs data-model v1.1 first-class on resume)
- [x] 2026-09-19 — `endpoints-appointments.md` Draft v0.1 → **Final v1.0** (verified vs §5.1/§5.2; no level corrections; needs re-verify vs data-model v1.1 first-class on resume)
- [x] 2026-09-19 — `endpoints-evaluations.md` Draft v0.1 → **Final v1.0** (verified vs §5.6–§5.9; levels match; needs re-verify vs data-model v1.1 first-class on resume)
- [x] 2026-09-18 — Consult endpoint inventory: 112 `route.ts` files (~142 combos; 118 migrating to Laravel + 5 public/SSO), drift vs `endpoint-catalog.md` recorded, frontend untouched
- [x] 2026-09-18 — `api-endpoints.md` **Final v1.0** (conventions, 118-route summary, levels, scoping from handlers, design decisions, 3 modules, #9/#10 resolved)
- [x] 2026-09-18 — `auth-integration.md` **Final v1.2** (SSO contract verbatim from cert controllers, cookie reality, §10 14-item port inventory, group-wording)
- [x] 2026-09-18 — `data-model.md` Draft v0.1 → **v0.2** (hybrid users approach: cache table, no FK relationships; all user-ref columns are plain TEXT `// opaque Auth sub`; eliminates circular FK; users upserted from JWT on login, admin-written profile fields via import/management endpoints)
- [x] 2026-09-18 — `endpoints-academic.md` Draft v0.1 (14 handlers: departments/courses/subjects/sections/mappings/enrollments/semesters with contracts, audit vocabulary, deletion policy, reassign/fix-names/impacts routines); parent levels corrected (academic POST/PATCH + impacts → `admin`)
- [x] 2026-09-18 — `endpoints-appointments.md` Draft v0.1 (12 combos: booking model, batch, action dispatch, slot links, files owner rule, availability self/other rules); no level corrections needed
- [x] 2026-09-18 — `endpoints-evaluations.md` Draft v0.1 (lifecycle/masking, student flows, periods, rubric editor + seed/lock, results aggregation family, disabled set, dispute mail); parent levels corrected (periods/rubrics mutations → `admin`, rubric-copy → `read`)
- [x] 2026-09-18 — `test-suite.md` Draft v0.1 (cert pattern: MySQL `loa_consult_test`, JWT helper, coverage per module, user-runs-tests rule)
- [x] 2026-09-18 — `docker-compose-spec.md` Draft v0.1 (root-stack `consult-*` blocks port 9002, `loa_consult` init, rollout + acceptance; blocked on first migration)
- [x] 2026-09-18 — `LOCAL-DEV-RUNBOOK.md` Draft v0.1 (shared root-stack pattern, port 9002, wiring gate + checklist)
- [x] 2026-09-18 — `DEPLOY.md` + `FRONTEND-INTEGRATION.md` Draft v0.1 skeletons
- [x] 2026-09-18 — Scaffold via artisan-in-Docker: real Laravel 12.12 tree, swagger + phpunit 12, platform pinned PHP 8.3 (downgraded symfony 8.x), staged files layered back, `HealthTest` green in container
- [x] 2026-09-18 — Cert spec-vs-code audit + alignment: `api-endpoints.md` v1.8, `authenticated-endpoints-spec.md` v1.2, `auth-proxy.md` (11 routes)
- [x] 2026-09-18 — Resolved spec §7 #9/#10: levels corrected; no local admin/role in Consult; DEAN access is a deploy-time Auth grant
- [x] 2026-09-18 — Frontend usage scan + redundancy audit; dormant watchlist recorded
- [x] 2026-09-18 — Trackers: `PROJECT.md` + `PROJECT_UPDATES.md` updated; delivery split into independent Auth / Cert / Consult tracks

---

## Reference basis (read-only for Consult)

- Auth: `tenant-group-endpoint-grants.md` v1.1, `tenant-endpoint-catalog.md` v3.2, `tenant-app-api.md`, `unified-auth-flow.md`
- Cert: `api-endpoints.md` v1.8 (verified implementation), `auth-proxy.md`, middleware + auth controllers

---

## DEFERRED IMPROVEMENTS (cross-platform — filed during 2026-09-22 mirror passes, not scheduled)

### Cert (`assemblies/loa-cert-platform/`)
- [ ] Stop returning `refresh_token` in callback + refresh bodies (cookie-only); migrate callers first (`api-endpoints.md` §§9.3/9.7)
- [ ] Logout `Secure` from `cert-platform.refresh_cookie_secure` (§9.8)
- [ ] Owner rule on `jwt_endpoint_level === 'admin'` instead of group check (§9.6)
- [ ] Remove `recipient_email` from public `verify` + fix `PublicCertificateTest` (§5.6)
- [ ] Composite unique `(event_id, recipient_email, active)` via generated column (`certificate-rules-spec.md`)
- [ ] Align `.user.ini` to 10M (`body-size-limits.md`, DEPLOY.md)
- [ ] `permissions:sync-cert-catalog` artisan command (§9.5)
- [ ] `JwtMiddlewareTest` slug → `loa-e-cert`; add QR-spec-path + owner-negative tests
- [ ] Verify QR `read` grant matching for path-param route (`config/cert-endpoints.php`, `WithJwt.php`)
- [ ] e-cert repo `legacy-e-cert-integration.md` copy still shows query QR path (different repo)
- [ ] Backfill `data-model.md` from migrations (proposed; pure transcription)

### Auth (`assemblies/loa-auth-platform/`)
- [ ] Enforce I1 422 guard in `AuthorizationService::addToGroup()` (M7/Q1)
- [ ] `POST /admin/users/{id}/platform-permissions` + toggle panel (§12.3)
- [ ] `POST /admin/users/{id}/sessions/invalidate` + controller (`admin-dashboard.md`)
- [ ] Strip `password`/`status` from create form (`user-account-activation.md` §10.1)
- [ ] Delete dead `showRegister`/`register` methods (§8.1)
- [ ] Unify `deny` handling (store vs delete on import)
- [ ] `tenant_id` nullable migration for platform-wide grants/overrides
- [ ] Maximize level on priority ties (`PermissionPolicyService`)
- [ ] Tenant-create `group_id` optional (`auth-tenant.md` §5)
- [ ] Accept `"none"` in import validator or remove §5.4
- [ ] Unify `Activation` (24h) vs `PasswordSetToken` (48h)
- [ ] Random hashed placeholder instead of empty-string (`auth-tenant.md` §5.4)
- [ ] Rewrite `web-ui.md` §§3/4.1 against unified pipeline
- [ ] Cross-platform ordinal review (admin-top adopted 2026-09-22)
- [ ] Tests on sqlite `:memory:` — consider docker MySQL (`loa_auth_test`) for cert/consult parity; NOT now (working, do not touch — noted 2026-09-24)

### Consult + shared
- [x] SUPERSEDED 2026-09-23 — Slices B/C + auth port + `test-suite.md` promotion: all COMPLETE (B/C green, §11 port COMPLETE, `test-suite.md` Final v1.1)
- [ ] Mail service extraction (spec `services/mail/README.md` Final v1.0 — unblocked, unscheduled)
- [ ] `decisions/` — adopt (ADR-005+) or delete + prune `platform.md` refs (open)
