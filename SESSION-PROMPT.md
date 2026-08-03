# SESSION PROMPT

## How to Use

1. **Starting a new session:** Paste the `## Startup Prompt` block below verbatim into the first message.
2. **Ending a session:** Update the `## Last Session Notes` section so the next session knows exactly where to pick up.

---

## Startup Prompt

Paste this block into the first message of a new session:

```
Read these files IN ORDER and report your understanding of where we left off:

1. AGENT.md        - mandatory spec-first rules
2. AI-GUIDE.md     - architecture + Step 0 (spec check)
3. AI-RULES.md     - coding rules + Rule 0 (specs before code)
4. PROJECT.md      - project tracker: current status of every layer and phase
5. SESSION-PROMPT.md - "Last Session Notes" section = exactly where we stopped
6. assemblies/loa-auth-platform/group-permission-management.md - next spec to implement
7. assemblies/loa-cert-platform/web-ui.md - cert platform frontend spec
8. assemblies/loa-cert-platform/README.md - cert platform SSO callback contract

Then:
- Summarize the current state of each layer (kernels, domains, contexts, services, assemblies)
- Summarize Phase 1-4 implementation status
- List what's Done, what's In Progress, what's Backlog
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until a Final spec exists
```

---

## Last Session Notes

### Date: 2026-08-02

### Completed
- **Data-Driven Permission Policy spec** finalized (`data-driven-permission-policy.md` → Final v1.0):
  - JSON import format: `{app, version, routes[]}` with method/path/claims (key, precedence, filter)
  - Import populates `route_policies`; admin UI manages afterward
  - Tables: `claims`, `route_policies`, `group_claims`, `user_claim_overrides`
  - JWT carries `permissions` (claim keys) + `scopes` (from group_claims)
  - `permission-registry.md` and `permission-claims.md` marked SUPERSEDED
- **Auth Platform implementation** of data-driven permission policy:
  - 4 migrations: `claims`, `route_policies`, `group_claims`, `user_claim_overrides`
  - 4 models: `Claim`, `RoutePolicy`, `GroupClaim`, `UserClaimOverride`
  - `PermissionPolicyService`: `resolveUserClaims()`, `resolveUserScopes()`, `authorize()`
  - `ClaimPolicyMiddleware`: dynamic route policy lookup + claim + filter check
  - `ImportPermissions` command: `php artisan permissions:import {app}` reads `permissions.json`
  - `PermissionPolicyController`: admin API CRUD for claims, route policies, group claims, user overrides + `/authorize` test endpoint
  - `IdentityService` updated: JWT now includes `claims` (resolved claim keys) and `scopes`
  - `bootstrap/app.php`: registered `jwt.claim-policy` middleware alias
  - `routes/api.php`: added `v1/admin/permissions` API endpoints (claims, policies, group-claims, user-overrides, authorize)
- **Business Contexts refactored**: Removed 7 automotive template contexts; restructured 3 LOA contexts to use `entities/` subdirectories
- **Cert Platform SSO spec** (`assemblies/loa-cert-platform/README.md` updated)
- **Cert Platform web-ui.md** created (15 sections)
- **Auth Platform group-permission-management.md** created (11 sections)
- **admin-dashboard.md** updated — added v4 scope reference
- **Architecture decision confirmed**: SSO-only for LOA users, self-hosted for external users on cert app
- **Group/permission management implemented**: GroupController, UserGroupController, 3 Blade views, routes — all verified against spec
- **Admin create user implemented** (v3): WebAdminController::create/store, create.blade.php, routes — spec already Final in admin-dashboard.md §9

### Key Architecture Decisions
- **Consultation app**: SSO-only, LOA domains only (`@lyceumalabang.edu.ph`, `@itmlyceumalabang.onmicrosoft.com`)
- **Cert app**: Any email allowed (must have record in `event_attendees` table)
- **Permission model**: Claims-based, data-driven — policy stored in DB, managed via Auth Platform admin UI, apps consume via JWT `permissions` (claim keys) + `scopes`
- **Guard surface discovery**: Each app ships `permissions.json` declaring its guarded endpoints + claims; Auth Platform imports it into `route_policies`
- **SSO flow**: Auth Platform issues JWT with `permissions` (claim keys) + `scopes` → apps check claims on each endpoint via `ClaimPolicyMiddleware`
- **Tenant group membership**: Users MUST be tenant members before being added to tenant groups (enforcement needed — not yet in code)

### In Progress
- (none — tenant endpoint catalog + group endpoint grants implementation complete)

### Next Action
- [ ] Create `permissions.json` per app (cert, consult, auth)  [carried over]
- [ ] Implement Cert Platform SSO integration  [carried over]

### Backlog / Known Gaps
- **Tenant group membership enforcement**: `AuthorizationService::addToGroup()` doesn't check tenant membership — needs validation that user is tenant member before adding to tenant-scoped group
- **Cert Platform event_attendees check**: External users must exist in `event_attendees` table before registering
- `JWT_SECRET` not configured (env) — required before deploy
- Production environment: cPanel cron for `schedule:run`, MySQL host, MAIL SMTP credentials
- No-terminal deploy requires uploading prebuilt `vendor/` (pure-PHP deps; safe cross-platform)

### Open Questions
- Should Auth Platform auto-create preset groups when a new tenant is created?
- How to handle password sync if user changes password on one system (Auth Platform vs e-cert)?
- Should the permission registry be a JSON config file per app, or a database table?

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
| 2026-08-02 | Read AGENT.md, AI-GUIDE.md, AI-RULES.md, PROJECT.md, group-permission-management.md, cert web-ui.md, cert README.md, tenant-endpoint-catalog.md, permission-resolution.md, data-driven-permission-policy.md, tenancy.md, README.md, user-group.md, AuthorizationService.php, RoutePolicy.php; summarized all layers + Phase 1-4 status; created `tenant-group-endpoint-grants.md` spec (Draft→Final); updated SESSION-PROMPT.md | Implement tenant-endpoint-catalog.md + tenant-group-endpoint-grants.md in code
| 2026-08-02 | Added Admin UI §6 to `tenant-endpoint-catalog.md` (endpoint catalog list, create form, bulk import, validate, delete with force) and Admin UI §8 to `tenant-group-endpoint-grants.md` (group endpoint grants page, user endpoint overrides page); updated Implementation Inventory and Dependency References in both specs; updated SESSION-PROMPT.md | Build Blade views for tenant endpoint catalog + group/user endpoint grants |
| 2026-08-02 | Implemented tenant endpoint catalog + group endpoint grants: 3 migrations, 3 models, EndpointGrantController, ClaimPolicyMiddleware extension, GET /api/v1/auth/access endpoint, admin web + API routes, 3 Blade views. Fixed ImportPermissionsCommandTest (Artisan::call() to resolve SQLite :memory: isolation). All 143 tests pass. | Create permissions.json per app; implement Cert Platform SSO |
