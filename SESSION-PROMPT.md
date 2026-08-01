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
6. kernels/identity/rules/token-lifecycle.md  - the last spec we touched (if still relevant)

Then:
- Summarize the current state of each layer (kernels, domains, contexts, services, assemblies)
- Summarize Phase 1-4 implementation status
- List what's Done, what's In Progress, what's Backlog
- Identify the NEXT action item from "Last Session Notes"
- Do NOT write any code until a Final spec exists
```

---

## Last Session Notes

### Date: 2026-08-01

### Completed
- **Admin dashboard v2 implemented** (`admin-dashboard.md` promoted to Final):
  - Tenant CRUD: list (`GET /admin/tenants`), create form (`GET /admin/tenants/create`), store (`POST /admin/tenants`), show (`GET /admin/tenants/{tenant}`), suspend/activate (`POST /admin/tenants/{tenant}/status`)
  - Tenant groups: list + create (`GET/POST /admin/tenants/{tenant}/groups`), per-group permission grant/revoke (`POST /admin/tenants/{tenant}/groups/{group}/permissions`)
  - Tenant members: add/remove (`POST /admin/tenants/{tenant}/members` with `action=add|remove`)
  - Controller: `WebAdminController` v2 methods (tenantsIndex/Create/Store/Show/Status/Groups/GroupsStore/GroupsPermissions/MembersStore)
  - Views: `admin/tenants/index.blade.php`, `create.blade.php`, `show.blade.php`, `groups.blade.php`
  - Admin layout updated with Tenants nav link
  - CSS additions for forms, detail cards, permission grid, inline forms
- **Identity Kernel v3.0 tenancy fully implemented** (previous session): migrations, TenantService, tenant-scoped AuthorizationService, `jwt.tenant`/`web.admin` middleware, login destination
- **`database.sql` rebuilt from migration schema** (previous session): verified structural parity, clean import
- Regenerated `loa-auth-dist/` (SQL + all v2 views/controllers)
- All PHP lints pass; verified pages render (login, tenants list, create form, show, groups)
- PROJECT.md + SESSION-PROMPT.md updated

### In Progress
- None — admin dashboard v1 + v2 complete

### Next Action
- Deploy current auth release (full admin dashboard + tenancy), OR start Phase 2 (Consult App)

### Backlog / Known Gaps
- **Admin create user** (spec pending): admin manually creates user account without self-registering; routes `GET /admin/users/create` + `POST /admin/users`; form: email, name, password (optional → auto-generate), status; permission `users.manage`; spec to be added to `admin-dashboard.md` as v3
- Education Domain + Business Contexts still use flat structure (no `entities/`, `events/`, `rules/` subdirs)
- `JWT_SECRET` not configured (env) — required before deploy
- Production environment: cPanel cron for `schedule:run`, MySQL host, MAIL SMTP credentials
- No-terminal deploy requires uploading prebuilt `vendor/` (pure-PHP deps; safe cross-platform)

### Open Questions
- None pending

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
