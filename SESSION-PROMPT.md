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

### Date: 2026-08-03

### Completed
- **Group priority implemented** (code complete, all 143 tests pass):
  - Migration `2026_08_03_000023_add_priority_to_user_groups_table.php` adds `priority` column (int, default 10)
  - `UserGroup` model: `priority` added to `$fillable`
  - `PermissionPolicyService::resolveEffectiveLevelForEndpoint()` rewritten — sorts grants by group priority, lowest value wins, `deny` only on priority ties
  - Admin UI: priority field in group create forms (platform + tenant), priority column in groups index, priority shown in group detail
  - `WebAdminController`: `groupsStore` + `tenantsGroupsStore` validate priority
  - `UserGroupFactory`, `database.sql` seed, `AdminGroupsTest` all updated
- **Access Config Import/Export spec written** (`access-config-import-export.md` Draft v1.0):
  - JSON schema for groups (with priority), grants, user overrides
  - Template download, export, import (preview + confirm) API contracts
  - Import logic: group upsert by name, grant upsert, "none" level deletes rows
  - Admin UI: download template, export, import dialog with preview

### In Progress
- (none)

### Next Action
- [ ] Implement Access Config Import/Export (`AccessConfigController`, routes, admin UI)
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
| 2026-08-02 | Implemented tenant endpoint catalog + group endpoint grants: 3 migrations, 3 models, EndpointGrantController, ClaimPolicyMiddleware extension, GET /api/v1/auth/access endpoint, admin web + API routes, 3 Blade views. Fixed ImportPermissionsCommandTest (Artisan::call() to resolve SQLite :memory: isolation). All 143 tests pass. Clarified: permissions.json per app not needed — bulk import API already accepts the JSON format as payload. | Implement Cert Platform SSO |
| 2026-08-03 | Group priority resolution spec'd: `user_groups.priority` (int, default 10, 1 = highest); `tenant-group-endpoint-grants.md` → Final v1.1 (§3.3 Group Priority + §4 algorithm — highest-precedence wins, `deny` only on priority ties); `user-group.md` + `permission-resolution.md` updated. Endpoint catalog admin UI navigation fixed + `tenant-endpoint-catalog.md` → Final v3.2 (web/API split, entry point). | Implement group priority in code |
| 2026-08-03 | Implemented group priority: migration, model, resolution logic, admin UI, tests (143 pass); committed + pushed. Wrote `access-config-import-export.md` spec (Draft v1.0) — JSON template download, export, import with preview/confirm for groups+grants+overrides. | Implement access config import/export, or Cert SSO |
