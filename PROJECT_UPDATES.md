# SESSION PROMPT

## How to Use

1. **Starting a new session:** paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** replace `## Last Session Notes` with where we stopped (date, completed, next action). Keep it to one entry — older notes live in git history.
3. **Cross-boundary record:** `## PROJECT UPDATES` keeps only decisions/design that span assemblies. Per-platform detail lives in each assembly's `SESSION-PROMPT.md`. Delivery is tracked **per platform** — Auth / Cert / Consult ship independently.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AGENTS.md        - sole agent entry: SDD+TDD loop, Rule 0, Rule 0.5, No Auto-Pilot
2. principles.md   - SDD+TDD detail + coding rules (authoritative)
3. platform.md     - LOA architecture + Laravel gotchas
4. PROJECT.md      - project tracker: status of every layer and phase
5. PROJECT_UPDATES.md - this file: shared decisions + per-platform Done/Next + Last Session Notes
6. assemblies/loa-auth-platform/SESSION-PROMPT.md - auth session details (if working on Auth)
7. assemblies/loa-cert-platform/api-endpoints.md - cert endpoint source of truth (if working on Cert)

Then:
- Summarize Done / In Progress / Backlog for the platform in scope
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write code until the governing spec is Final (AGENTS.md Rule 0)
```

---

## PROJECT UPDATES

Durable cross-boundary record only. Detail lives in assembly specs; history lives in git.

### Shared decisions

| Decision | Detail |
|----------|--------|
| App topology | 3 Laravel 12 APIs + 1 Next.js 16 UI (table below) |
| Domains | All APIs on `*.lyceumalabang.edu.ph`; example emails `@lyceumalabang.edu.ph` |
| Spec-first + TDD | No code without a Final spec (`AGENTS.md` Rule 0); SDD sets the contract, TDD explores/refines it (`principles.md` §2b) |
| JWT model | Shared HMAC-SHA256, HS256, `type=access`, local validation — no HTTP per request |
| Cross-app access | JWT local validation + HTTP (Bearer) user lookup; each app owns its DB |
| Identity authority | Auth is sole source of truth; apps are consumers (state changes via Auth API) |
| Permission model | Level-based grants (`<level>:<path>`) per `tenant-group-endpoint-grants.md`, not static keys |
| Consumer allowlist | `AUTH_ALLOWED_REDIRECTS` + `CORS_ALLOWED_ORIGINS` + tenant `redirect_origins` must include every UI origin (currently `https://e-cert.vercel.app`) |
| Governance (2026-09-22) | `AGENTS.md` is the sole agent entry; `AI-RULES.md`/`AI-GUIDE.md` merged into it (+ `principles.md`/`platform.md`) and removed |

| App | Subdomain | Database | Purpose |
|-----|-----------|----------|---------|
| Auth | auth.lyceumalabang.edu.ph | lyceumalabang_auth_db (local: `loa_auth`) | JWT service, users, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Booking + evaluation (spec program **ACTIVE** 2026-09-23) |
| Cert API | cert-api.lyceumalabang.edu.ph | lyceumalabang_e_cert (local: `loa_cert`) | Issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js consumer of Auth + Cert APIs |

Prod DBs/users provisioned in cPanel 2026-08-24 (user `lyceumalabang_auth_admin`; passwords deploy-time only). See each platform's `DEPLOY.md` + `.env.cpanel` template.

### Auth — `assemblies/loa-auth-platform/`

- **Status:** Phase 1 largely implemented, **not deployed**. SSO entry live (`/sso/login`, `/sso/register`, `/redirect`).
- **Final specs (implemented):** `web-ui.md`, `admin-dashboard.md` (v1+v2), `tenant-endpoint-catalog.md` v3.2, `tenant-group-endpoint-grants.md` v1.1, `access-config-import-export.md` v1.0, data-driven permission policy, RefreshToken, `admin-dashboard-home.md`, `group-permission-management.md` v3.0, `auth-tenant.md` v1.0.
- **Done (latest):** §12 group-permission restructure + auth-tenant 8 items (multi-select Add Member, CSV multi-group, Create User + set-password, Platform badge/shortcut); test fixes + `PasswordSetToken` HasUuids fix; SQL consolidation (`cpanel-auth-db-install.sql`).
- **Next:** run tests + lint to verify; commit + push; then `user-account-activation.md` v1.0 implementation. Deploy deferred (user decision — focus on Cert).
- **2026-09-22 — Spec-mirror pass:** Final specs rewritten to match working code (admin-top ordinals, wrapped catalog, auto-attach, deny-deletes, toggle/invalidate/register-cleanup marked unimplemented, web-ui supersession pointers); proper fixes filed DEFERRED in-spec.
- **2026-09-23 — Consult endpoints catalog:** `database/json/consult-endpoints-catalog.json` v1.0 (118 entries, transcribed from consult `api-endpoints.md` Final v1.0 §5) added as bulk-import path for deploy-time provisioning (cert-file precedent).

### Cert — `assemblies/loa-cert-platform/`

- **Status:** C-Auth complete (2026-08-11). All endpoints behind `jwt.auth` + `jwt.endpoint`; SSO trio live. Retrofit phases A–H COMPLETE (e-cert SPA: auth swap, data swap, cleanup, decommission, JWT/audit tests, OpenAPI).
- **Source of truth:** `api-endpoints.md` Final v1.8 (61 gated + 3 domain-public + 3 SSO = 64 domain rows), `legacy-e-cert-integration.md` Final v2.2, `authenticated-endpoints-spec.md` v1.2.
- **Done (latest):** template visibility (commit `9904746`, 23 tests); post-reset redirect (`28f152e`, log-viewer routes); refresh-cookie crash fix (`Cookie::queue()`); 413 JSON handler (419-CSRF handling unevidenced — claim corrected); parametrized dist builds.
- **Known gaps:** local seed has no organization → FK 1452 on template/certificate writes (needs seeder); certificate owner-rule check missing on show/pdf/download (only `MeController` scopes); QR route shape code-vs-spec drift (`/{n}/qr` vs `?certificate_number=`); `test-suite.md` still Draft (says SQLite, actual MySQL `loa_cert_test`).
- **Next:** auth/cert deploy; cert org seeder.

### Consult — `assemblies/loa-consult-platform/` (spec program **ACTIVE** 2026-09-23)

- **Status:** scaffold done + auth-layer port COMPLETE (§11 Steps 1–7 green 2026-09-22) + slices B/C COMPLETE (B1–B5 academic/appointments, C1–C4 evaluations green 2026-09-22) + Phase D link-reads + import-domain + data/audit green 2026-09-23 (`UserLinkTest` 8/8, `ImportTest` 7/7, `DataAuditTest` 8/8 user) + URL-flattening F1+F2 green (flat 104+5 normative, scoped results). Stopped — implementation phase planning needs user yes.
- **Specs:** `api-endpoints.md` Final v2.0 (flat 104+5 green), `auth-integration.md` Final v1.4 (§11 Steps 1–7 landed: config · services trio · JwtMiddleware · catalog 104+5 flattened current, 118+5 was v1.0 history · EndpointPolicy · auth trio · gate routes — suite green 2026-09-22, port COMPLETE), 3 endpoint modules Final v1.0–v1.1 (academic/evaluations v1.1 flat green), `endpoints-admin-import.md` Final v1.1 (Phase D baselines green 2026-09-23; DEC-5 proxy OPEN), `url-flattening.md` Final v1.1 (flat 104+5 per ACC-2; 113+5 was F2 intermediate), `docker-compose-spec.md` Final v1.0, `data-model.md` Final v1.3 (shape contract + §3 Status gate 6/3/17); `test-suite.md` Final v1.1; `consult-readiness.md` Final v1.5 (104+5 pointer); runbooks still Draft.
- **Next:** implementation phase planning (scaffold, domain slices, C-Auth, cutover) — gated; see TODO AFTER FINAL SPECS. Auth JSON deferred to deploy-time.

---

## Last Session Notes

### Date: 2026-09-23

### Completed
- **Tracker reconciliation (user-approved):** `TODO.md` header → ACTIVE 2026-09-23; Phase D deferred markers → SUPERSEDED (Final v1.1 + baselines green); counts → 104+5 normative (`api-endpoints.md` v2.0 §5 Total + `url-flattening.md` ACC-2 + `consult-readiness.md` CON-2 + `config/consult-endpoints.php` 104 gated); 118+5 = v1.0 history, 113+5 = F2 intermediate. `PROJECT.md` Phase 2 synced (auth catalog, admin-import, url-flattening, F1+F2, results 20→15, academic flat). This file synced (ACTIVE date, Status, Specs).
- **Open drift (spec-owned, not edited):** assembly `AGENTS.md` §4 url-flattening 113+5; `url-flattening.md` Document Control 113+5; `endpoints-admin-import.md` Refs config 118; `config/consult-endpoints.php` L131-132 `/admin/audit-logs` unflattened.

### Next Action
- [ ] Implementation phase planning (scaffold, domain slices, C-Auth, cutover) — gated; see TODO AFTER FINAL SPECS. Spec-owned drift fixes need separate yes per Rule 0.
