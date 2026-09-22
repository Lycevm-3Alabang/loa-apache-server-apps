# AGENTS.md — LOA Auth Platform Agent Contract

> This file is the standing contract for any AI agent working in `assemblies/loa-auth-platform/`.
> It outranks ad-hoc instructions when they conflict. If a request violates
> Section 1, stop and ask instead of proceeding.
> Companion rules: root `AGENTS.md` (sole entry) + `principles.md` (SDD+TDD detail) + `platform.md` (architecture + gotchas). Change journal: `SESSION-PROMPT.md` (assembly session details) + root `PROJECT_UPDATES.md` (Auth section + Last Session Notes).

---

## 1. Working agreements (must-follow, moving forward)

1. **Spec-first, no exceptions.** No code, migration, config, or dependency changes without a
   written spec the user has explicitly marked **Final**. The loop is:
   discuss → write spec → user approves spec as Final → implement exactly the spec.
   SDD writes the contract; TDD tests it against reality.
2. **No auto-pilot.** Never chain beyond what was approved. Finish the approved
   step, report, and stop. Ask before starting the next phase, even if it seems
   "obvious."
3. **Agent does not run CLIs — the user does.** The agent must NEVER execute
   terminal commands (`docker compose`/`php artisan`/`composer`/`git`/builds/deploys, etc. — even read-only ones). The agent
   provides the exact commands; the user runs them and pastes back output.
   Rationale: the user owns the device, build environment, and credentials.
   Docker runs from repo root only (`loa-platform` project); never an assembly-level compose file.
   Tests: `docker compose exec auth-app php artisan test` (+ `php vendor/bin/pint --test`); suites sequentially only.
4. **No breaking changes unless the finalized spec requires them.** JWT shape/claims (`groups` + `permissions` + `tenant`),
   SSO routes (`/sso/login`, `/sso/register`, `/redirect`), tenant catalog/grants APIs, admin web routes, the login
   destination matrix, and `database.sql` migration parity must stay compatible. Any migration must be in the spec with rollback noted.
5. **Identity authority invariant.** Auth is the sole source of truth for users, groups, grants, and tokens. Never duplicate
   identity stores in consumers; never let another app write identity except through the Auth API. Cross-app identity flows
   only via local JWT validation (shared secret) + HTTP Bearer user lookup.
6. **Keep it cPanel-deployable.** `public/` docroot; `public/.htaccess` MUST forward `Authorization`
   before the front-controller rule; `JWT_SECRET`/`ENCRYPTION_KEY` here are canonical (consumers mirror them byte-identical);
   tenant slugs must match the Auth DB. `.env.cpanel` contains real passwords — keep gitignored, rotate post-deploy, never commit secrets.
7. **Keep tests green.** Nothing may break the existing suites (incl. 29 access-config tests). No change is
   complete until the user pastes green results. If a test enshrines drifted behavior, fix the test only after the
   code fix lands (Type B follows Type A).
8. **Document after approved changes.** Update `SESSION-PROMPT.md` + root `PROJECT_UPDATES.md` (Auth section + Last Session Notes)
   and append a `Current status` entry in Section 3 below.
9. **Spec format standard (all future specs).** Every normative spec MUST live
   as its own file under `assemblies/loa-auth-platform/` (never inline in `AGENTS.md` — §4 is a
   pointer only) and MUST follow the consult template: metadata table
   (ID/Title/Status/Owner/Version/Scope/Non-goals/Layer) + RFC 2119 terminology +
   `Context` + `Constraints` (numbered `CON-*`, MUST/MUST NOT) + `Goal`
   (decisions `DEC-*`, acceptance `ACC-*`) + `Deliverables` (numbered `D-*`) + `Glossary` + `References`.
   No normative change via reformat/polish alone.
10. **Spec-first + TDD with behavioral coverage.** Every future spec MUST separate:
    - **Objective** — deterministic, machine-checkable (`ACC-*`): statuses, shapes, claims, guards
      (401/403/404/422/423), token rotation/revocation, idempotency.
    - **Subjective** — human-judged checks stated as observable reviewer steps
      (e.g. "reviewer confirms admin login lands on the dashboard and non-admin redirect carries the fragment").
    TDD MUST cover both: `php artisan test` (user-run, request-level over mocks, one behavior per test,
    `RefreshDatabase` — never hardcoded tokens) for logic, plus user-run manual checks (SSO flows, dashboard)
    for paths tests cannot prove. No behavior without a `CON-*` + `ACC-*` + `D-*`.

---

## 2. App overview and scaffold

**What it is:** LOA Auth Platform — JWT token service, user/group/grant management, tenant administration, SSO entry,
Laravel 12 + PHP 8.3 + MySQL (local `loa_auth`; prod `lyceumalabang_auth_db`), subdomain `auth.lyceumalabang.edu.ph`.
**Identity authority** for the whole LOA platform: issues tokens, owns tenants/groups/grants/membership; Cert and Consult consume.

**Features:** register/login/refresh/logout (423 on lock, 403 on disabled) · JWT HMAC-SHA256 access + DB-backed rotating refresh tokens
(revoke on logout/password change/reset/lock; daily prune) · `jwt.auth` + `jwt.tenant` + permission middleware · tenant-scoped
groups/grants/overrides + level-based `tenant-group-endpoint-grants` + endpoint catalog + `GET /api/v1/auth/access` · admin dashboard
v1 (users) / v2 (tenants) / v3 (create user) / v4 (group/permission mgmt) + platform permission surface · access-config import/export
(template/preview `dry_run`/transactional apply) · Web UI (login redirect via fragment, forgot/change password, SMTP mail) · SSO entry
(`/sso/login`, `/sso/register`, `/redirect`) · OpenAPI/Swagger at `/api/docs` · pending-activation flow (baseline exists: `Activation` + 24h tokens) ·
CORS/allowlist + tenant `redirect_origins` · master admin seeder (`loa-auth-admin`).

**Scaffold:**

```
assemblies/loa-auth-platform/
├── app/Models/ (18)        # User, UserGroup, Permission, RefreshToken, Tenant, Activation, PasswordSetToken,
│                           # TenantAppEndpoint, TenantEndpointGrant/Override, UserClaimOverride, AuditLog, …
├── app/Services/ (10)      # JWTService, IdentityService, AuthorizationService, TenantService,
│                           # PermissionPolicyService, ActivationService, AuditLogger, PortalRouter, …
├── app/Http/Controllers/   # Auth, EndpointGrant, AccessConfig, WebAuth, WebAdmin, Group, TenantMemberImport, …
├── routes/api.php + web.php# API + admin web UI + SSO entry
├── config/jwt.php          # canonical secrets (consumers mirror)
├── database/migrations/    # 000001–000022+ (tenants, grants, overrides, activations, …)
├── database/sql/           # cpanel install + seed SQL (structural parity with migrations)
├── database/seeders/       # master admin seeder (seeder-spec.md Final)
├── tests/                  # incl. 29 access-config tests + RegisterTest (register→404 per activation spec)
├── docker/                 # php/Dockerfile + nginx/default.conf
├── *.md specs              # web-ui, admin-dashboard, catalogs, grants, import/export, tenant, activation (see §4)
├── SESSION-PROMPT.md       # assembly session details
└── AGENTS.md               # this file
```

**Architecture notes:** tenant-scoped everything (`user_groups.tenant_id`, scope-unique pivots); JWT carries `groups` + `permissions` + `tenant`;
suspended tenants rejected at login/refresh/verify; login destination matrix (admin → session dashboard; non-admin + valid redirect →
fragment; direct → reject + revoke). Consumers validate locally — no per-request HTTP to Auth.

---

## 3. Current status (historical tracking — append newest at bottom)

- **Phase 1 — largely implemented, not deployed.** JWT service, Identity/Authorization services, tenant v3.0 stack, admin dashboard
  v1–v4, access-config import/export, Web UI + SSO entry, OpenAPI, seeder with migration parity. Deploy deferred (user decision — focus on Cert).
- **Latest — §12 group-permission restructure + auth-tenant items** (multi-select Add Member, CSV multi-group, Create User + set-password,
  Platform badge/shortcut); test fixes + `PasswordSetToken` HasUuids fix; SQL consolidation (`cpanel-auth-db-install.sql`).
- **2026-09-22 — Alignment audit (read-only, Type A/B/C filed — implementation DEFERRED, mirror rule applies).**
  10 Type-A (I1 auto-grant vs 422; missing platform-toggle + session-invalidate; create-form silent fields; dead register code;
  `deny` deleted-not-stored; `tenant_id` NOT NULL blocks platform-wide; tie-break; tenant Create User contract; catalog wrap shape).
  6 Type-C (ordinal tables disagree; `admin==write?`; `none` vs `deny`; dual 24h/48h tokens; empty-password placeholder; stale `web-ui.md` blocks).
  No hard Type-B. Next: user runs tests + lint, then commit + push, then `user-account-activation.md` v1.0 implementation.
- **2026-09-22 — Assembly contract created.** This `AGENTS.md` (wise_wallet format); referenced from root `AGENTS.md`.
- **2026-09-22 — Spec-mirror pass (auth).** Specs rewritten to match working code, improvements filed DEFERRED: admin-top ordinals, wrapped catalog shape, auto-attach (no 422), deny-deletes + `none` dead path, NOT NULL platform-wide block, tie first-wins, tenant-create `group_id` + 48h/24h token split, empty-string placeholder, unimplemented toggle/invalidate/register-cleanup marked, web-ui supersession pointers, §15 inventory closed.

---

## 4. Specs (pointer only)

**Normative text lives in the assembly `*.md` files; implement exactly those. This section is a pointer only.**

### Normative specs

| Spec | Status |
|---|---|
| `web-ui.md` | FINAL v1.4 (unified-auth-flow D5/D8 supersedes §§3/4.1) |
| `unified-auth-flow.md` | FINAL v1.0 (amended by `dashboard-account.md` v1.1–v1.3) |
| `dashboard-account.md` | FINAL v1.3 |
| `admin-dashboard.md` | FINAL (v1–v4 implemented) |
| `admin-dashboard-home.md` | FINAL |
| `admin-audit-log.md` | FINAL |
| `tenant-endpoint-catalog.md` | FINAL v3.2 |
| `tenant-group-endpoint-grants.md` | FINAL v1.1 |
| `tenant-group-access.md` | FINAL |
| `access-config-import-export.md` | FINAL v1.0 |
| `group-permission-management.md` | FINAL v3.0 (§12) |
| `auth-tenant.md` | FINAL v1.1 |
| `tenant-app-api.md` | FINAL |
| `tenant-member-import.md` | FINAL |
| `bulk-user-import.md` | FINAL |
| `permission-registry.md` | FINAL |
| `redirect-interstitial.md` | FINAL |
| `sso-logout.md` | FINAL |
| `cert-readiness.md` | FINAL (Auth-side provisioning for cert) |
| `user-account-activation.md` | FINAL v1.0 (baseline exists; implementation next) |
| `tenant-member-picker.md` | PROPOSED (not Final — no code) |

### Operate / session (not normative specs)

| File | Status |
|---|---|
| `test-suite.md` | DRAFT (says SQLite; actual MySQL — mirror fix deferred) |
| `environment.md` | DRAFT — tooling spec |
| `DEPLOY.md` / `LOCAL-DEV-RUNBOOK.md` / `CPANEL-DEPLOYMENT-NOTES.md` | runbooks (see file) |
| `README.md` | DRAFT — assembly overview |
| `SESSION-PROMPT.md` | session details (not normative spec) |
