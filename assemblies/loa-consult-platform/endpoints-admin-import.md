# LOA Consult Platform — Admin + Import + Data-Audit Endpoints

| Field | Value |
|-------|-------|
| ID | CONSULT-ADMIN-001 |
| Title | Phase D Admin + Import + Data-Audit |
| Status | Final v1.1 (pointer refresh to api-endpoints v2.0, user-approved 2026-09-23) |
| Owner | Consult Platform assembly |
| Version | 1.1 Final |
| Scope | Consult-local admin surface + `import/*`, data/audit, `access-config`, `user-permissions`, plus `/service/*` proxy decision; identity (users/groups/grants) owned by Auth — consult holds Auth↔domain link only; all under `/api/v1/` + `jwt.auth`/`jwt.endpoint` |
| Non-goals | Redefining levels/paths (owned by `api-endpoints.md` Final v2.0); auth flow (owned by `auth-integration.md`); shapes/migrations (owned by `data-model.md`); reports (Phase E) |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

Slices B/C + auth-layer are COMPLETE (routes in `routes/api.php`, 13 controllers, green pastes). Missing per `api-endpoints.md` Final v2.0 §5 + `config/consult-endpoints.php` gap vs routes file: admin-users family, import family, data/audit, access-config, user-permissions. TODO defers these as Admin+import module (enforcement, Auth-owned) with contract at implementation phase; Phase D covers the contract. Ownership (user decision 2026-09-23): admin-users are actual Auth tenant app users for the consultation app under groups `aces-admin` / `aces-dean` / `aces-faculty` / `aces-user` (student); Auth users link to consult `students`/`employees` by email. Consult MUST NOT own user CRUD — identity writes live in `loa-auth-platform` (tenant member endpoints); consult exposes link-derived reads only. Enforcement note: destructive/sensitive ops are `admin`-level; local admin/role MUST NOT be introduced — groups come from JWT `groups` claim only.

## Constraints

- **CON-1** — Paths/levels MUST match `api-endpoints.md` Final v2.0 §5 + `config/consult-endpoints.php`. On conflict those win; this doc MUST NOT duplicate the catalog (reference by ID).
- **CON-2** — All Phase D routes MUST sit under `/api/v1/` behind `['jwt.auth','jwt.endpoint']`; bare shapes only (no envelope).
- **CON-3** — Destructive ops (`DELETE`, `soft-delete`/`bulk-soft-delete`/`restore`, `reset-db`, `delete-students`, `import/*` writes, `access-config` writes, `visibility`/`invalidate`) MUST require `admin` level. Read/reference/preview MUST require at least `read`.
- **CON-4** — No local users/roles/groups tables. Identity (users, membership, grants) is Auth-owned (`loa-auth-platform` tenant member endpoints); consult MUST read levels from `jwt_endpoint_level` + groups (`aces-*`) from JWT claims only, and MUST resolve person identity via the Auth↔domain email link to `students`/`employees`.
- **CON-5** — Migrations for any new tables/columns MUST be in this spec with rollback noted before code (assembly §1.4). Shapes MUST match `data-model.md` Final v1.3 or refine it first (Classification C → spec-first).
- **CON-6** — Bulk/import MUST have preview semantics (dry-run shape identical to apply shape minus side effects) and MUST be idempotent on retry with the same payload key.
- **CON-7** — Tests MUST extend `test-suite.md` Final v1.0 (RefreshDatabase, JWT helper, sequential, `loa_consult_test` only, one behavior per test).

## Goal

### Decisions

- **DEC-1** — Admin-users: NO consult-owned user CRUD. Create/update/delete/soft-delete/restore/bulk live in Auth (`tenant-member*`/`bulk-user-import`, referenced by ID). Consult exposes link-derived reads only: `related-data` (Auth user + linked student/employee row), `users/primary`, `users/attendees` resolved via email link + JWT claims. Any `admin/users*` write path in the legacy catalog MUST NOT be implemented in consult.
- **DEC-2** — Import: identity imports (users) live in Auth (`tenant-member-import`/`bulk-user-import`); consult imports domain attributes only via `preview` (dry-run) + per-domain `reference` + domain writes (`departments-courses`, `faculties`, `students` domain rows keyed by email link); `subjects`/`sections` reference-only in this phase.
- **DEC-3** — Data/audit: `audit-logs` read; `evaluation-mappings` read; `export-consultations` admin export; `delete-students` + `reset-db` admin destructive with double-guard (level + explicit confirm field).
- **DEC-4** — Access-config + user-permissions: read/export/paths read; writes/import/put admin. Observed frontend usage stays untouched until cutover.
- **DEC-5** — `/service/*` proxy: RESOLVED 2026-09-24 as (b) thin proxy to legacy service with `X-Api-Key` (mirroring cert/auth-proxy.md) because no `/service/*` routes found in consult assembly.

### Acceptance — Objective (machine-checkable)

- **ACC-1** — Unknown Phase D path tokenless or low-level → 403 closed-by-default/insufficient_level (policy).
- **ACC-2** — Admin link reads: `related-data` returns Auth user + linked student/employee (or explicit unlinked marker); no consult `admin/users` write route exists; user writes attempted in consult → 404 (not implemented — Auth duty).
- **ACC-3** — Import preview returns identical shape to apply without writes (row count verified unchanged); apply with same key twice → second is idempotent success with no duplicates.
- **ACC-4** — `reset-db`/`delete-students` without confirm field → 422; with `admin` + confirm → success + audit row fail-soft.
- **ACC-5** — `reference` endpoints return read shapes usable directly as import payloads (round-trip equality minus server-set ids/timestamps).
- **ACC-6** — `visibility`/`invalidate` writes require `admin`; non-admin → 403; side effects (recompute/restore chains) verified via results endpoints.
- **ACC-7** — `/service/*` decision recorded (DEC-5 resolved) before any proxy code.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer runs preview→apply on a small CSV in local root-stack with no error toast and confirms row counts match.
- **ACC-S2** — Reviewer confirms destructive ops show an explicit confirm step in API client docs with no accidental-submit path.

## Deliverables

- **D-1** — Routes in `routes/api.php` (link reads `admin/users/{id}/related-data`, `users/*`, `import/*` domain-only, `admin/audit-logs`, `admin/data/*`, `data/evaluation-mappings`, `admin/access-config*`, `admin/user-permissions*`) gated per CON-2/CON-3. Explicitly OUT: consult `admin/users` writes (Auth duty).
- **D-2** — Controllers/FormRequests/services (thin models; rules in requests/controllers) + migrations with rollback (CON-5).
- **D-3** — Tests: `AdminLinkTest` (link reads, unlinked marker, no-write 404), `ImportTest` (preview/apply/idempotence, domain-only), `DataAuditTest` (guards/confirm/audit) extending test-suite D-5/D-6 pattern.
- **D-4** — DEC-5 record (proxy decision) + runbook note for Auth catalog/grants delta at deploy time.

## Glossary

| Term | Meaning |
|------|---------|
| Preview semantics | Dry-run returning the exact apply shape with zero writes |
| Double-guard | `admin` level plus explicit confirm field for destructive ops |
| Fail-soft audit | Audit failure MUST NOT fail the request |
| Auth↔domain link | Auth tenant user ↔ `students`/`employees` via email; `aces-*` groups from JWT only |

## References

- `api-endpoints.md` Final v2.0 §5 (paths/levels source)
- `config/consult-endpoints.php` (public 5 + 104 gated flattened current; Phase D delta source)
- `auth-integration.md` Final v1.4 (JWT/policy/gating, §8 provisioning)
- `data-model.md` Final v1.3 §3/§7 (shape + implementability gate)
- `test-suite.md` Final v1.0 (CON/ACC/D test contract)
- Assembly `AGENTS.md` §1 (spec-first, cPanel, format) + root `AGENTS.md` (Ownership)

---

## Document Control

- **Status:** Final v1.1 (pointer refresh to api-endpoints v2.0, user-approved 2026-09-23)
- **Created:** 2026-09-23 from endpoint gap (routes file ~90 gated vs catalog 118); refined v0.2 2026-09-23 (Auth-owned users + aces-* + linkage, no consult user CRUD); promoted Final v1.0 2026-09-23
- **Next:** implementation per ACC-*/D-* after data-model gate check; DEC-5 proxy decision recorded before proxy code; on change follow Classification C (spec-first)
