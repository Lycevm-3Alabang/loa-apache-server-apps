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

### Key Architecture Decisions
- **Consultation app**: SSO-only, LOA domains only (`@lyceumalabang.edu.ph`, `@itmlyceumalabang.onmicrosoft.com`)
- **Cert app**: Any email allowed (must have record in `event_attendees` table)
- **Permission model**: Both group-level AND individual user-level permissions supported (user overrides win)
- **SSO flow**: Auth Platform issues JWT with `permissions` array → apps check permissions on each endpoint
- **Tenant group membership**: Users MUST be tenant members before being added to tenant groups (enforcement needed — not yet in code)
- **Permission discovery**: Currently manual; need a permission registry where apps declare their endpoint-to-permission mapping

### In Progress
- None — all specs created, awaiting implementation

### Next Action
- Implement Auth Platform group/permission management API + admin UI (from `group-permission-management.md`)
- Add domain restriction to Auth Platform registration (LOA domains only)
- Add permission registry for apps to declare their permission requirements
- Implement Cert Platform SSO integration (from `web-ui.md` and `README.md` Section 11-12)

### Backlog / Known Gaps
- **Tenant group membership enforcement**: `AuthorizationService::addToGroup()` doesn't check tenant membership — needs validation that user is tenant member before adding to tenant-scoped group
- **Permission registry**: Apps need to declare which permissions map to which endpoints (currently manual)
- **Cert Platform event_attendees check**: External users must exist in `event_attendees` table before registering
- **Admin create user** (spec pending): admin manually creates user account without self-registering; routes `GET /admin/users/create` + `POST /admin/users`; form: email, name, password (optional → auto-generate), status; permission `users.manage`; spec to be added to `admin-dashboard.md` as v3
- Education Domain + Business Contexts still use flat structure (no `entities/`, `events/`, `rules/` subdirs)
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
| 2026-08-02 | Cert Platform SSO spec (README.md §11-12), web-ui.md created, group-permission-management.md created, architecture decisions (SSO-only for LOA, self-hosted for external, permission-based role mapping) | Implement Auth Platform group/permission API + admin UI, add permission registry |
