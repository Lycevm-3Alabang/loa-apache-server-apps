# TODO — Consult Platform Spec Program

> **ACTIVE 2026-09-22** — Consult spec program resumed (spec-first); Auth + Cert deferred (working, no changes). Staleness resolved 2026-09-22: SPEC STATUS reconciled against assembly files. Boundary decision: ONE assembly, two contexts (Consultation + Evaluation, no split).

**Updated:** 2026-09-22
**Scope:** `assemblies/loa-consult-platform/` specs, with Auth + Cert as reference/basis.
**Rule:** No implementation code until the relevant spec `.md` file is Final (AGENTS.md Rule 0).

---

## NOW (in progress)

- [x] 2026-09-19 — Academic slice partial — migrations 000001-000009 (departments, department_courses, subjects, sections, students, employees, semesters, faculty_subjects, student_enrollments) + 000010 audit → 9 models → `AcademicController` + `SemesterController` → ~24 routes (no auth middleware yet; stubs pass-through)

- [ ] Auth layer — middleware + controllers per `auth-integration.md` Final v1.2 (SSO callback/refresh/logout, `jwt.auth`/`jwt.endpoint`)

- [x] 2026-09-22 — `data-model.md` Draft v1.1 → **Final v1.2** (all carry-forward source-verified: M19 semester columns, M21 enrollment mapping link, M26/M27 subject-level grain, M30/M31 periods; dropped invented `student_sections`; fixed course-linked `sections`; §7 baseline delta filed for slice build)

---

## AFTER FINAL SPECS (implementation gates)

- [ ] Domain slice B — appointments/academic/semesters (appointments + academic + semesters handlers)

- [ ] Domain slice C — evaluations/periods/rubrics/results

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
| `auth-integration.md` | v1.2 | **Final** | — (root spec) |
| `data-model.md` | v1.2 | **Final** | — (source-verified vs supabase-schema M17/M19/M21/M26/M27/M30/M31; Owner + no-cross-FK rules) |
| `endpoints-academic.md` | v1.0 | **Final** | — (build-time carry-forward: repo-only fields, join paths) |
| `endpoints-appointments.md` | v1.0 | **Final** | — (build-time carry-forward: actor-ownership, Teams sync) |
| `endpoints-evaluations.md` | v1.0 | **Final** | — (build-time carry-forward: service rules, nested keys) |
| `test-suite.md` | v0.1 | **Draft** | test infrastructure |
| `docker-compose-spec.md` | v1.0 | **Final** | — (root stack wiring: §2 blocks + §3 init + §4 secrets + §7 scripts) |
| `LOCAL-DEV-RUNBOOK.md` | v0.1 | **Draft** | local dev setup |
| `DEPLOY.md` | v0.1 | **Draft** | deployment |
| `FRONTEND-INTEGRATION.md` | v0.1 | **Draft** | cutover phase |
| `consult-readiness.md` | v1.0 | **Draft** | auth provisioning — **OUTDATED: says "Laravel assembly is deferred" — no longer true** |
| `AGENTS.md` (assembly contract) | v1.0 | **Final** | — (working agreements, scaffold, status, spec pointers) |
| `student.md` / `faculty.md` / `faculty-loading.md` (education domains) | v1.1 | **Final** | — (first-class cache via SSO upsert, FK to domain IDs; unblock data-model Final) |

---

## DONE

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
