# LOA Consult Platform — Auth Integration Readiness

| Field | Value |
|-------|-------|
| ID | CONSULT-READY-001 |
| Title | Consult Auth Provisioning Readiness |
| Status | Final v1.4 (user-approved 2026-09-23) |
| Owner | Consult Platform assembly |
| Version | 1.4 Final |
| Scope | Deploy-time Auth provisioning checklist for consult (tenant, groups, catalog import, grants, secrets) + historical Next.js notes; normative auth = `auth-integration.md` Final v1.4 |
| Non-goals | Redefining SSO/JWT/levels/shapes (owned by `auth-integration.md`, `api-endpoints.md`, `data-model.md`); duplicating the endpoint catalog; Auth-side specs |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

Consult backend is the active `loa-consult-platform` Laravel assembly (mirroring cert), frontend Next.js 16 on Vercel. Normative auth behavior (SSO flow, JWT validation, endpoints, port plan) lives in `auth-integration.md` Final v1.4 §2–§11. This doc is provisioning checklist + historical context only and MUST NOT redefine normative behavior.

Source reconciliation for v1.3: `api-endpoints.md` Final v1.0 §5 + `config/consult-endpoints.php` define public 5 (health, count-active, auth trio) + catalog 118 gated. Prior v1.2 figures (`~130`, 8-entry public list, `/api/*` paths, full 118-row tables) are stale/duplicated and superseded — retained in git history v1.2, not repeated here per Ownership (reference by ID, never duplicate). Auth JSON counterpart deferred to deploy-time per user choice (Step 4).

## Constraints

- **CON-1** — Normative auth MUST be `auth-integration.md` Final v1.4. On conflict this doc loses.
- **CON-2** — Paths/levels MUST match `api-endpoints.md` Final v1.0 §5 + `config/consult-endpoints.php` (public 5 + 118). This doc MUST NOT duplicate the catalog.
- **CON-3** — Tenant MUST be slug `loa`, `active`, with `redirect_origins` including the consult frontend (`https://aces.lyceumalabang.edu.ph`). Groups MUST be `aces-admin` / `aces-dean` / `aces-faculty` / `aces-user` (student), tenant-scoped to `loa`, and MUST come from JWT `groups` claim only; never add local roles.
- **CON-4** — `JWT_SECRET`/`ENCRYPTION_KEY` MUST be byte-identical with Auth. Secrets MUST NOT be committed.
- **CON-5** — No `app_users` mirror. Auth tenant users (consultation app) link to consult domain by email: post-SSO upsert MUST target first-class `students`/`employees` per `data-model.md` Final v1.3 §3.1.1–§3.1.2; groups/permissions MUST NOT be stored locally.
- **CON-6** — Provisioning MUST run at deploy time per `auth-integration.md` §8 (TODO PLANNED). No Auth spec/files are created now.
- **CON-7** — Legacy e-consultation specs (`D:\loa\e-consultation\specs/`) are historical with no sync duty; updates land in the assembly only.

## Goal

### Decisions

- **DEC-1** — Groups (tenant-scoped to `loa`): `aces-admin` full; `aces-dean` read breadth + `write` on activate/visibility; `aces-faculty` availability + own results; `aces-user` (student) booking + evaluations. Multi-role users hold multiple groups (replaces pipe-delimited legacy roles; `GUEST` removed).
- **DEC-2** — Catalog import generated from `api-endpoints.md` §5 (118 + 5); grant matrix §2.4 intent kept as deploy-adjustable recommendation; bulk setup ≈500+ grant records.
- **DEC-3** — SSO redirect pattern = cert pattern; Laravel `/api/v1/auth/*` (decrypt + `loa_connect_refresh` cookie); `throttle:10,1` on callback/refresh.
- **DEC-4** — Verification reuses `test-suite.md` Final v1.0 ACCs plus manual browser checks below.

### Acceptance — Objective (machine-checkable)

- **ACC-1** — Tenant `loa` active; `redirect_origins` contains consult frontend URL.
- **ACC-2** — Groups `aces-admin`/`aces-dean`/`aces-faculty`/`aces-user` exist scoped to `loa`.
- **ACC-3** — Imported catalog count equals `config/consult-endpoints.php` (118 gated + 5 public).
- **ACC-4** — Grants seeded per matrix; closed-by-default holds (verified via `test-suite.md` ACC-3/ACC-5: unknown → 403).
- **ACC-5** — Secrets identical across Auth/Consult; JWT + tenant scoping green (`test-suite.md` ACC-2/ACC-3).
- **ACC-6** — SSO trio + gating green (`test-suite.md` ACC-4/ACC-5); no `app_users` table exists.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer confirms tenant/groups/grants visible in Auth admin UI with expected levels.
- **ACC-S2** — Reviewer completes browser SSO round-trip (login → callback → refresh → logout) with no error toast and correct cookie set/clear.
- **ACC-S3** — Reviewer confirms legacy catalog tables/paths are gone from normative text (pointer-only) with history in git v1.2.

## Deliverables

- **D-1** — Tenant create/update (`loa`, active, `redirect_origins` += consult URL).
- **D-2** — Group seed (`aces-admin`/`aces-dean`/`aces-faculty`/`aces-user` scoped to `loa`).
- **D-3** — Catalog import from `api-endpoints.md` §5 / `config/consult-endpoints.php` (118+5 count-checked).
- **D-4** — Grant seed per matrix (`aces-admin` full; `aces-dean`/`aces-faculty`/`aces-user` scoped as decided).
- **D-5** — Secrets handoff (`JWT_SECRET`, `ENCRYPTION_KEY` 64-hex or `base64:`) + consult `.env` (`AUTH_BASE_URL`, `TENANT_SLUG=loa`, `REFRESH_COOKIE=loa_connect_refresh`, `REFRESH_COOKIE_TTL=10080`) — deploy-time only.
- **D-6** — SSO redirect config (`/sso/login?redirect=` accepted for consult origin).
- **D-7** — Provisioning run log + verification pastes (ACC-1–ACC-6, ACC-S1–S3).

## Historical notes (non-normative)

- Pre-Laravel NextAuth/Supabase store, `lib/auth.ts`, `[...nextauth]`, activate/forgot/change-password routes, `lib/access.ts`, `group_access`/`user_permissions`/`role`/`userrole`/reset-token/NextAuth tables, `SessionProvider`, `bcryptjs`, `AUTH_SECRET` — all removed/replaced per §11 of v1.2 (see git history).
- SSO §§3–4 shapes (`/api/auth/*`, `{access_token,user}`) superseded by `auth-integration.md` §2–§3 (`/api/v1/auth/*`); kept in v1.2 history for context.
- Full 118-row `/api/*` catalog + 8-entry public list + `/api/*` grant patterns in v1.2 superseded by pointer (CON-2); counts/paths in git v1.2 MUST NOT be used for provisioning.
- Legacy role source: dropped `app_users.role` pipe values (`ADMIN|FACULTY` → multi-group); mapping table in v1.2 history.

## Glossary

| Term | Meaning |
|------|---------|
| Provisioning | Deploy-time Auth DB writes (tenant, groups, catalog, grants) + secrets handoff; no code change |
| Closed-by-default | Unknown endpoint → 403 via `jwt.endpoint` (verified in test-suite) |
| First-class upsert | Post-SSO insert/update of `students`/`employees` by email; Auth stays sole identity authority |
| Auth↔domain link | Auth tenant user (consultation app) ↔ consult `students`/`employees` row via email; groups are `aces-*`, never stored locally |

## References

- `auth-integration.md` Final v1.4 §2–§11 (normative SSO/JWT/provisioning/port plan)
- `api-endpoints.md` Final v1.0 §5 + `config/consult-endpoints.php` (public 5 + 118)
- `data-model.md` Final v1.3 §3.1/§4 (students/employees upsert; dropped tables)
- `test-suite.md` Final v1.0 (ACC-2–ACC-5 verification)
- `docker-compose-spec.md` Final v1.0 (root-stack)
- Assembly `AGENTS.md` §1 (spec-first, auth invariant, cPanel, format) + root `AGENTS.md` (Ownership: reference by ID)

---

## Document Control

- **Status:** Final v1.4 (user-approved 2026-09-23)
- **Created:** 2026-08-24 as v1.0; v1.1 Laravel-active correction; v1.2 identity fix (2026-09-22); rewritten 2026-09-23 to §1.9 template with count/path reconciliation (pointer-only catalog); promoted Final v1.3 2026-09-23; refined to v1.4 2026-09-23 (aces-admin/dean/faculty/user + Auth↔students/employees link) and re-promoted Final 2026-09-23
- **Next:** deploy-time provisioning per ACC-*/D-*; catalog counts follow `api-endpoints.md` + `config/consult-endpoints.php`
- **Supersedes:** v1.2 full-table catalog/paths/counts (git history only)
