# LOA Consult Platform — Test Suite Specification

**Version:** 0.1
**Status:** Draft
**Layer:** Product Assembly (`loa-consult-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> Follows `../loa-cert-platform/test-suite.md` as pattern, adapted to Consult.
> Per `AGENT.md`: the AI agent never runs tests — the USER runs every command below. Suites run sequentially (concurrent runs deadlock the shared test DB).

---

## 1. Purpose

Defines the automated test baseline for the LOA Consult Platform: fast, isolated, behavior-focused. PHPUnit + Laravel test helpers; no mock-heavy unit tests where a request-level test is practical.

## 2. Test stack

| Component | Choice | Notes |
|---|---|---|
| Framework | PHPUnit | Laravel's PHPUnit integration (`php artisan test`) |
| Database | Dedicated MySQL `loa_consult_test` | Cert actual practice (isolated from app DB); never `loa_consult` app data |
| HTTP testing | Laravel test helpers | `json()`, `getJson/postJson`, `actingAs` equivalent via JWT claims helper |
| Environment | `testing` | Set by `phpunit.xml.dist` |
| Auth in tests | JWT helper (claims builder) | Mirrors cert `WithJwt`-style trait: mint HS256 tokens with chosen `sub/groups/permissions/tenant` |

## 3. How to run tests (USER runs these)

From the repo root (`loa-apache-server-apps/`), once wired:

```powershell
cd D:\loa\loa-apache-server-apps
docker compose exec consult-app php artisan test
docker compose exec consult-app php artisan test tests/Feature/Api/AppointmentTest.php
docker compose exec consult-app php artisan test --filter testName
```

Local (no Docker): `php artisan test` from `assemblies/loa-consult-platform/` (per-assembly `vendor/`).

## 4. Test layout

```text
assemblies/loa-consult-platform/
├── phpunit.xml.dist
└── tests/
    ├── TestCase.php
    ├── Feature/
    │   ├── Api/          # public routes, appointments, academic, evaluations, results, admin
    │   └── Web/          # (none initially — API-only assembly)
    └── Unit/             # computation helpers (remarks scale, aggregation, masking rules)
```

Conventions: API behavior in `tests/Feature/Api`; pure computation in `tests/Unit`; `RefreshDatabase` on every DB-touching test; minimal explicit seed data per test.

## 5. Minimum coverage expectations

- **Public:** `GET /health`, `GET /semesters/count-active`, SSO callback/refresh/logout contract (decrypt, rotate, clear).
- **Middleware:** `jwt.auth` (missing/invalid/expired token, tenant mismatch), `jwt.endpoint` (closed-by-default 403, level downgrade 403, group-membership scoping).
- **Appointments:** booking rules (student-self, behalf, internal, creator≠student), batch shape, action dispatch incl. invalid action, slot-link validation, files owner rule, availability self/other matrix.
- **Academic:** CRUD + 409 duplicates, code uppercasing, section program derivation, fix-names routine, reassign side effects (invalidate + recompute), impacts counts, activate action, deletion policy (departments disable-only).
- **Evaluations:** lifecycle DRAFT→SUBMITTED, 404-masking (non-owner/wrong-status/disabled), enrollment enforcement + `unenrolled` bypass, ratings/comment flows, dispute flow, seed/lock guards (409s), lazy `computeAll`, visibility gate 403, invalidate/restore chains.
- **Admin/import (when contracted):** destructive guards, bulk shapes, preview semantics.

## 6. Test design rules

- Request-level over mocks; assert observable behavior (status + shape), not internals.
- Bare response shapes asserted exactly (no envelope): `{appointments}`, `{data}`, `{success:true}`, `{error}`.
- One behavior per test; Arrange/Act/Assert; descriptive names.
- Never depend on production `.env`, live DB, Auth service, or network.
- JWT claims built by helper — never hardcoded tokens.

## 7. Anti-patterns

- Do not touch the `loa_consult` app database from tests.
- Do not skip database reset between tests.
- Do not run two suites concurrently (shared test-DB deadlock).
- Do not assert internal method calls instead of user-visible behavior.
- Do not invent response envelopes the spec forbids (§3.4).

---

## Document Control

- **Status:** Draft v0.1
- **Created:** 2026-09-18
- **Next:** promote alongside implementation start; expand §5 as admin+import contracts land
