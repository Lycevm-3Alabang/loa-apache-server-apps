# TODO — Consult Platform Spec Program

**Updated:** 2026-09-18
**Scope:** `assemblies/loa-consult-platform/` specs, with Auth + Cert as reference/basis.
**Rule:** No implementation code until the relevant spec is Final (AI-RULES Rule 0).

---

## NOW (in progress)

- [ ] Draft endpoint modules #2–#6 (per-endpoint request/response contracts)
- [ ] Promote `api-endpoints.md` to Final once the above land

---

## PLANNED

- [ ] Phase B spec slice: appointments + availability + academic + semesters
- [ ] Phase C spec slice: evaluations + periods + rubrics + results
- [ ] Phase D spec slice: admin-users + import + data-audit; `/service/*` proxy decision (admin module spec)
- [ ] Phase E: reports as Laravel API + cutover (frontend rewrite decision: Vercel rewrite vs direct+CORS)
- [ ] Implementation (scaffold → domain slices → C-Auth → cutover) — only after specs are Final
- [ ] Auth provisioning per `auth-integration.md` §8 at deploy time (tenant, groups, catalog import, grants, secrets)

---

## DONE

- [x] 2026-09-18 — Consult endpoint inventory: 112 `route.ts` files (~142 combos; 118 migrating to Laravel + 5 public/SSO), drift vs `endpoint-catalog.md` recorded, frontend untouched
- [x] 2026-09-18 — `assemblies/loa-consult-platform/api-endpoints.md` Draft v0.1 (conventions, 118-route summary, levels, scoping from handlers, design decisions)
- [x] 2026-09-18 — `assemblies/loa-consult-platform/auth-integration.md` Final v1.1 (SSO contract verbatim from cert controllers, cookie reality, §10 14-item port inventory)
- [x] 2026-09-18 — Cert spec-vs-code audit + alignment: `api-endpoints.md` v1.8 (ordinals, slug, 61-entry catalog, 64-domain totals), `authenticated-endpoints-spec.md` v1.2, `auth-proxy.md` (11 routes), `FRONTEND-INTEGRATION.md` (rewrite actual-vs-recommended)
- [x] 2026-09-18 — Resolved spec §7 #9 from code: `POST /semesters/{id}` = ADMIN-only activate; semesters PATCH ADMIN-only (levels `write`→`admin`); categories POST `write`→`admin` (code requires ADMIN); DELETE shapes confirmed (`{categoryId}`, `{all|ids}`); `{data}`/`{success:true}` response variants recorded
- [x] 2026-09-18 — Resolved spec §7 #10: no local admin/role in Consult — visibility stays `required_level=admin`; DEAN access is a deploy-time Auth grant (default ADMIN-only), per cert tenant-group pattern
- [x] 2026-09-18 — `assemblies/loa-consult-platform/data-model.md` Draft v0.1: 25-table MySQL 8 port following the cert↔auth pattern (opaque-sub, uuid PKs, slim users, 8 drops, FK-safe order); 3 column shapes flagged for module-spec verification
- [x] 2026-09-18 — Stripped local roles: `ADMIN`/`DEAN`/`FACULTY`/`STUDENT` exist only as auth-app tenant groups (§4.0 binding rule); gates reframed as JWT group-membership; backend unification of group-split paths deferred to module specs; `auth-integration.md` v1.2
- [x] 2026-09-18 — Scanned `lib/db/common.ts`: corrected users shape (`semester_id` rename, `deleted_at` soft-delete, no `evaluation_eligible`); captured query shapes for modules (appointmentSelect variants, FK-embed names, ratings fan-out, pipe-join retirement)
- [x] 2026-09-18 — e-cert reality checks: no rewrite in code (direct+CORS actual); QR + email verified implemented (retired stub notes)
- [x] 2026-09-18 — Trackers: `PROJECT.md` + `PROJECT_UPDATES.md` updated; delivery split into independent Auth / Cert / Consult tracks

---

## Reference basis (read-only for Consult)

- Auth: `tenant-group-endpoint-grants.md` v1.1, `tenant-endpoint-catalog.md` v3.2, `tenant-app-api.md`, `unified-auth-flow.md`
- Cert: `api-endpoints.md` v1.8 (verified implementation), `auth-proxy.md`, middleware + auth controllers
