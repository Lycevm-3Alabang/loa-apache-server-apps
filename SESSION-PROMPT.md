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
- **Business Contexts refactored**: Removed 7 automotive template contexts (commercial, crm, finance, fleet, inventory, procurement, workshop); restructured 3 LOA contexts to use `entities/` subdirectories (certificate, consultation, evaluation)
- **Data-Driven Permission Policy spec** created and promoted to Final (`kernels/identity/entities/data-driven-permission-policy.md`):
  - JSON import format: `permissions.json` per app (app, version, routes[] with claims + precedence + filter) — declares which endpoints need guarding
  - Import populates `route_policies` table; Auth Platform admin UI manages policy afterward
  - Claims vocabulary: `claims` table (resource + action + scope); standard actions `read`/`write`/`admin`; filters `all`/`author`/`scope`/`none`
  - Group claims (`group_claims` with scope_type/scope_id) + user claim overrides (`user_claim_overrides`, overrides win)
  - JWT carries `permissions` + `scopes` claims
  - App-side enforcement: gate (claim ∈ route policy) + filter (apply declared filter type)
  - `permission-registry.md` and `permission-claims.md` marked SUPERSEDED
- **Cert Platform SSO spec** (`assemblies/loa-cert-platform/README.md` updated):
  - Section 10: Added `POST /api/v1/auth/callback` to API surface
  - Section 11: SSO Redirect and Callback spec (flow overview, redirect to Auth Platform, callback endpoint, encryption contract, session establishment, logout, security requirements, Auth Platform config reference)
  - Section 12: Frontend Implementation Example (TypeScript callback handler, Laravel callback controller, EncryptionService, route registration, config)
- **Cert Platform web-ui.md** created (15 sections):
  - SSO callback flow (fragment detection, extraction, backend callback)
  - Token lifecycle (storage in memory + httpOnly cookie, refresh flow, expiry detection)
  - SSO group and permission mapping (permission-based role resolution using `cert.*` permissions)
  - Return-to-URL routing (sessionStorage capture, post-auth redirect)
  - Auth guard (route protection, initialization sequence, silent refresh)
  - HTTP client configuration (Axios interceptor pattern)
  - Pages, error pages, security checklist, anti-patterns
- **Auth Platform group-permission-management.md** created (11 sections):
  - API surface: Group CRUD, Group Permissions, User Groups, User Permission Overrides
  - Admin UI: User detail page, Group list page, Group detail page
  - Route summary (API + Admin Web)
  - Implementation inventory (5 new files, 5 modified files)
- **admin-dashboard.md** updated — added v4 scope reference to group-permission-management.md
- **Architecture decision confirmed**: SSO-only for LOA users, self-hosted for external users on cert app
- **Group/permission management implemented**: GroupController, UserGroupController, 3 Blade views, routes — all verified against spec
- **Admin create user implemented** (v3): WebAdminController::create/store, create.blade.php, routes — spec already Final in admin-dashboard.md §9

### Key Architecture Decisions
- **Consultation app**: SSO-only, LOA domains only (`@lyceumalabang.edu.ph`, `@itmlyceumalabang.onmicrosoft.com`)
- **Cert app**: Any email allowed (must have record in `event_attendees` table)
- **Permission model**: Claims-based, data-driven — policy stored in DB, managed via Auth Platform admin UI, apps consume via JWT `permissions` + `scopes`
- **Guard surface discovery**: Each app ships `permissions.json` declaring its guarded endpoints + claims; Auth Platform imports it into `route_policies`
- **SSO flow**: Auth Platform issues JWT with `permissions` array → apps check claims on each endpoint
- **Tenant group membership**: Users MUST be tenant members before being added to tenant groups (enforcement needed — not yet in code)

### In Progress
- None

### Next Action
- Implement data-driven permission policy in loa-auth-platform:
  - New tables: `claims`, `route_policies`, `group_claims`, `user_claim_overrides`
  - JSON import command for `permissions.json` (per app)
  - Admin UI: claims, route policies, group claims, user overrides
  - JWT `permissions` + `scopes` claims
- Create `permissions.json` for each app (cert, consult, auth)
- Implement Cert Platform SSO integration (from `web-ui.md` and `README.md` Section 11-12)

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
