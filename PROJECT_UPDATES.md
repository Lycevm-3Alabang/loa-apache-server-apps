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

1. AGENT.md        - sole agent entry: SDD+TDD loop, Rule 0, Rule 0.5, No Auto-Pilot
2. principles.md   - SDD+TDD detail + coding rules (authoritative)
3. platform.md     - LOA architecture + Laravel gotchas
4. PROJECT.md      - project tracker: status of every layer and phase
5. PROJECT_UPDATES.md - this file: shared decisions + per-platform Done/Next + Last Session Notes
6. assemblies/loa-auth-platform/SESSION-PROMPT.md - auth session details (if working on Auth)
7. assemblies/loa-cert-platform/api-endpoints.md - cert endpoint source of truth (if working on Cert)

Then:
- Summarize Done / In Progress / Backlog for the platform in scope
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write code until the governing spec is Final (AGENT.md Rule 0)
```

---

## PROJECT UPDATES

Durable cross-boundary record only. Detail lives in assembly specs; history lives in git.

### Shared decisions

| Decision | Detail |
|----------|--------|
| App topology | 3 Laravel 12 APIs + 1 Next.js 16 UI (table below) |
| Domains | All APIs on `*.lyceumalabang.edu.ph`; example emails `@lyceumalabang.edu.ph` |
| Spec-first + TDD | No code without a Final spec (`AGENT.md` Rule 0); SDD sets the contract, TDD explores/refines it (`principles.md` §2b) |
| JWT model | Shared HMAC-SHA256, HS256, `type=access`, local validation — no HTTP per request |
| Cross-app access | JWT local validation + HTTP (Bearer) user lookup; each app owns its DB |
| Identity authority | Auth is sole source of truth; apps are consumers (state changes via Auth API) |
| Permission model | Level-based grants (`<level>:<path>`) per `tenant-group-endpoint-grants.md`, not static keys |
| Consumer allowlist | `AUTH_ALLOWED_REDIRECTS` + `CORS_ALLOWED_ORIGINS` + tenant `redirect_origins` must include every UI origin (currently `https://e-cert.vercel.app`) |
| Governance (2026-09-22) | `AGENT.md` is the sole agent entry; `AI-RULES.md`/`AI-GUIDE.md` merged into it (+ `principles.md`/`platform.md`) and removed |

| App | Subdomain | Database | Purpose |
|-----|-----------|----------|---------|
| Auth | auth.lyceumalabang.edu.ph | lyceumalabang_auth_db (local: `loa_auth`) | JWT service, users, admin dashboard |
| Consult | aces-api.lyceumalabang.edu.ph | loa_consult | Booking + evaluation (**DEFERRED**) |
| Cert API | cert-api.lyceumalabang.edu.ph | lyceumalabang_e_cert (local: `loa_cert`) | Issuance, verification, PDF/QR/email |
| e-cert UI | e-cert.vercel.app | — (Vercel) | Next.js consumer of Auth + Cert APIs |

Prod DBs/users provisioned in cPanel 2026-08-24 (user `lyceumalabang_auth_admin`; passwords deploy-time only). See each platform's `DEPLOY.md` + `.env.cpanel` template.

### Auth — `assemblies/loa-auth-platform/`

- **Status:** Phase 1 largely implemented, **not deployed**. SSO entry live (`/sso/login`, `/sso/register`, `/redirect`).
- **Final specs (implemented):** `web-ui.md`, `admin-dashboard.md` (v1+v2), `tenant-endpoint-catalog.md` v3.2, `tenant-group-endpoint-grants.md` v1.1, `access-config-import-export.md` v1.0, data-driven permission policy, RefreshToken, `admin-dashboard-home.md`, `group-permission-management.md` v3.0, `auth-tenant.md` v1.0.
- **Done (latest):** §12 group-permission restructure + auth-tenant 8 items (multi-select Add Member, CSV multi-group, Create User + set-password, Platform badge/shortcut); test fixes + `PasswordSetToken` HasUuids fix; SQL consolidation (`cpanel-auth-db-install.sql`).
- **Next:** run tests + lint to verify; commit + push; then `user-account-activation.md` v1.0 implementation. Deploy deferred (user decision — focus on Cert).

### Cert — `assemblies/loa-cert-platform/`

- **Status:** C-Auth complete (2026-08-11). All endpoints behind `jwt.auth` + `jwt.endpoint`; SSO trio live. Retrofit phases A–H COMPLETE (e-cert SPA: auth swap, data swap, cleanup, decommission, JWT/audit tests, OpenAPI).
- **Source of truth:** `api-endpoints.md` Final v1.8 (61 gated + 6 public), `legacy-e-cert-integration.md` Final v2.2, `authenticated-endpoints-spec.md` v1.2.
- **Done (latest):** template visibility (commit `9904746`, 23 tests); post-reset redirect (`28f152e`); refresh-cookie crash fix (`Cookie::queue()`); 419-CSRF redirect handler; parametrized dist builds.
- **Known gaps:** local seed has no organization → FK 1452 on template/certificate writes (needs seeder); certificate owner-rule check missing on show/pdf/download (only `MeController` scopes); QR route shape code-vs-spec drift (`/{n}/qr` vs `?certificate_number=`); `test-suite.md` still Draft (says SQLite, actual MySQL `loa_cert_test`).
- **Next:** auth/cert deploy; cert org seeder.

### Consult — `assemblies/loa-consult-platform/` (**DEFERRED** by user)

- **Status:** scaffold done (`HealthTest` green) + academic slice partial (9 models/routes, JWT stubs pass-through). No further implementation until data-model Final (Rule 0 tension noted).
- **Specs:** `api-endpoints.md` Final v1.0, `auth-integration.md` Final v1.2, 3 endpoint modules Final v1.0, `docker-compose-spec.md` Final v1.0; `data-model.md` Draft v1.1 (first-class, blocks resume), `test-suite.md` + runbooks still Draft.
- **Next (when resumed):** promote `data-model.md` v1.1 → Final, re-verify 3 modules vs v1.1, then auth port (middleware+controllers) → slices B/C.

---

## Last Session Notes

### Date: 2026-09-22

### Completed
- **Governance lean merge:** `AGENT.md` rewritten as sole entry (SDD+TDD loop, Rule 0/0.5, No Auto-Pilot, testing, Laravel gotchas); detail migrated to `principles.md` §2b + `platform.md` §15b; `PROJECT.md` references updated.
- **Scope change:** Consult deferred; focus is Auth + Cert alignment.
- **This file consolidated:** startup prompt updated (no AI-RULES/AI-GUIDE); per-platform sections cut to Status/Done/Next; verbose history + session log dropped (git history is the archive).

### Next Action
- [x] Merge done 2026-09-22: `TODO.md`, `README.md`, `build-your-own-app.md`, auth `SESSION-PROMPT.md` reading lists updated; `AI-RULES.md` + `AI-GUIDE.md` deleted. Remaining `AI-RULES/AI-GUIDE` mentions are historical (assembly Final specs) — rule now lives in `AGENT.md`.
- [ ] Auth + Cert spec-vs-code alignment (read-only audit findings → Type A/B/C classification per SDD+TDD)
