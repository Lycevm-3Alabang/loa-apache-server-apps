# SESSION PROMPT — LOA Auth Platform

> Assembly-scoped session prompt for `assemblies/loa-auth-platform/`. This complements (does not replace) the repo-wide `PROJECT_UPDATES.md` at the workspace root.

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.
3. **Scope rule:** This prompt governs **Auth Platform work only** (identity, tenancy, permissions, admin dashboard, web UI, deployment). Do not pull in Cert/Consult implementation tasks unless the note explicitly says so.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AI-RULES.md                       - mandatory spec-first rules (Rule 0: no code without a Final spec)
2. AI-GUIDE.md                       - architecture + Step 0 (spec check)
3. PROJECT.md                        - repo tracker: Phase 1 "Auth Service" = this platform's status
4. PROJECT_UPDATES.md (root)         - repo-wide cross-boundary tracker: decisions/design/changes per platform
5. assemblies/loa-auth-platform/README.md   - assembly scope + API surface
6. assemblies/loa-auth-platform/web-ui.md   - login redirect + forgot/change password flows (Final v1.2)
7. assemblies/loa-auth-platform/admin-dashboard.md - admin dashboard spec (Final, v1 + v2 implemented)
8. assemblies/loa-auth-platform/tenant-group-endpoint-grants.md - level-based grants model (Final v1.1)
9. assemblies/loa-auth-platform/tenant-endpoint-catalog.md - endpoint catalog (Final v3.2)
10. assemblies/loa-auth-platform/access-config-import-export.md - JSON import/export (Final v1.0)
11. assemblies/loa-auth-platform/user-account-activation.md - activation flow replacing self-registration (Final v1.0)
12. assemblies/loa-auth-platform/SESSION-PROMPT.md - this file: "Last Session Notes" = where we stopped

Then:
- Summarize the spec status (Draft vs Final) and what's implemented vs pending
- List what's Done, In Progress, Backlog for the Auth Platform only
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until the governing spec is Final
```

---

## Last Session Notes

### Date: 2026-08-11

### Completed
- **SSO web auth fully implemented** — login, register, redirect splash, admin rejection, encrypted payload path:
  - `WebAuthController`: `ssoLogin`, `ssoRegister`, `showSSOLogin`, `showSSORegister`, `showRedirect` methods
  - Blade views: `sso-login.blade.php`, `sso-register.blade.php`
  - Routes: `/sso/login`, `/sso/register`, `/redirect` (web, no CSRF)
  - Registration restricted to `lyceumalabang.edu.ph` + `itmlyceumalabang.onmicrosoft.com`
  - Admin users rejected from SSO login (redirects to admin dashboard instead)
  - Splash page (`/redirect`) is one-time-use — clears session after reading
- **EncryptionService::decrypt() bug fix** — the old code always appended `==` to the unpadded base64, which only decodes when `len % 4 == 2` (~1/3 of payloads). Fixed to pad to a multiple of 4 correctly. Verified with round-trip test.
- **SsoAuthTest** — 15 tests, 54 assertions covering both encrypted and fragment payload paths, admin rejection, redirect validation, member-only access, one-time splash, registration domain restriction, duplicate email handling.
- **Full auth suite: 210 tests, 498 assertions, all green.**

### In Progress
- **Cert Platform Phase D:** e-cert auth swap (CSR) — now unblocked (SSO entry point exists)

### Next Action
- [ ] Deploy auth to `auth.lyceumalabang.edu.ph` (provision per `cert-readiness.md` Final v0.4)
- [ ] Focus on Cert Platform Phase D — e-cert auth swap (CSR)

### Backlog / Known Gaps
- **Deployment not yet done** — Docker-verified only; `database.sql` rebuilt from migration schema (parity-checked).
- Kernel specs (User, UserGroup, Permission, LoginAttempt, PasswordResetToken, Contracts, Events, Business Rules) remain **Draft** — code is ahead of several kernel specs.
- No-terminal deploy requires uploading prebuilt `vendor/` (pure-PHP deps; safe cross-platform).
- **Centralized Seq logging** (port 5341) now configured in root docker-compose.yml and auth app's config/logging.php — see LOCAL-DEV-RUNBOOK.md for details.
- No logging spec exists for this change — pending spec creation before further logging enhancements.

---

## Session Log 

| Date | Work Done | Next Action |
|------|-----------|-------------|
| 2026-07-31 | Identity events/rules specs; auth controllers; middleware; CORS spec+impl; spec-first mandate | Deploy auth or start Phase 2 |
| 2026-07-31 | RefreshToken entity spec + contract; model + migration + IdentityService wiring (Final) | Auth Web UI or deploy auth |
| 2026-07-31 | Docker local dev (php:8.3, nginx, mysql, mailpit); Rule 7 disable endpoint + Rule 8 pruning; Laravel 12 upgrade; all verified | Auth Web UI or deploy auth |
| 2026-08-01 | Auth Web UI implemented; deployed CORS/Swagger/seeder issues investigated; SQL dump fixed; no-terminal DEPLOY.md; login destination resolution (web-ui v1.2) | Admin dashboard v2 or deploy |
| 2026-08-01 | Tenancy v3.0 + admin dashboard v1 + v2 implemented and verified (migrations 000011–000015, TenantService, tenant-scoped auth, `jwt.tenant`/`web.admin`, login destination matrix, `/admin/users`, tenant CRUD, groups, per-group permissions, members, suspend/activate); `database.sql` rebuilt; `admin-dashboard.md` Final | Deploy, or start Phase 2 (Consult) |
| 2026-08-02 | Data-driven permission policy Final v1.0 (4 migrations, 4 models, PermissionPolicyService, ClaimPolicyMiddleware, ImportPermissions command, JWT claims/scopes, admin endpoints); tests written; SUPERSEDED marks on old registry/claims specs | Run tests and verify |
| 2026-08-02 | Tenant endpoint catalog + group endpoint grants implemented: 3 migrations, 3 models, EndpointGrantController, ClaimPolicyMiddleware extension, `GET /api/v1/auth/access`, admin web + API routes, 3 Blade views; ImportPermissionsCommandTest fixed (SQLite :memory: isolation); **143 tests pass** | Implement Cert Platform SSO |
| 2026-08-03 | Group priority: migration, model, resolution logic, admin UI, tests (**143 pass**); committed + pushed; `access-config-import-export.md` Draft → **Final v1.0** after P0/P1/P2 review | Implement access config import/export |
| 2026-08-03 | Access Config Import/Export implemented: `AccessConfigController`, web + API routes, import Blade view, 3 factories, 29 tests (**all 172 pass**) | Deploy auth or start Cert Platform SSO |
| 2026-08-05 | Cross-boundary: domain correction + allowlists updated for `https://e-cert.vercel.app`; auth referenced from root `PROJECT_UPDATES.md` | Phase B: Cert readiness (catalog import, seed groups), then deploy |
| 2026-08-06 | **Phase B deferred (user decision):** no Cert data baked into Auth seeders/database.sql — a `CertReadinessSeeder` attempt was created then **reverted**. Provisioning will be **manual at deploy-time**. Wrote runbook **`cert-readiness.md` (Draft v0.1)** on branch `docs/cert-readiness-runbook`: `loa` tenant + `redirect_origins`, 48-endpoint Appendix A payload, group creation, full 48-row grant matrix (admin 48 / staff 39 / user 7), verification steps. Payload + matrix parity-checked against `api-endpoints.md` Appendix A | Review + promote `cert-readiness.md` → Final → deploy → provision per runbook |
| 2026-08-06 | **`cert-readiness.md` promoted to Final v0.1** (payload + matrix verified 48/48). Phase B readiness spec is locked; provisioning happens manually at deploy-time per the runbook | Deploy auth → provision per runbook |
| 2026-08-06 | **`cert-readiness.md` → Final v0.2:** added **§8 Local Development** (Docker Compose) — local admin UI at `localhost:8080`, local origin/CORS/redirect table, optional ad-hoc tinker fast path for tenant+groups (not in any seeder, per decision), local verification. §3 cross-ref added; References + Doc Control renumbered to §9–§11 | Deploy auth → provision per runbook |
| 2026-08-06 | **Auth deployment deferred** (user decision 2026-08-06: focus on Cert platform). Auth deploy to `auth.lyceumalabang.edu.ph` can proceed independently when ready; no action needed until then. | Focus on Cert platform Phase C scaffold (unauth domain CRUD) |
| 2026-08-07 | Reconciled local Docker path: **`LocalCertReadinessSeeder`** (runs automatically on local `db:seed` via `DatabaseSeeder` non-prod guard; `cert-app` tenant @ `localhost:9001` + `cert-admin/staff/user` groups) is the sanctioned local provisioning. `cert-readiness.md` → **Final v0.4** (decision note, §8.1, §8.3, table, doc control). Production seeding remains manual-only per the 2026-08-06 decision. | Deploy auth → provision per runbook; Cert Phase C unauth CRUD slice |
| 2026-08-07 (2) | **User Account Activation spec** written + promoted to **Final v1.0** (`user-account-activation.md`): replaces self-registration with backend-provisioned activation flow (pending status, activation tokens, admin resend). Committed + pushed. | Implement user account activation per spec |
| 2026-08-08 | User Account Activation fully implemented (migrations, model, service, views, email template, routes, controllers, tests). Auth Platform work complete for now. Added centralized Seq logging infrastructure. | Focus on Cert Platform Phase C |
| 2026-08-11 | SSO web auth implemented (login/register/redirect/blade views); fixed EncryptionService::decrypt() padding bug; SsoAuthTest (15 tests); full suite 210 tests pass | Deploy auth or Phase D e-cert auth swap |

---

## Anti-Scope Rules (Auth Platform work only)

| Rule | Detail |
|------|--------|
| No consumer app code here | Cert/Consult implementations belong to their own assemblies |
| No direct consumer DB access | Consumer apps own their DB; Auth data is consumed via Auth API / JWT claims only |
| No business logic in the assembly | Auth Platform wires identity/auth/admin; domain logic lives in `kernels/`, `domains/`, `business-contexts/` |
| Specs before code, always | No implementation until the governing spec is Final (AI-RULES.md Rule 0) |
| No auto-pilot | Confirm every significant action with the user (AI-RULES.md §13, AI-GUIDE.md) |
