# TODO — Consult Platform Spec Program

**Updated:** 2026-09-18
**Scope:** `assemblies/loa-consult-platform/` specs, with Auth + Cert as reference/basis.
**Rule:** No implementation code until the relevant spec `.md` file is Final (AI-RULES.md Rule 0).

---

## NOW (in progress)

- [ ] Promote `data-model.md` v0.2 (hybrid users: cache table, no FK relationships, all user-ref columns = opaque Auth sub TEXT) to Final — **needs review of §3 column shapes flagged for module-spec verification before promotion**

- [ ] Promote `endpoints-academic.md` Draft v0.1 → Final — verify against data-model v0.2 + `api-endpoints.md` §5.4/§5.5

- [ ] Promote `endpoints-appointments.md` Draft v0.1 → Final — verify against data-model v0.2 + `api-endpoints.md` §5.1/§5.2

- [ ] Promote `endpoints-evaluations.md` Draft v0.1 → Final — verify against data-model v0.2 + `api-endpoints.md` §5.6–§5.9

---

## AFTER FINAL SPECS (implementation gates)

- [ ] First migration(s) — `users` + `departments` (FK-safe per data-model.md §8) → wire compose per `docker-compose-spec.md` → `loa_consult` DB init → consult-app in Docker

- [ ] Auth layer — middleware + controllers per `auth-integration.md` Final v1.2 (SSO callback/refresh/logout, `jwt.auth`/`jwt.endpoint`)

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
| `data-model.md` | v0.2 | **Draft** | first migration, compose wiring |
| `endpoints-academic.md` | v0.1 | **Draft** | domain slice B implementation |
| `endpoints-appointments.md` | v0.1 | **Draft** | domain slice B implementation |
| `endpoints-evaluations.md` | v0.1 | **Draft** | domain slice C implementation |
| `test-suite.md` | v0.1 | **Draft** | test infrastructure |
| `docker-compose-spec.md` | v0.1 | **Draft** | compose wiring (blocked on first migration) |
| `LOCAL-DEV-RUNBOOK.md` | v0.1 | **Draft** | local dev setup |
| `DEPLOY.md` | v0.1 | **Draft** | deployment |
| `FRONTEND-INTEGRATION.md` | v0.1 | **Draft** | cutover phase |
| `consult-readiness.md` | v1.0 | **Draft** | auth provisioning — **OUTDATED: says "Laravel assembly is deferred" — no longer true** |

---

## DONE

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
