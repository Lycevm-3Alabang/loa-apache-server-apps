# SESSION PROMPT

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Cross-boundary record:** `## PROJECT UPDATES` is the durable cross-platform record — it preserves high-level decisions, design, and changes made across `assemblies/loa-auth-platform/`, `assemblies/loa-cert-platform/`, and `assemblies/loa-consult-platform/`. Keep it updated whenever a decision or design change touches more than one assembly.
4. **Platform-scoped prompts:** per-assembly session details (Last Session Notes, Session Log, open questions) live in `assemblies/loa-cert-platform/SESSION-PROMPT.md` and `assemblies/loa-auth-platform/SESSION-PROMPT.md`. Read them for the platform you're working on.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AGENT.md        - mandatory spec-first rules
2. AI-GUIDE.md     - architecture + Step 0 (spec check)
3. AI-RULES.md     - coding rules + Rule 0 (specs before code)
4. PROJECT.md      - project tracker: current status of every layer and phase
5. PROJECT_UPDATES.md - cross-boundary tracker: "PROJECT UPDATES" (per-platform decisions/design/changes) + "Last Session Notes" (where we stopped)
6. assemblies/loa-auth-platform/README.md    - auth platform scope
7. assemblies/loa-auth-platform/SESSION-PROMPT.md - auth platform session details (Last Session Notes, open questions)
8. assemblies/loa-cert-platform/README.md    - cert platform scope + SSO callback contract
9. assemblies/loa-cert-platform/api-endpoints.md - cert endpoint source of truth (priority spec)
10. assemblies/loa-cert-platform/SESSION-PROMPT.md - cert platform session details (Last Session Notes, open questions)
11. assemblies/loa-consult-platform/README.md - consult platform scope
12. assemblies/loa-auth-platform/tenant-group-endpoint-grants.md - level-based grants model (authority for consumer apps)

Then:
- Summarize the current state of each layer (kernels, domains, contexts, services, assemblies)
- Summarize the state of EACH of the three platform assemblies (auth / cert / consult)
- List what's Done, what's In Progress, what's Backlog per platform
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until a Final spec exists
```

---

## PROJECT UPDATES

Durable cross-boundary record: high-level decisions, design, and changes across the three platform assemblies. Each platform keeps its own detailed spec; this section preserves only what must survive across boundaries.

### Shared / Cross-Platform

| Decision / Design | Detail |
|-------------------|--------|
| App topology | 3 Laravel 12 API apps + 1 Next.js 16 UI (see table below) |
| Domain naming | All APIs on `*.lyceumalabang.edu.ph` (corrected 2026-08-05; was `*.loa.edu.ph`); example emails lowercase `@lyceumalabang.edu.ph` |
| Spec-first mandate | No code without a Final spec (AI-RULES.md Rule 0) |
| JWT model | Shared HMAC-SHA256 secret, HS256, `type=access`, local validation — no HTTP call per request |
| Cross-app access | JWT local validation + HTTP (Bearer) for user lookup; each app owns its DB — no shared DB reads |
| Identity authority | Auth Platform is sole source of truth for identity/password; apps are pure consumers (primary state changes via Auth API) |
| Permission model | Data-driven (`user_permission` DB table); consumer apps use **level-based grants** (`<level>:<path>`, e.g. `read:/api/v1/events`) per `tenant-group-endpoint-grants.md` — not static `cert.*`/`consult.*` keys |
| Verification endpoint | `GET /api/v1/auth/verify` — public JWT validation for consumers |
| Consumer allowlist | `AUTH_ALLOWED_REDIRECTS` + `CORS_ALLOWED_ORIGINS` + tenant `redirect_origins` must include every consumer UI origin (currently `https://e-cert.vercel.app`) |

| App | Subdomain | Database | Framework | Purpose |
|-----|-----------|----------|-----------|---------|
| Auth | auth.lyceumalabang.edu.ph | loa_auth | Laravel 12 | JWT token service, user management, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Laravel 12 | Consultation booking + faculty evaluation (combined) |
| Cert API | cert-api.lyceumalabang.edu.ph | loa_cert | Laravel 12 | Certificate issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js 16 | Cert frontend; pure consumer of Auth + Cert APIs |

### LOA Auth Platform — `assemblies/loa-auth-platform/`

- **Scoped session prompt:** `assemblies/loa-auth-platform/SESSION-PROMPT.md`
- **Status:** Scaffolded + largely implemented (Phase 1). **Not yet deployed** to `auth.lyceumalabang.edu.ph`.
- **Kernel:** Identity v3.0 (tenancy) implemented in code; many kernel specs still Draft.
- **Final specs (implemented):** `web-ui.md` v1.2 (destination resolution), `admin-dashboard.md` (v1 + v2), `tenant-endpoint-catalog.md` v3.2, `tenant-group-endpoint-grants.md` v1.1 (group priority), `access-config-import-export.md` v1.0, data-driven permission policy v1.0, RefreshToken.
- **Implemented highlights:** tenants + `user_tenants` (000011–000012), tenant-scoped groups/grants (000013–000015), `tenant` JWT claim + `jwt.tenant` middleware, admin dashboard v1/v2 (tenant CRUD, groups, per-group permissions, members, suspend/activate), group priority resolution (`user_groups.priority`, default 10, 1 = highest), endpoint catalog + bulk import, access config import/export, 172 tests pass.
- **2026-08-05 changes:** domain correction across docs/configs/tests/blades; allowlists updated (`config/cors.php`, `config/auth-web.php`, `.env.example`, `DEPLOY.md`, `environment.md`) to include `https://e-cert.vercel.app`.
- **Phase B (Cert readiness) — DEFERRED 2026-08-06:** user decision — **no Cert data baked into Auth seeders/database.sql** (a `CertReadinessSeeder` was created then reverted). Provisioning is **manual at deploy-time** per the runbook **`cert-readiness.md`** (**Final v0.2**, branch `docs/cert-readiness-runbook`): `loa` tenant (`redirect_origins` incl. `https://e-cert.vercel.app`), 48-endpoint Appendix A catalog import, `cert-admin`/`cert-staff`/`cert-user` groups (priorities 2/3/4, created manually), 48-row grant matrix (admin 48 / staff 39 / user 7), verification steps. Payload + matrix parity-checked against `api-endpoints.md` Appendix A. **§8 Local Development added (v0.2):** same provisioning via Docker Compose (`localhost:8080` admin UI + optional ad-hoc tinker fast path; local origin/CORS/redirect table).
- **Next:** deploy Auth; then provision per `cert-readiness.md` (Final).

### LOA Cert Platform — `assemblies/loa-cert-platform/`

- **Scoped session prompt:** `assemblies/loa-cert-platform/SESSION-PROMPT.md`
- **Status:** Spec phase — **Phase A complete (2026-08-06)**: `api-endpoints.md` and `legacy-e-cert-integration.md` are **Final**. Retrofit of legacy `e-cert` (Next.js 16) into a **pure consumer** of Auth + Cert, spec-gated for implementation.
- **Key specs:** `api-endpoints.md` (**Final v1.4** — 50 domain endpoints: 48 JWT-gated + 2 public; **decision #20: Cert API auth deferred**, Phase C = unauth domain CRUD), `legacy-e-cert-integration.md` (**Final v2.1**; authoritative retrofit spec, synced to `D:\loa\e-cert\legacy-e-cert-integration.md` per D7 — **D9 auth deferral**, C-Auth phase; Auth-provisioning sections are side-notes pointing at `cert-readiness.md`), `web-ui.md`, `README.md`.
- **Retrofit decisions D1–D7 (locked) + D8 superseded:** refactor-in-place; fresh start with no migration; archive-then-drop legacy DB; roles via user-groups + level grants; PDF/QR/email owned by Cert; spec synced to `e-cert` repo; ~~D8 SSR access-token cookie~~ **superseded 2026-08-06 — CSR wins**: `e-cert` is a **client-side SPA** (token in memory only, no server actions, no server-side JWT verification, `src/proxy.ts` deleted, no shared secret; refresh stays in the Cert-proxied httpOnly `loa_cert_refresh` cookie; route guard is client-side only).
- **SSO design (Q-1 resolved: split-origin):** browser hits `e-cert.vercel.app`; Vercel rewrite `/api/v1/:path*` → `https://cert-api.lyceumalabang.edu.ph/api/v1/:path*` keeps httpOnly refresh cookie same-origin; direct cross-origin CORS is fallback only. Flow: `auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app` → `https://e-cert.vercel.app#payload=<AES-256-GCM>` → `POST /api/v1/auth/callback` (decrypt, `exp` + tenant-slug validation, httpOnly `SameSite=Lax` refresh cookie) → `jwt.auth` (local HS256, no users table, tenant claim) + `jwt.endpoint` (local catalog mirror, closed-by-default, owner-rule hook). Cert-proxied refresh/logout; `/sso/register`, `/forgot-password`, `/reset-password`.
- **Auth contract (verified from code):** HS256, `type=access`, TTLs 15/10080 min, claims `{ sub, email, name, groups, permissions, scopes, tenant:{id,slug} }`, `GET /api/v1/auth/access`.
- **Open questions — all resolved 2026-08-06:** Q-2 → **CSR supersedes D8** (SPA, in-memory token, parse-only client JWT, no server actions / no shared secret, `src/proxy.ts` deleted — aligned with `D:\loa\e-cert\specs\` v2.0); Q-3 audit/email-log gaps (deferred — drop affected UI; future dedicated SMTP API, maybe reuse Auth's temp email tool); Q-4 seed groups (`cert-admin`/`cert-staff`/`cert-user`, no LOA group reuse); Q-5 `/my/profile` (out of scope, front-end refinement task); Q-6 cert number (per-event user-configurable pattern required, must contain `####`, no default); Q-7 attendees/import (JSON payload — CSV parsing is a UI concern); **decision #17** (Cert-proxied refresh/logout confirmed); **dashboard stats at `read`** (confirmed **with ownership note** — dashboard data is org-wide unscoped, grants `cert-admin`/`cert-staff` only, excluded from `cert-user`, per `api-endpoints.md` §5.7 + `legacy-e-cert-integration.md` §7.2).
- **Next:** Phase B — Auth readiness — **deferred (2026-08-06)**: provisioned manually at deploy-time per Auth runbook `cert-readiness.md` (**Final v0.2**, incl. §8 Local Development; no baked-in seeder); Phase C — scaffold Laravel 12 Cert app — **domain CRUD slice only, unauthenticated** (decision #20 / D9: `jwt.auth`/`jwt.endpoint` + SSO callback/refresh/logout deferred to a later auth phase).

### LOA Consult Platform — `assemblies/loa-consult-platform/`

- **Status:** Draft spec only, no code.
- **Key spec:** `README.md` v1.0 Draft.
- **Design:** thin composition layer wiring **Consultation + Evaluation + Academic** business contexts; owns API routing, middleware, JWT validation, request/response transformation, API docs, deployment — owns **no business logic**.
- **API surface (draft):** appointments (+batch/accept/decline/complete/cancel), availability-rules CRUD, semesters, evaluation-periods, evaluations (ratings/comments/submit/pending), admin departments/subjects/sections, 7 report endpoints.
- **Deployment:** `aces-api.lyceumalabang.edu.ph`, Laravel 12, cPanel, PHP 8.2+, MySQL 8.
- **Future:** evaluation → certificate event to Cert Platform.
- **Next:** needs its own endpoint/auth spec (mirror Cert's `api-endpoints.md`) before implementation; not yet started.

---

## Last Session Notes

### Date: 2026-08-06

### Completed
- **Resolved Q-2..Q-7 (Cert Phase A gate)** — see Cert Platform section above for outcomes.
- **Bumped `api-endpoints.md` → Draft v1.3** (Q-6/Q-7 synced): `certificate_number_pattern` is required + user-configurable, must contain `####` (no default); `/events/{id}/attendees/import` accepts a **JSON payload** (CSV is a UI concern).
- **Bumped `legacy-e-cert-integration.md` → Draft v1.1** (all Qs resolved in §13): D8 reframed (API-enforced JWT-cookie model), seed groups `cert-admin`/`cert-staff`/`cert-user`, `/my/profile` out of scope.
- **CSR decision (2026-08-06):** checked `D:\loa\e-cert\specs\` v2.0 — supersedes D8 with a **client-side SPA**; user chose **CSR wins**. Rewrote `legacy-e-cert-integration.md` → **Draft v2.0** (D8 superseded in §5; §3 SPA; §6 in-memory session + parse-only client JWT + route guard; §8 server actions deleted → typed client API; §10 no secrets env, adds `NEXT_PUBLIC_CERT_TENANT_SLUG`; §12 phases D/E reworded; §13 Q-2=CSR, R-4=XSS/in-memory risk). Synced to `D:\loa\e-cert\legacy-e-cert-integration.md` (D7). Fixed `e-cert/specs` stale bits (seed groups `cert-admin`/`cert-staff`/`cert-user`, attendee import = JSON payload, CSV parse stays client-side).
- **Phase A COMPLETE (2026-08-06):** user confirmed decision #17 (Cert-proxied refresh/logout) and dashboard stats at `read` (with ownership note). Promoted `api-endpoints.md` → **Final v1.4** (§9.2 SSO URL → `/sso/login`, §9.9 `/access` optional, §5.7 dashboard ownership note, decision #17 confirmed, example numbers → `CERT-0001`, **#20 auth deferred**) and `legacy-e-cert-integration.md` → **Final v2.1** (§7.2 dashboard ownership note, §12 Phase A complete; **D9 auth deferral**, C-Auth phase; Auth-provisioning sections refactored to side-notes). D7 copy re-synced.

### In Progress
- **Phase B (next):** Auth readiness — redirect allowlist, cert catalog import (Appendix A), **provision** `cert-admin`/`cert-staff`/`cert-user` groups + grants (per `cert-readiness.md`; **not** seeded)

### Next Action
- [x] **Phase A — COMPLETE 2026-08-06:** `api-endpoints.md` v1.4 and `legacy-e-cert-integration.md` v2.1 → **Final** (incl. #20 auth deferral, D9, side-note refactor)
- [ ] **Phase B:** Auth readiness — redirect allowlist incl. `https://e-cert.vercel.app`, cert catalog import (Appendix A), **provision** `cert-admin`/`cert-staff`/`cert-user` groups + grants (per `cert-readiness.md`; **not** seeded)
- [ ] **Phase C:** scaffold Laravel 12 Cert app — **domain CRUD slice only, unauthenticated** (decision #20 / D9: `jwt.auth`/`jwt.endpoint` + SSO callback/refresh/logout deferred to a later auth phase)

### Backlog / Known Gaps
- Deferred (Q-3): global email logs + audit-log delete/entity/user/by-ids queries dropped from retrofit; future dedicated SMTP API (check reuse of Auth's temporary email tool)

---

### Date: 2026-08-05

### Completed
- **Authored the legacy `e-cert` retrofit spec** — `assemblies/loa-cert-platform/legacy-e-cert-integration.md` (Draft v1.0, augmented with §10.4–10.8 concrete wiring):
  - Turns the Next.js 16 `e-cert` app into a pure consumer of Auth + Cert: SSO (`/sso/login`, `#payload=` fragment, verify-only `jose` JWT §6.4), roles via user-groups + level-based grants (§7), full server-action/API-route → Cert v1.2 endpoint mapping (§8), env contract (§10.1), decommission plan (§11), file-by-file checklist (§10.8), impl plan (§12)
  - Locked decisions D1–D8 (refactor-in-place; fresh start; archive-then-drop DB; roles via groups; PDF/QR/email owned by Cert; spec synced to `e-cert` repo; SSR access-token cookie)
  - Synced working copy in the `e-cert` repo per D7
- **Corrected all LOA domains across monorepo + `e-cert`** (committed in both repos):
  - `auth.loa.edu.ph` → `auth.lyceumalabang.edu.ph`
  - `cert.loa.edu.ph` → `cert-api.lyceumalabang.edu.ph` (API host) + `e-cert.vercel.app` (UI, Vercel) — **Q-1 resolved: split-origin**
  - `consult.loa.edu.ph` → `aces-api.lyceumalabang.edu.ph` (Consult + Eval combined)
  - example emails `@loa.edu.ph` → `@lyceumalabang.edu.ph`
  - Touched docs, `config/cors.php`, `config/auth-web.php`, `.env.example`, tests, blade placeholders; retrofit spec §10.7 rewritten (Vercel rewrites + `cert-api` vhost)

### In Progress
- `api-endpoints.md` — **Draft v1.2**, awaiting user review
- `legacy-e-cert-integration.md` — **Draft**, awaiting user review

### Next Action
- [ ] **Phase A:** review + promote `api-endpoints.md` v1.2 and `legacy-e-cert-integration.md` → **Final**; resolve open questions Q-2..Q-7 (§13 of retrofit spec)
- [ ] **Phase B:** Auth readiness — redirect allowlist incl. `https://e-cert.vercel.app`, cert catalog import, **provision** groups (per `cert-readiness.md`; **not** seeded)
- [ ] **Phase C:** scaffold Laravel 12 Cert app — **domain CRUD slice only, unauthenticated** (decision #20 / D9: auth deferred)

### Backlog / Known Gaps
- **DECISION:** Auth Platform remains the sole source of truth for identity/password changes; apps rely on the Auth API for primary state modification.
- **CONFIRMATION:** Permission registry is data-driven (`user_permission` DB table), not static JSON — enforced in future dev.
- No-terminal deploy requires uploading prebuilt `vendor/` (pure-PHP deps; safe cross-platform).
- Cert API v1.2 gaps (retrofit spec Q-3): audit-log delete/entity queries + global email logs — drop UI or extend the API.

---

## Session Log

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-07-31 | Identity events/rules specs; auth controllers; middleware; CORS spec+impl; spec-first mandate | Deploy auth or start Phase 2 |
| 2026-07-31 | RefreshToken entity spec + contract + README/PROJECT updates | Implement RefreshToken or deploy auth or Phase 2 |
| 2026-07-31 | Auth Web UI spec (login redirect, forgot/change password, email, CSRF) + rule unification | Implement RefreshToken or Auth Web UI or deploy |
| 2026-07-31 | RefreshToken spec → Final; model + migration + IdentityService wiring | Auth Web UI or deploy auth |
| 2026-07-31 | Docker local dev (php:8.3, nginx, mysql, mailpit); Rule 7 disable endpoint + Rule 8 pruning; Laravel 12 upgrade; all verified | Auth Web UI or deploy auth |
| 2026-08-01 | Auth Web UI implemented; deployed CORS/Swagger/seeder issues investigated and deployment safeguards added | Deploy corrected auth release |
| 2026-08-01 | SQL dump fixed (sessions table + real admin hash); no-terminal DEPLOY.md section; login destination resolution spec'd (web-ui v1.2); Admin Dashboard spec (Draft) | Promote Admin Dashboard to Final + implement, or deploy |
| 2026-08-01 | Identity Kernel v3.0 tenancy spec (tenants, scoped groups/grants, `jwt.tenant`, claims) + admin-dashboard v2 tenant admin | Review/promote tenancy spec; then implement |
| 2026-08-01 | Tenancy + admin dashboard v1 implemented and verified (migrations, TenantService, tenant-scoped auth, `jwt.tenant`/`web.admin`, login destination, `/admin/users`); `database.sql` rebuilt from migration schema (parity-checked) | Deploy current auth release, or start admin dashboard v2 |
| 2026-08-01 | Admin dashboard v2 implemented: tenant CRUD, groups, per-group permissions, members, suspend/activate; `admin-dashboard.md` promoted to Final; dist regenerated | Deploy, or start Phase 2 (Consult App) |
| 2026-08-02 | Cert Platform SSO spec (README.md §11-12), web-ui.md created, group-permission-management.md created, architecture decisions (SSO-only for LOA, self-hosted for external, permission-based role mapping) | Implement group/permission API + admin UI |
| 2026-08-02 | Verified group/permission management already implemented (GroupController, UserGroupController, 3 Blade views, routes); verified admin create user v3 already implemented | Add permission registry, or implement Cert Platform SSO |
| 2026-08-02 | Data-driven permission policy spec finalized (Final v1.0); old registry/claims specs marked SUPERSEDED | Implement permission policy in loa-auth-platform |
| 2026-08-02 | Auth Platform implementation complete: 4 migrations, 4 models, PermissionPolicyService, ClaimPolicyMiddleware, ImportPermissions command, PermissionPolicyController, JWT claims/scopes, admin API endpoints, middleware registration | Create permissions.json per app; implement Cert SSO; run tests |
| 2026-08-02 | Tests written: 12 model tests, 9 middleware tests, 20+ controller tests, 12 service tests, 8 command tests; WithJwtClaims trait created | Run tests and verify |
| 2026-08-02 | Read AGENT.md, AI-GUIDE.md, AI-RULES.md, PROJECT.md, group-permission-management.md, cert web-ui.md, cert README.md, tenant-endpoint-catalog.md, permission-resolution.md, data-driven-permission-policy.md, tenancy.md, README.md, user-group.md, AuthorizationService.php, RoutePolicy.php; summarized all layers + Phase 1-4 status; created `tenant-group-endpoint-grants.md` spec (Draft→Final); updated SESSION-PROMPT.md | Implement tenant-endpoint-catalog.md + tenant-group-endpoint-grants.md in code |
| 2026-08-02 | Added Admin UI §6 to `tenant-endpoint-catalog.md` (endpoint catalog list, create form, bulk import, validate, delete with force) and Admin UI §8 to `tenant-group-endpoint-grants.md` (group endpoint grants page, user endpoint overrides page); updated Implementation Inventory and Dependency References in both specs; updated SESSION-PROMPT.md | Build Blade views for tenant endpoint catalog + group/user endpoint grants |
| 2026-08-02 | Implemented tenant endpoint catalog + group endpoint grants: 3 migrations, 3 models, EndpointGrantController, ClaimPolicyMiddleware extension, GET /api/v1/auth/access endpoint, admin web + API routes, 3 Blade views. Fixed ImportPermissionsCommandTest (Artisan::call() to resolve SQLite :memory: isolation). All 143 tests pass. Clarified: permissions.json per app not needed — bulk import API already accepts the JSON format as payload. | Implement Cert Platform SSO |
| 2026-08-03 | Group priority resolution spec'd: `user_groups.priority` (int, default 10, 1 = highest); `tenant-group-endpoint-grants.md` → Final v1.1 (§3.3 Group Priority + §4 algorithm — highest-precedence wins, `deny` only on priority ties); `user-group.md` + `permission-resolution.md` updated. Endpoint catalog admin UI navigation fixed + `tenant-endpoint-catalog.md` → Final v3.2 (web/API split, entry point). | Implement group priority in code |
| 2026-08-03 | Implemented group priority: migration, model, resolution logic, admin UI, tests (143 pass); committed + pushed. Wrote `access-config-import-export.md` spec (Draft v1.0) — JSON template download, export, import with preview/confirm for groups+grants+overrides. | Implement access config import/export |
| 2026-08-03 | Reviewed `access-config-import-export.md` against related specs; fixed 3 P0 issues (export query, platform-wide groups, confirm/dry_run), 3 P1 issues (active check, API routes, invariants), 3 P2 issues (none no-op, * wildcard, validation notes). Spec promoted to **Final v1.0**. | Implement access config import/export |
| 2026-08-03 | Implemented Access Config Import/Export: `AccessConfigController` (template, export, import), web + API routes, `access-config-import.blade.php` (file upload, paste JSON, preview, confirm), buttons on tenant show + groups pages, 3 factories (composite PK), `HasFactory` on 3 models, 29 tests (all 172 pass). | Deploy auth or start Cert Platform SSO |
| 2026-08-05 | Retrofit spec + domain correction (see Last Session Notes); root SESSION-PROMPT restructured into cross-boundary **PROJECT UPDATES** record for auth/cert/consult | Phase A: review + promote Cert specs to Final |
| 2026-08-06 | Resolved Q-2..Q-7; `api-endpoints.md` → Draft v1.3 (cert-number pattern required/user-configurable, attendees/import JSON) + `legacy-e-cert-integration.md` → Draft v1.1 (Qs resolved, seed groups `cert-admin/staff/user`) | Phase A: review + promote v1.3/v1.1 → Final → Phase B (Auth readiness) → Phase C (scaffold) |
| 2026-08-06 (2) | **CSR decision** (user: CSR wins): checked `D:\loa\e-cert\specs\` v2.0 — supersedes D8; rewrote `legacy-e-cert-integration.md` → Draft v2.0 (D8 superseded §5, SPA §3, in-memory/parse-only/route-guard §6, server actions deleted §8, no-secrets env §10, Q-2=CSR/R-4 §13); synced D7 copy; fixed `e-cert/specs` stale bits (seed groups, JSON import, CSV client-side); updated e-cert SESSION-PROMPT | Phase A: review + promote v1.3 / v2.0 → Final → Phase B → Phase C |
| 2026-08-06 (3) | **Phase A COMPLETE.** User confirmed decision #17 (Cert-proxied refresh/logout) + dashboard stats `read` with ownership note. Promoted `api-endpoints.md` → **Final v1.3** (SSO URL `/sso/login`, §9.9 `/access` optional, §5.7 dashboard ownership, decision #17 confirmed, examples `CERT-0001`) and `legacy-e-cert-integration.md` → **Final v2.0** (§7.2 ownership note, §12 A complete); D7 re-synced | Phase B (Auth readiness) → Phase C (scaffold) |
| 2026-08-06 (4) | **Phase B deferred (user decision):** no Cert data baked into Auth seeders/database.sql — `CertReadinessSeeder` attempt reverted; groups to be created **manually** at deploy-time. Wrote **`cert-readiness.md` (Draft v0.1)** runbook in `assemblies/loa-auth-platform/` on new branch **`docs/cert-readiness-runbook`** (loa tenant + redirect_origins, 48-endpoint Appendix A payload, groups, 48-row grant matrix, verification); payload + matrix parity-checked vs `api-endpoints.md` Appendix A | Review + promote `cert-readiness.md` → Final → deploy Auth → provision per runbook → Phase C (Cert scaffold) |
| 2026-08-06 (5) | **`cert-readiness.md` promoted to Final v0.1** (Phase B readiness runbook; payload + matrix verified 48/48) | Deploy Auth → provision per runbook → Phase C (Cert scaffold) |
| 2026-08-06 (6) | **`cert-readiness.md` → Final v0.2:** added **§8 Local Development** (Docker Compose) — local admin UI at `localhost:8080`, local origin/CORS/redirect table, optional ad-hoc tinker fast path for tenant+groups (not in any seeder), local verification; §3 cross-ref; References + Doc Control renumbered to §9–§11 | Deploy Auth → provision per runbook → Phase C (Cert scaffold) |
| 2026-08-06 (7) | **Decision #20 (2026-08-06):** no auth on Cert API endpoints for now — Phase C = unauth domain CRUD slice; SSO + `jwt.auth`/`jwt.endpoint` deferred to a later **C-Auth** phase. `legacy-e-cert-integration.md` → **Final v2.1** (D9, C-Auth row, side-note refactor of Auth-provisioning sections). `api-endpoints.md` → **Final v1.4** (decision #20, §13 inventory annotated). `README.md` → Draft v1.1 (§11.8 side-note). | Phase C scaffold (unauth core slice) |
