# TODO — Consult Platform Spec Program

> **ACTIVE 2026-09-22** — Consult spec program resumed (spec-first); Auth + Cert deferred (working, no changes). Staleness resolved 2026-09-22: SPEC STATUS reconciled against assembly files. Boundary decision: ONE assembly, two contexts (Consultation + Evaluation, no split).

**Updated:** 2026-09-22
**Scope:** `assemblies/loa-consult-platform/` specs, with Auth + Cert as reference/basis.
**Rule:** No implementation code until the relevant spec `.md` file is Final (AGENTS.md Rule 0).

---

## NOW (in progress)

- [x] 2026-09-19 — Academic slice partial — migrations 000001-000009 (departments, department_courses, subjects, sections, students, employees, semesters, faculty_subjects, student_enrollments) + 000010 audit → 9 models → `AcademicController` + `SemesterController` → ~24 routes (no auth middleware yet; stubs pass-through)

- [x] Auth layer — middleware + controllers per `auth-integration.md` Final v1.4 §10–§11 (SSO callback/refresh/logout, `jwt.auth`/`jwt.endpoint`) — Steps 1–7 landed (config · services · JwtMiddleware · catalog · EndpointPolicy · auth trio · gate routes); **full suite green pasted 2026-09-22 — auth-layer port COMPLETE per §11**

- [x] 2026-09-22 — `data-model.md` Final v1.2 → **Final v1.3** (Option A status split, user-approved): Final = shape contract only; §3 Status legend + column (6 Implemented / 3 Delta pending / 17 Specified—not migrated, incl. `audit_logs`/`bug_reports`); §7 implementability authority; no shape changes

---

## AFTER FINAL SPECS (implementation gates)

- [x] Domain slice B — appointments/academic/semesters — **COMPLETE green pasted** (B1 deltas + B2 tables + B3 availability + B4 appointments + B5 academic hardening)

- [x] Domain slice C — evaluations/periods/rubrics/results — **COMPLETE green pasted** (C1 tables + C2 periods/rubrics + C3 lifecycle + C4 results/compute)

- [ ] Implementation phase planning (scaffold, domain slices, C-Auth, cutover) — flush out when all specs are Final

---

## PLANNED (deferred)

- [ ] Admin+import module (DEFERRED 2026-09-18 — enforcement, Auth-owned; contract at implementation phase)

- [ ] Phase D spec slice: admin-users + import + data-audit; `/service/*` proxy decision (admin module spec) — DEFERRED with admin+import module above

- [ ] Phase E: reports as Laravel API + cutover (frontend rewrite decision: Vercel rewrite vs direct+CORS)

- [ ] Auth provisioning per `auth-integration.md` §8 at deploy time (tenant, groups, catalog import, grants, secrets)

---

## SPEC STATUS (consult platform)

| Spec | Version | Status | Blocks |
|------|---------|--------|--------|
| `api-endpoints.md` | v1.0 | **Final** | — (root spec) |
| `auth-integration.md` | v1.4 | **Final** | §11 port plan; Steps 1–7 landed (config · services trio · JwtMiddleware · catalog 118+5 · EndpointPolicy · auth trio · gate routes) — full suite green 2026-09-22, port COMPLETE |
| `data-model.md` | v1.3 | **Final** | — (shape contract; §3 Status gates implementability: 6 Implemented / 3 Delta pending / 17 Specified—not migrated; source-verified M17/M19/M21/M26/M27/M30/M31) |
| `endpoints-academic.md` | v1.0 | **Final** | — (build-time carry-forward: repo-only fields, join paths) |
| `endpoints-appointments.md` | v1.0 | **Final** | — (build-time carry-forward: actor-ownership, Teams sync) |
| `endpoints-evaluations.md` | v1.0 | **Final** | — (build-time carry-forward: service rules, nested keys) |
| `endpoints-admin-import.md` | v1.0 | **Final** | Phase D contract (Auth-owned users via aces-*, domain-only imports, double-guard destructives, DEC-5 proxy open) |
| `test-suite.md` | v1.0 | **Final** | test contract for auth-layer + B/C (CON/DEC/ACC/D); user-run sequential |
| `docker-compose-spec.md` | v1.0 | **Final** | — (root stack wiring: §2 blocks + §3 init + §4 secrets + §7 scripts) |
| `LOCAL-DEV-RUNBOOK.md` | v0.1 | **Draft** | local dev setup |
| `DEPLOY.md` | v0.1 | **Draft** | deployment |
| `FRONTEND-INTEGRATION.md` | v0.1 | **Draft** | cutover phase |
| `consult-readiness.md` | v1.4 | **Final** | provisioning checklist (aces-admin/dean/faculty/user + Auth↔students/employees link, pointer-only 118+5); normative auth = `auth-integration.md` Final v1.4 |
| `AGENTS.md` (assembly contract) | v1.0 | **Final** | — (working agreements, scaffold, status, spec pointers) |
| `student.md` / `faculty.md` / `faculty-loading.md` (education domains) | v1.1 | **Final** | — (first-class cache via SSO upsert, FK to domain IDs; unblock data-model Final) |

---

## DONE

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

### Consult + shared
- [ ] Slices B/C + auth port + `test-suite.md` promotion (gated; see AFTER FINAL SPECS)
- [ ] Mail service extraction (spec `services/mail/README.md` Final v1.0 — unblocked, unscheduled)
- [ ] `decisions/` — adopt (ADR-005+) or delete + prune `platform.md` refs (open)
