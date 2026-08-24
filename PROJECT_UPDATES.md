# SESSION PROMPT

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Cross-boundary record:** `## PROJECT UPDATES` is the durable cross-platform record — it preserves high-level decisions, design, and changes made across `assemblies/loa-auth-platform/`, `assemblies/loa-cert-platform/`, and `assemblies/loa-consult-platform/`. Keep it updated whenever a decision or design change touches more than one assembly.
4. **Platform-scoped prompts:** per-assembly session details (Last Session Notes, Session Log, open questions) live in `assemblies/loa-auth-platform/SESSION-PROMPT.md`. Read them for the platform you're working on.

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
10. assemblies/loa-consult-platform/README.md - consult platform scope
11. assemblies/loa-auth-platform/tenant-group-endpoint-grants.md - level-based grants model (authority for consumer apps)

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
| Auth | auth.lyceumalabang.edu.ph | lyceumalabang_auth_db (local: `loa_auth`) | Laravel 12 | JWT token service, user management, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Laravel 12 | Consultation booking + faculty evaluation (combined) |
| Cert API | cert-api.lyceumalabang.edu.ph | lyceumalabang_e_cert (local: `loa_cert`) | Laravel 12 | Certificate issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js 16 | Cert frontend; pure consumer of Auth + Cert APIs |

> Production DB names/users provisioned in cPanel 2026-08-24: both apps use user `lyceumalabang_auth_admin`; passwords are deploy-time placeholders (never committed). See each platform's `DEPLOY.md` + `.env.cpanel` template.

### LOA Auth Platform — `assemblies/loa-auth-platform/`

- **Scoped session prompt:** `assemblies/loa-auth-platform/SESSION-PROMPT.md`
- **Status:** Scaffolded + largely implemented (Phase 1). **Not yet deployed** to `auth.lyceumalabang.edu.ph`. SSO entry point is live (`/sso/login`, `/sso/register`, `/redirect`).
- **Kernel:** Identity v3.0 (tenancy) implemented in code; many kernel specs still Draft.
- **Final specs (implemented):** `web-ui.md` v1.2 (destination resolution), `admin-dashboard.md` (v1 + v2), `tenant-endpoint-catalog.md` v3.2, `tenant-group-endpoint-grants.md` v1.1 (group priority), `access-config-import-export.md` v1.0, data-driven permission policy v1.0, RefreshToken.
- **Final specs (pending implementation):** `user-account-activation.md` v1.0 — replaces self-registration with backend-provisioned activation flow (pending status, activation tokens, email, admin resend).
- **Implemented highlights:** tenants + `user_tenants` (000011–000012), tenant-scoped groups/grants (000013–000015), `tenant` JWT claim + `jwt.tenant` middleware, admin dashboard v1/v2 (tenant CRUD, groups, per-group permissions, members, suspend/activate), group priority resolution (`user_groups.priority`, default 10, 1 = highest), endpoint catalog + bulk import, access config import/export, SSO web auth (login/register/redirect), EncryptionService decrypt bug fix. **210 tests pass**.
- **2026-08-05 changes:** domain correction across docs/configs/tests/blades; allowlists updated (`config/cors.php`, `config/auth-web.php`, `.env.example`, `DEPLOY.md`, `environment.md`) to include `https://e-cert.vercel.app`.
- **Phase B (Cert readiness) — DEFERRED 2026-08-06:** user decision — **no Cert data baked into the production Auth seed path** (`DatabaseSeeder` production runs only `AdminSeeder`; `database/seeders/database.sql` untouched — a `CertReadinessSeeder` attempt was created then reverted). Provisioning is **manual at deploy-time** per the runbook **`cert-readiness.md`** (**Final v0.4**, branch `docs/cert-readiness-runbook`): `loa` tenant (`redirect_origins` incl. `https://e-cert.vercel.app`), 48-endpoint Appendix A catalog import, `cert-admin`/`cert-staff`/`cert-user` groups (priorities 2/3/4, created manually), 48-row grant matrix (admin 48 / staff 39 / user 7), verification steps. Payload + matrix parity-checked against `api-endpoints.md` Appendix A. **§8 Local Development (v0.2→v0.4):** local Docker provisioning via the **`LocalCertReadinessSeeder`** (runs automatically on local `db:seed` via `DatabaseSeeder` non-prod guard — creates `cert-app` tenant @ `localhost:9001` + groups; catalog/grants still via local admin UI) plus an optional tinker fast path; local origin/CORS/redirect table.
- **Next:** Auth deployment **deferred** (user decision 2026-08-06 — focus on Cert platform). Provisioning per `cert-readiness.md` (**Final v0.4**) happens at deploy-time; no action needed until Auth is deployed. Three things lined up for the Cert platform: **(1) Phase C** — Laravel 12 Cert app **scaffold created 2026-08-06** (`cert-app/`: core models + migrations); next is the **unauth domain CRUD slice** (events/attendees/templates/certificates + tests); **(2) C-Auth phase** — SSO `callback`/`refresh`/`logout` + `jwt.auth`/`jwt.endpoint` middleware (deferred from Phase C per decision #20/D9); **(3) Phase D** — `e-cert` auth swap (CSR): in-memory token, silent refresh, SSO fragment handler, parse-only JWT, client auth guard (depends on C-Auth).
- **2026-08-24 changes:** Admin UI overhaul committed (`4ed2e80`, `8d40f6b`) — breadcrumbs everywhere, link-text table actions, quick-action tiles; password API auth model documented (`37fcbb7`); JWT access TTL briefly 480 then **reverted to 15 min** per `token-lifecycle.md`; cPanel production DB names wired into DEPLOY/env docs (`d031994`); HeidiSQL runbook §14; `.env.cpanel` prod templates (gitignored). Details in `SESSION-PROMPT.md`.

### LOA Cert Platform — `assemblies/loa-cert-platform/`

- **Status:** **C-Auth complete (2026-08-11).** All endpoints now enforce `jwt.auth` + `jwt.endpoint` middleware. Auth endpoints (callback/refresh/logout) live. Auth platform SSO entry is now live — E2E SSO flow unblocked for Phase D.
- **Verified implementation (2026-08-11):** **All 48 domain endpoints + 2 public endpoints fully implemented.** Events (13), Attendees (8), Templates (5), Certificates (14), Me (4), Public (2), Dashboard (2), Audit (2), Auth SSO (3) — all fully implemented with real DB queries, validation, audit logging. **Stubs:** QR code generation (`GET /certificates/qr` and `GET /view/{id}` return hardcoded placeholder). **Missing:** `POST /certificates/{id}/email` (email sending service not built). Deferred to Service Phase: QR code generation, email sending.
- **Key specs:** `api-endpoints.md` (**Final v1.5** — 50 domain endpoints: 48 JWT-gated + 2 public; C-Auth implemented), `legacy-e-cert-integration.md` (**Final v2.2**; authoritative retrofit spec), `authenticated-endpoints-spec.md` (v1.1, updated 2026-08-11).
- **2026-08-24:** cPanel DB migration runbook drafted — root **`docs/cpanel-db-migration-runbook.md`** (Draft v0.1): fresh-database drop→create→seed path for `lyceumalabang_auth_db`/`lyceumalabang_e_cert`; documents that auth prod seed = admin only and cert seeder is empty (org-row FK 1452 gap), plus access-config export/import as the sanctioned groups+grants provisioning shortcut. Awaiting review/promotion.
- **Retrofit decisions D1–D7 (locked) + D8 superseded:** refactor-in-place; fresh start with no migration; archive-then-drop legacy DB; roles via user-groups + level grants; PDF/QR/email owned by Cert; spec synced to `e-cert` repo; ~~D8 SSR access-token cookie~~ **superseded 2026-08-06 — CSR wins**: `e-cert` is a **client-side SPA** (token in memory only, no server actions, no server-side JWT verification, `src/proxy.ts` deleted, no shared secret; refresh stays in the Cert-proxied httpOnly `loa_cert_refresh` cookie; route guard is client-side only).
- **SSO design (Q-1 resolved: split-origin):** browser hits `e-cert.vercel.app`; Vercel rewrite `/api/v1/:path*` → `https://cert-api.lyceumalabang.edu.ph/api/v1/:path*` keeps httpOnly refresh cookie same-origin; direct cross-origin CORS is fallback only. Flow: `auth.lyceumalabang.edu.ph/sso/login?redirect=https://e-cert.vercel.app` → `https://e-cert.vercel.app#payload=<AES-256-GCM>` → `POST /api/v1/auth/callback` (decrypt, `exp` + tenant-slug validation, httpOnly `SameSite=Lax` refresh cookie) → `jwt.auth` (local HS256, no users table, tenant claim) + `jwt.endpoint` (local catalog mirror, closed-by-default, owner-rule hook). Cert-proxied refresh/logout; `/sso/register`, `/forgot-password`, `/reset-password`.
- **Auth contract (verified from code):** HS256, `type=access`, TTLs 15/10080 min, claims `{ sub, email, name, groups, permissions, scopes, tenant:{id,slug} }`, `GET /api/v1/auth/access`.
- **C-Auth implementation (2026-08-11):**
  - `JWTService` (HS256 validate-only), `EncryptionService` (AES-256-GCM decrypt + key rotation)
  - `JwtMiddleware` (`jwt.auth`), `EndpointPolicyMiddleware` (`jwt.endpoint`, 48-entry catalog)
  - `AuthCallbackController`, `AuthRefreshController`, `AuthLogoutController` (throttled 10/min)
  - Routes: `auth/*` public; everything else behind `['jwt.auth','jwt.endpoint']`
  - `config/cert-platform.php` (tenant, cookie), `config/jwt.php` (secret, TTL), `config/auth-platform.php` (base_url, encryption keys)
  - 126 tests, 386 assertions, all green (MySQL `loa_cert_test`)
- **Retrofit phases A–H COMPLETE** (per `D:\loa\e-cert\whats-next.md`, updated 2026-08-23): D auth swap (2026-08-12 — `src/lib/auth/` token-store/jwt/sso-fragment/auth-guard, legacy Supabase stack deleted), E data swap (`b613c22` — 122 files, typed API client replaces all server actions), F UI cleanup + Playwright parity, G legacy DB decommission, H cross-app JWT/audit tests + OpenAPI completion. Post-H polish: loading UX, role scoping via `/me/*`.
- **2026-08-24:** **Template visibility spec Final + IMPLEMENTED** — `D:\loa\e-cert\specs\components\template-visibility.md` v1.1: `visibility ENUM(public,private)` + `updated_by` on `certificate_templates`; owner-set model (`created_by`/`updated_by`, never none); private = owners only, admin sees all; enforcement on list/show/**clone endpoints/event references** (side-door audit found unguarded `clone-template`/`clone-email-template`); 404-masking. Implementation commit `9904746`: 23 new visibility tests, full suite **168/557 green**; also fixed latent `jwt_claims.sub` dot-notation bug (`created_by` was never persisted) and corrected an inverted EndpointPolicy unit test. Runtime cache shards untracked.
- **Known gap:** local cert stack seeds no organizations → template/certificate writes fail with FK 1452 until a row exists for the backend-configured org (`CERT_ORGANIZATION_ID`). Fix candidate: Laravel seeder in cert backend.

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

### Date: 2026-08-24

### Completed
- **Admin UI overhaul (auth)** — breadcrumbs on every admin page incl. roots; 12 "Back to" buttons removed; link-text table actions; tenant/user/group detail quick-action tiles; `.button-ghost` white-text fix; new button variants (`neutral`/`soft-danger`/`soft-success`). Commits `4ed2e80`, `8d40f6b`; Web suite 85/85 green.
- **Password API documented** — full endpoint/auth table (`forgot`/`reset` public by design; `PUT /password`, `change-request` jwt.auth) in `web-ui.md` §12.3 + `kernels/identity/rules/password-reset-flow.md`.
- **JWT access TTL reverted to 15 min** (`37fcbb7`) — the 8-hour bump violated `token-lifecycle.md`; code default, compose, env examples all back to 15; containers recreated, verified live.
- **cPanel production DB wiring** (`d031994`) — auth `lyceumalabang_auth_db`, cert `lyceumalabang_e_cert`, user `lyceumalabang_auth_admin` on both; passwords = placeholders only; fixed `CACHE_STORE=redis` landmine in auth example.
- **DEPLOY.md hardened** — MAIL_* table, ENCRYPTION_KEY/AUTH_ADMIN_GROUP, data-migration recipe, server prerequisites, permissions, backup/rollback, sync-mail note.
- **HeidiSQL guide** (root runbook §14) and **`.env.cpanel` prod templates** for both platforms (gitignored, untracked).
- **Template visibility spec Final** in e-cert repo: `D:\loa\e-cert\specs\components\template-visibility.md` v1.1 — reviewed twice; caught side-doors (`clone-template`, `clone-email-template`, event references) + missing `updated_by`/owner-set model before promotion.
- **Cert housekeeping** — runtime cache shards untracked; `EventController.organization_id` payload addition verified benign (EventTest 19/19) but left uncommitted.

### Next Action
- [x] Implement template visibility in `loa-cert-platform` per the Final spec — **DONE 2026-08-24, commit `9904746`** (23 new tests; suite 168/557 green)
- [x] Implement post-reset redirect per web-ui.md v1.3 §4.3a — **DONE 2026-08-24, commit `28f152e`** (9 new tests; auth suite 219/528 green)
- [ ] Corrected 2026-08-24: e-cert retrofit phases D–H were already complete — trackers were stale; see `D:\loa\e-cert\whats-next.md`
- [ ] Seed a default organization in the cert backend (local FK-1452 gap from e-cert whats-next)

### Date: 2026-08-11

### Completed
- **Auth platform SSO fully implemented** — `GET /sso/login`, `POST /sso/login`, `GET /sso/register`, `POST /sso/register`, `GET /redirect` (splash page). Login rejects admin users, validates redirect origin against tenant `redirect_origins`, checks tenant membership, creates JWT + refresh token, stores encrypted payload in session, redirects via one-time splash page. Registration restricted to LOA domains.
- **EncryptionService::decrypt() bug fixed** — padding logic always appended `==`, which only decoded when unpadded base64 length % 4 == 2 (~1/3 of payloads). Fixed to pad to a multiple of 4. Verified with round-trip tests.
- **SsoAuthTest** — 15 tests, 54 assertions (encrypted + fragment paths, admin rejection, redirect validation, member-only access, one-time splash, registration domain restriction). Full auth suite: **210 tests, 498 assertions, all green**.
- **FRONTEND-INTEGRATION.md updated** — SSO entry is now live; removed "blocked on auth platform" caveat.
- **Cert Platform endpoint verification** — Audited all 50 endpoints against `api-endpoints.md` Final v1.5. **48 of 50 fully implemented.** Events (13), Attendees (8), Templates (5), Certificates (12/14), Me (4), Public (1/2), Dashboard (2), Audit (2), Auth SSO (3). Stubs: QR code in `GET /certificates/qr` and `GET /view/{id}`. Missing: `POST /certificates/{id}/email`.

### Next Action
- [ ] Deploy auth to `auth.lyceumalabang.edu.ph` (provision per `cert-readiness.md` Final v0.4)
- [ ] Phase D — e-cert auth swap (CSR) — **unblocked**

### Date: 2026-08-10

### Completed
- **Events & Attendees resource groups COMPLETE** per `api-endpoints.md` v1.4 (Final) — unauth domain CRUD slice done:
  - **Events (13 endpoints):** CRUD + real `stats()`, clone-template, clone-email-template, bulk-issue, reissue, issue-completed, revoke-expired (GET count + POST action) — OpenAPI-annotated.
  - **Attendees (8 endpoints):** list, create (upsert by event+email → 201), import (JSON; replace requires `confirm=true`), update (PATCH, event-scoped email conflict), destroy, destroy-with-cert, delete-preview, file-data (template → 200 / 410 / Storage download) — OpenAPI-annotated.
  - Routes registered in `routes/api.php` (PATCH per spec; nested `events/{eventId}/attendees` group).
- **Test suite GREEN: 91 tests / 334 assertions** via Docker (`docker compose exec -T cert-app vendor/bin/phpunit`). New feature tests: `tests/Feature/Api/EventTest.php` (13) + `AttendeeTest.php` (8).
- **Bugs fixed during suite run** (working-tree only, NOT committed): composite-PK sequence increment (`certificate_sequences`); `destroy*()` `Response` vs `: JsonResponse` → `json(null, 204)`; `CertificateController::store` attendee `firstOrCreate` missing `organization_id` + `$event` null scope; `expire()` counted after update (always 0); `PdfService` DomPDF v3 (facade + `loadHtml`); `Organization` missing `HasFactory`; factories (unique org slug, attendee `organization_id`).
- **Infra:** composer dev deps (`phpunit ^12.5` [13.x needs PHP ≥8.4.1; container 8.3.33], `mockery ^1.6`, `fakerphp/faker ^1.24`), `autoload-dev` `Tests\`; created `bootstrap/cache` + `storage/framework/{cache,sessions,views}` + `storage/logs` (volume shadowing); renamed `database/Migrations` → `database/migrations` (git mv — was only working on Windows via case-insensitive mount; would break cPanel/Linux).
- **Cleanup:** removed dead duplicate `app/Http/Controllers/Api/CertificateTemplateController.php`; deleted `.tmp_debug.php`.
- **Caveats:** nothing committed yet; `cert-app/` leftover scaffold dir still present. **Test DB (2026-08-10):** SQLite is a non-goal — `phpunit.xml.dist` now forces MySQL (`force="true"` beats compose shell env) against dedicated **`loa_cert_test`** (created + granted to `loa`); tests no longer touch `loa_cert` app data; `certificates` migration's MySQL-only `storedAs('IF(...)')` is intentional.

### Next Action
- [ ] Commit completed Events/Attendees work (await explicit instruction)
- [ ] Remove leftover `cert-app/` dir (confirm first)

---

### Date: 2026-08-08

### Completed
- **Phase C State Verification:** Audited Cert Platform implementation against `api-endpoints.md` v1.4 (Final). Found Events/Attendees groups **partially implemented** — not complete as previously reported.
- **CertificateTemplateController:** ✅ Complete (5 endpoints, OpenAPI, tests).
- **CertificateController:** ✅ Complete (14 endpoints, OpenAPI, tests).
- **PdfService + PlaceholderResolver:** ✅ Complete.
- **Docker + Swagger Infrastructure:** ✅ Complete.
- **All Models & Migrations:** ✅ Complete.

### Corrected Status — Events & Attendees
| Resource Group | Spec Endpoints | Implemented | Missing |
|----------------|----------------|-------------|---------|
| **Events** | 13 (§5.1) | 6 (CRUD + stats stub) | 7: clone-template, clone-email-template, bulk-issue, reissue, revoke-expired (count+action), issue-completed |
| **Attendees** | 8 (§5.2) | 0 | All 8 endpoints |

**EventController** has basic CRUD + stats stub (mock data). No OpenAPI attributes. Routes not registered.
**AttendeeController** does not exist.
**Tests:** Only basic Unit tests for Event CRUD. No Feature tests for advanced endpoints, no Attendee tests.

### In Progress
- **Deferred to Auth Phase:** SSO + `jwt.auth`/`jwt.endpoint` middleware (decision #20/D9)
- **Deferred to Service Phase:** QR code generation, email sending services
- **Active Work:** Completing Events & Attendees resource groups per spec

### Next Action
- [ ] **Complete EventController:** Add 7 missing endpoints + fix `stats()` + OpenAPI on all 13 methods
- [ ] **Create AttendeeController:** All 8 endpoints with OpenAPI
- [ ] **Register routes** in `routes/api.php`
- [ ] **Write Feature tests** for all 21 endpoints
- [ ] **Run tests** and verify

### Backlog / Known Gaps
- Previous session notes (2026-08-07) overstated Events/Attendees completion — corrected here.

---

### Date: 2026-08-06

### Completed
- **Resolved Q-2..Q-7 (Cert Phase A gate)** — see Cert Platform section above for outcomes.
- **Bumped `api-endpoints.md` → Draft v1.3** (Q-6/Q-7 synced): `certificate_number_pattern` is required + user-configurable, must contain `####` (no default); `/events/{id}/attendees/import` accepts a **JSON payload** (CSV is a UI concern).
- **Bumped `legacy-e-cert-integration.md` → Draft v1.1** (all Qs resolved in §13): D8 reframed (API-enforced JWT-cookie model), seed groups `cert-admin`/`cert-staff`/`cert-user`, `/my/profile` out of scope.
- **CSR decision (2026-08-06):** checked `D:\loa\e-cert\specs\` v2.0 — supersedes D8 with a **client-side SPA**; user chose **CSR wins**. Rewrote `legacy-e-cert-integration.md` → **Draft v2.0** (D8 superseded in §5; §3 SPA; §6 in-memory session + parse-only client JWT + route guard; §8 server actions deleted → typed client API; §10 no secrets env, adds `NEXT_PUBLIC_CERT_TENANT_SLUG`; §12 phases D/E reworded; §13 Q-2=CSR, R-4=XSS/in-memory risk). Synced to `D:\loa\e-cert\legacy-e-cert-integration.md` (D7). Fixed `e-cert/specs` stale bits (seed groups `cert-admin`/`cert-staff`/`cert-user`, attendee import = JSON payload, CSV parse stays client-side).
- **Phase A COMPLETE (2026-08-06):** user confirmed decision #17 (Cert-proxied refresh/logout) and dashboard stats at `read` (with ownership note). Promoted `api-endpoints.md` → **Final v1.4** (§9.2 SSO URL → `/sso/login`, §9.9 `/access` optional, §5.7 dashboard ownership note, decision #17 confirmed, example numbers → `CERT-0001`, **#20 auth deferred**) and `legacy-e-cert-integration.md` → **Final v2.1** (§7.2 dashboard ownership note, §12 Phase A complete; **D9 auth deferral**, C-Auth phase; Auth-provisioning sections refactored to side-notes). D7 copy re-synced.

### In Progress
- **Phase B (next):** Auth readiness — redirect allowlist, cert catalog import (Appendix A), **provision** `cert-admin`/`cert-staff`/`cert-user` groups + grants (per `cert-readiness.md`; production not seeded — local-only `LocalCertReadinessSeeder`)

### Next Action
- [x] **Phase A — COMPLETE 2026-08-06:** `api-endpoints.md` v1.4 and `legacy-e-cert-integration.md` v2.1 → **Final** (incl. #20 auth deferral, D9, side-note refactor)
- [ ] **Phase B:** Auth readiness — redirect allowlist incl. `https://e-cert.vercel.app`, cert catalog import (Appendix A), **provision** `cert-admin`/`cert-staff`/`cert-user` groups + grants (per `cert-readiness.md`; production not seeded — local-only `LocalCertReadinessSeeder`)
- [x] **Phase C scaffold — CREATED 2026-08-06:** Laravel 12 app at `assemblies/loa-cert-platform/cert-app/` (directory structure, composer.json, app config, core models `Organization`/`Event`/`CertificateTemplate`/`Certificate`/`EventAttendee` + migrations). Next: implement the unauth domain CRUD slice (decision #20 / D9: `jwt.auth`/`jwt.endpoint` + SSO callback/refresh/logout deferred to a later auth phase)

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
| 2026-08-06 (8) | **Phase C scaffold created** — Laravel 12 app at `assemblies/loa-cert-platform/cert-app/` (directory structure, composer.json, app config, core models `Organization`/`Event`/`CertificateTemplate`/`Certificate`/`EventAttendee` + migrations) | Implement the unauth domain CRUD slice (events/attendees/templates/certificates + tests) |
| 2026-08-07 | Reconciled the local Docker path: documented **`LocalCertReadinessSeeder`** (auto-runs on local `db:seed` via `DatabaseSeeder` non-prod guard; `cert-app` tenant @ `localhost:9001` + groups) as the sanctioned local provisioning in `cert-readiness.md` → **Final v0.4**. Production seeding stays manual-only per the 2026-08-06 decision. | Implement the unauth domain CRUD slice |
| 2026-08-07 (2) | **User Account Activation spec** written + promoted to **Final v1.0** (`user-account-activation.md`): replaces self-registration with backend-provisioned activation flow (pending status, activation tokens, admin resend). Committed + pushed. | Implement user account activation per spec |
| 2026-08-08 | Added centralized Seq log server (datalust/seq) to root docker-compose.yml; both Auth and Cert app services now emit structured logs to Seq on port 5341. Created config/logging.php in both assemblies with a seq channel activated when SEQ_URL env var is present. Auth platform runbook updated with Seq instructions. | Verify Seq integration logs flow correctly; update Cert runbook |
| 2026-08-10 | **Events & Attendees complete** (unauth CRUD slice): 13 event + 8 attendee endpoints (incl. stats, clone-template, bulk-issue, reissue, issue-completed, revoke-expired, import, destroy-with-cert, delete-preview, file-data), routes, OpenAPI, feature tests. Full suite green (91/334). Fixed seq/PDF/delete/expire/scope bugs surfaced by tests; added composer dev deps; renamed `database/Migrations`→`database/migrations`; removed dead Api controller. NOT committed. | Commit Events/Attendees work; remove `cert-app/` leftover |
| 2026-08-11 | **Auth platform SSO implemented** (`/sso/login`, `/sso/register`, `/redirect`); fixed `EncryptionService::decrypt()` padding bug; `SsoAuthTest` (15 tests); full auth suite 210/498 green. FRONTEND-INTEGRATION.md updated. **Cert endpoint verification:** 48/50 domain endpoints fully implemented; 2 stubs (QR), 1 missing (email). |
| 2026-08-24 | **Admin UI overhaul + docs/prod wiring (auth)**: breadcrumbs everywhere, link-text actions, quick-action tiles (`4ed2e80`,`8d40f6b`); password API auth model documented; JWT TTL reverted to 15m per policy (`37fcbb7`); cPanel DB names/users wired (`d031994`); DEPLOY.md hardened; HeidiSQL §14; `.env.cpanel` templates gitignored. **Cert/e-cert:** runtime cache shards untracked; template visibility spec authored, reviewed, promoted to **Final v1.1** in e-cert repo. | Implement template visibility in loa-cert-platform |
| 2026-08-24 (2) | **Template visibility IMPLEMENTED** (`9904746`): migration + model owner-set helpers; templates store/update/index/show per spec; clone endpoints + event references guarded; 23 new tests; fixed latent `jwt_claims.sub` bug + inverted endpoint-policy unit test. Suite 168/557 green. Swagger regenerated. Trackers updated. | Phase D e-cert auth swap; e-cert UI badge/toggle with Phase E/F |
| 2026-08-24 (3) | **Post-reset redirect implemented (auth)** per web-ui.md v1.3 §4.3a: forgot-password web+API accept allowlisted `redirect` → emailed link → hidden field → success redirects to tenant app (fallback `/login`); shared `safeRedirectUrl()` on base Controller (WebAuthController delegates). 9 new tests; suite 219/528 green, committed `28f152e`. **Tracker correction:** e-cert retrofit D–H already complete (stale notes said "Phase D next") — source of truth `D:\loa\e-cert\whats-next.md`; open gap = cert org seeder | Cert org seeder; auth/cert deploy |
| 2026-08-24 (4) | **Admin UI rename (`9dde135`):** "Effective Permissions" → "SSO Platform Permissions" on user detail + tenant group detail pages; permission checkboxes now show key + seeded description (fallback "No description available."); `group-permission-management.md` synced. **Spec wording fix:** `permission-resolution.md` rule 2 corrected from "deny wins *within* a group" to **deny wins *across* groups** — matches `AuthorizationService::getPermissions()` (granted minus denied, any applicable group deny strips the key) | Auth/cert deploy |
| 2026-08-24 (5) | **Platform-admin semantics documented** (`web-ui.md` §4.1): admin = membership in `loa-auth-admin` only (no privileged email; `ADMIN_EMAIL` seeding is bootstrap); any active user incl. tenant users can be promoted via Admin UI. Added /login vs /sso/login admission matrix. Corrected stale claim "/login is admin-only": code (`WebAuthController::login`) actually admits non-admin tenant members with valid tenant `?redirect=` via token-delivery flow; §16.3 verification rows synced. Verified live DB: `alamoninofrancisco@gmail.com` = cert-admin only, NOT platform-admin | Auth/cert deploy |
