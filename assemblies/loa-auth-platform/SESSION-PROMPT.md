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

### Date: 2026-08-27

### Completed
- **auth-tenant.md spec promoted to Final v1.0** — covers: auth tenant (slug `auth`, read-only, badge), search-first "Add Member" pattern (multi-select, batch add) on all 3 surfaces, CSV import (editable preview, tenant-scoped groups, set-password email), Create User flow (creates user + emails set-password link), Dashboard "Platform Groups" shortcut (admin-only).

### In Progress
- **§12 implementation** — all 6 phases done, committed + pushed (`f8cd6d9`). Tests not yet re-run after 9-fix round.

### Next Action
- [ ] Run tests: `docker compose exec auth-app php artisan view:clear && docker compose exec auth-app php artisan test`
- [ ] Run lint: `docker compose exec auth-app php vendor/bin/pint --test`
- [ ] Implement auth-tenant.md per Final spec (auth tenant seeder, search-first multi-select, CSV import, create user + set-password flow, dashboard shortcut)

### Backlog / Known Gaps
- Kernel specs remain Draft (unchanged); Auth deployment still deferred

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
| 2026-08-24 | **Admin UI overhaul** (commits `4ed2e80`, `8d40f6b`): breadcrumbs on every page, 12 "Back to" buttons removed, link-text row actions, tenant/user/group detail quick-action tiles, ghost-button white-text fix | Implement template visibility (cert platform, spec Final) |
| 2026-08-24 (2) | **Docs + prod wiring** (`37fcbb7`, `d031994`): password API auth model documented; JWT TTL reverted to 15m per policy; cPanel DB names/users wired into DEPLOY/env docs; DEPLOY.md hardened (MAIL_*, backup/rollback, prerequisites); HeidiSQL §14; `.env.cpanel` templates gitignored (`7deac3a`) | Phase D e-cert auth swap |
| 2026-08-24 (3) | **Template visibility implemented in cert platform** (`9904746`, cross-assembly per Final spec): 23 new tests, suite 168/557 green; latent `jwt_claims.sub` bug fixed; inverted endpoint-policy unit test corrected. Cross-boundary record updated in PROJECT_UPDATES.md | Phase D e-cert auth swap; e-cert UI badge/toggle in Phase E/F |
| 2026-08-24 (4) | **Post-reset redirect implemented per web-ui.md v1.3 §4.3a**: forgot-password (web+API) accepts allowlisted `redirect`, embedded into emailed link; reset form carries hidden field; success redirects to app (fallback `/login`); shared `safeRedirectUrl()` moved to base Controller (WebAuthController::resolveRedirect delegates). 9 new tests; suite 219/528 green | Commit post-reset redirect work |
| 2026-08-24 (5) | **Forgot page return-to-app link** per web-ui.md v1.4 §4.2 UI: `/forgot-password?redirect=` shows validated "Return to app" link (else "Back to sign in"); hidden field carries redirect through POST into the emailed link; validation-error path re-renders via GET with sanitized redirect so the link survives typos. 3 new tests; suite 222/538 green | Phase D e-cert auth swap |
| 2026-08-26 | **Admin dashboard home implemented** per `admin-dashboard-home.md` v1.0 Final: controller data assembly (`adminZoneData`, stat cards, attention queue, activity feed), admin-zone Blade partial (stat strip, attention list, activity table, quick actions), `?status=pending` fix, dashboard `@include` gated by `$isAdmin`. Zero-JS, H4 fail-degrade, link-out only | Run tests + lint; Phase D e-cert auth swap |
| 2026-08-26 (2) | **§12 Group-Permission-Management implemented** (all 6 phases): spec promoted to Final v3.0; AuthorizationService I1/M8 guards + `addToGroupTransactional()`; 5 new routes, 2 removed, 2 old methods deleted, 6 new controller methods, 2 modified with M6 guards; user detail rewrote to read-only, group-members rewrote with two-tier search + remove, new member-remove-confirm interstitial, platform group show scoped; `auth:repair-i1-violations` command; 25 new tests; fixed search table name + M6 guards + audit test routes | Run tests + lint; auth-tenant.md implementation |
| 2026-08-27 | **auth-tenant.md spec promoted to Final v1.0**: auth tenant (slug `auth`, read-only, badge), search-first Add Member (multi-select, batch add) on all surfaces, CSV import (editable preview, tenant-scoped groups), Create User + set-password email flow, Platform Groups dashboard shortcut | Implement auth-tenant.md per Final spec |
| 2026-08-27 (2) | **auth-tenant.md fully implemented** (all 8 items): LocalAuthTenantSeeder, Tenant.isPlatform() helper, auth tenant read-only (UI + server guards), "Platform" badge, CSV multi-group support, Create User + set-password flow (migration, model, controller, mail, views, routes), multi-select search on all 3 surfaces, dashboard Platform Groups shortcut. SQL scripts created/updated (migration + consolidated cPanel install + local dev seed). | Run tests + lint |
| 2026-08-27 (3) | **Test fixes** (4 failures): AdminAuditLogTest flash message, TenantGroupMembershipTest duplicate error, TenantMemberImportTest additive behavior, TenantMemberPickerTest button text. **PasswordSetToken HasUuids fix** (missing trait caused SQL 1364 on create). **SQL consolidation**: merged cpanel-auth-db-install-fixed.sql → cpanel-auth-db-install.sql (single file, upfront DROP pattern); fixed auth tenant INSERT (status enum, not is_active). **User Management improvements**: removed Import Users button, added "Pending" to status filter, added Groups column with linked badges, eager-loaded userGroups. | Run tests + lint; commit + push |

---

## Anti-Scope Rules (Auth Platform work only)

| Rule | Detail |
|------|--------|
| No consumer app code here | Cert/Consult implementations belong to their own assemblies |
| No direct consumer DB access | Consumer apps own their DB; Auth data is consumed via Auth API / JWT claims only |
| No business logic in the assembly | Auth Platform wires identity/auth/admin; domain logic lives in `kernels/`, `domains/`, `business-contexts/` |
| Specs before code, always | No implementation until the governing spec is Final (AI-RULES.md Rule 0) |
| No auto-pilot | Confirm every significant action with the user (AI-RULES.md §13, AI-GUIDE.md) |
