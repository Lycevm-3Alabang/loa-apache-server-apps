# LOA Consult Platform — Test Suite Specification

| Field | Value |
|-------|-------|
| ID | CONSULT-TEST-001 |
| Title | Consult Platform Test Suite |
| Status | Final v1.0 (user-approved 2026-09-23) |
| Owner | Consult Platform assembly |
| Version | 1.0 Final |
| Scope | Automated + manual verification for auth-layer §11 Steps 1–7 + domain slices B/C against Final specs; MySQL `loa_consult_test`; USER-run only |
| Non-goals | Admin+import contracts (deferred), Phase E reports, cutover frontend, prod data, cross-app deploys |
| Layer | Product Assembly (`assemblies/loa-consult-platform/`) |

## RFC 2119 terminology

The key words MUST, MUST NOT, REQUIRED, SHALL, SHALL NOT, SHOULD, SHOULD NOT, RECOMMENDED, MAY, and OPTIONAL in this document are to be interpreted as described in RFC 2119.

## Context

Consult assembly is Laravel 12 + PHP 8.3 + MySQL 8 (`loa_consult`), thin product assembly owning routing/middleware/JWT-validation/RBAC only. Normative behavior lives in `api-endpoints.md` Final v1.0 (118 gated + 5 public), `auth-integration.md` Final v1.4 §11 (Steps 1–7), `data-model.md` Final v1.3 (shape contract; §3 Status gates implementability), `endpoints-academic/appointments/evaluations.md` Final v1.0, `docker-compose-spec.md` Final v1.0 (root-stack `:9002`).

Slices B (appointments/academic) and C (evaluations) plus auth-layer port are landed with green pastes 2026-09-22 (Jwt 6, Policy 5, AuthTrio 16, RouteGating 5, Availability 10/10, Appointment 12/12, EvaluationTables 4/4, PeriodsRubrics 16, Flow + Results). This spec codifies the test contract so TDD can interrogate those Final specs without inventing behavior. Per assembly `AGENTS.md` §1.3/§1.7: agent NEVER runs tests; USER runs sequentially from repo root; nothing breaks HealthTest; no change complete until green pasted.

## Constraints

- **CON-1** — Test database MUST be dedicated MySQL `loa_consult_test`, pinned via `tests/bootstrap.php` (phpunit `force` loses to container `$_SERVER`). Tests MUST NOT touch `loa_consult` app DB.
- **CON-2** — Suites MUST run sequentially only. Concurrent runs against the shared test DB cause deadlocks.
- **CON-3** — Every DB-touching test MUST use `RefreshDatabase` with minimal explicit seed data per test.
- **CON-4** — Auth in tests MUST use a JWT claims helper (HS256, `type=access`, `tenant.slug=loa`, chosen `sub/groups/permissions`). Tests MUST NOT use hardcoded tokens, production `.env`, live DB, Auth service, or network.
- **CON-5** — Tests MUST prefer request-level (`getJson/postJson/json`) over mocks and MUST assert observable status + bare shapes only (`{appointments}`, `{data}`, `{success:true}`, `{error}` — no envelope).
- **CON-6** — One behavior per test; Arrange/Act/Assert; descriptive names; `phpunit.xml.dist` sets `testing` env + test secrets.
- **CON-7** — Agent MUST NOT execute test commands. USER runs from repo root (`loa-platform` project) only; never an assembly-level compose file.
- **CON-8** — Nothing MUST break `GET /api/v1/health → {status:ok, service:loa-consult-platform}`.
- **CON-9** — Level gates MUST match `api-endpoints.md` Final v1.0 §5 + `config/consult-endpoints.php` (public 5 + catalog 118); `jwt.auth` before `jwt.endpoint`; `throttle:10,1` on callback/refresh.
- **CON-10** — Tests MUST NOT assert internals (method calls) or invent envelopes/levels the specs forbid.

## Goal

### Decisions

- **DEC-1** — Stack: PHPUnit via `php artisan test` + Laravel HTTP helpers + `WithJwt`-style claims trait (cert pattern, consult re-point).
- **DEC-2** — Layout: `tests/Unit/` for middleware/computation; `tests/Feature/Api/` for route behavior; `tests/TestCase.php` base.
- **DEC-3** — Coverage groups: public/middleware (auth-layer) + appointments/academic (slice B) + evaluations/results (slice C). Admin/import covered only when its contract lands.
- **DEC-4** — Manual checks supplement automation for browser-cookie and nginx paths tests cannot prove.

### Acceptance — Objective (machine-checkable)

- **ACC-1** — Health: `GET /api/v1/health` 200 `{status:ok, service:loa-consult-platform}`.
- **ACC-2** — `JwtMiddlewareTest` 6/6: valid passes; 401 missing/invalid/expired/wrong-type; 403 `tenant_mismatch`.
- **ACC-3** — `EndpointPolicyMiddlewareTest` 5/5: public tokenless pass; 403 closed-by-default; 403 insufficient_level; sufficient pass + `jwt_endpoint_level` stored; real catalog 118+5 loads.
- **ACC-4** — `AuthTrioTest` 16/16: callback/refresh/logout contract; email-domain upsert (onmicrosoft→students, lyceumalabang→employees); `SSO-` placeholder ≤10 chars; unknown-domain no-row; audit fail-soft; `auth/*` public; `assertPlainCookie` (api skips `EncryptCookies`).
- **ACC-5** — `RouteGatingTest` 5/5: health + count-active public; semesters/admin 401 tokenless; callback reachable.
- **ACC-6** — B1: `000011` academic baseline delta + model convergence + structure tests green.
- **ACC-7** — B2: `000012` appointment-family tables + 5 thin models + structure tests green.
- **ACC-8** — B3: `AvailabilityRuleController` + gated routes + `AvailabilityTest` 10/10.
- **ACC-9** — B4: `AppointmentController` 10 routes + `AppointmentTest` 12/12 (conflicts/slots/rules, route order, single-level perms, staff-only creator, employees-only attendees).
- **ACC-10** — B5: academic hardening (409s, quirks, camelCase, no-change 400s, fail-soft audit) + `000013` + `AcademicHardeningTest` green.
- **ACC-11** — C1: `000014` 10 tables + 10 thin models + `EvaluationTablesTest` 4/4.
- **ACC-12** — C2: periods + rubric-groups + `PeriodsRubricsTest` green (seed/lock 409s, duplicate, snapshot).
- **ACC-13** — C3: lifecycle (masking, get-or-create, pending, bootstrap, dispute, comments) + `EvaluationFlowTest` green.
- **ACC-14** — C4: `ResultsService` (computeAll, lazy, visibility, camelCase) + 20 routes + `ResultsTest` green.
- **ACC-15** — Full suite green pasted by USER; bootstrap pins `loa_consult_test`; app DB untouched.

### Acceptance — Subjective (human-judged)

- **ACC-S1** — Reviewer confirms `GET /api/v1/health` 200 via root-stack nginx and direct container with identical bare shape.
- **ACC-S2** — Reviewer confirms SSO callback sets `loa_connect_refresh` plain cookie and refresh/logout rotate/clear it with no error toast in the browser flow.
- **ACC-S3** — Reviewer confirms error responses are bare `{error}` with no envelope leak on sampled 401/403/404/409/422 paths.

## Deliverables

- **D-1** — `tests/TestCase.php` + JWT claims helper (mint HS256 with chosen claims; never hardcoded tokens).
- **D-2** — `phpunit.xml.dist` (testing env + secrets) + `tests/bootstrap.php` test-DB pin.
- **D-3** — `tests/Unit/JwtMiddlewareTest.php` (ACC-2), `tests/Unit/EndpointPolicyMiddlewareTest.php` (ACC-3).
- **D-4** — `tests/Feature/Api/AuthTrioTest.php` (ACC-4), `tests/Feature/Api/RouteGatingTest.php` (ACC-5).
- **D-5** — Slice-B tests: academic delta/structure, `AvailabilityTest`, `AppointmentTest`, `AcademicHardeningTest` (ACC-6–ACC-10).
- **D-6** — Slice-C tests: `EvaluationTablesTest`, `PeriodsRubricsTest`, `EvaluationFlowTest`, `ResultsTest` (ACC-11–ACC-14).
- **D-7** — USER-run runbook: repo-root `docker compose exec consult-app php artisan test` (+ single-file and `--filter` variants), sequential only (CON-7).

```powershell
cd D:\loa\loa-apache-server-apps
docker compose exec consult-app php artisan test
docker compose exec consult-app php artisan test tests/Feature/Api/AppointmentTest.php
docker compose exec consult-app php artisan test --filter testName
```

## Glossary

| Term | Meaning |
|------|---------|
| Bare shapes | `{appointments}`, `{data}`, `{success:true}`, `{error}` with no envelope; preserved until cutover |
| Claims helper | Test trait minting HS256 access tokens with chosen sub/groups/permissions/tenant |
| Green pasted | USER ran suite and pasted passing output; required for completeness per §1.7 |
| Slices B/C | B = appointments/academic/semesters; C = evaluations/periods/rubrics/results |
| Fail-soft audit | Audit write failure MUST NOT fail the request |

## References

- `api-endpoints.md` Final v1.0 §5 (118+5 catalog, levels)
- `auth-integration.md` Final v1.4 §10–§11 (SSO trio, Jwt/Policy, gating, throttle)
- `data-model.md` Final v1.3 §3/§7 (shape contract, Status gate, baseline deltas 000011–000014)
- `endpoints-academic/appointments/evaluations.md` Final v1.0 (module contracts)
- `docker-compose-spec.md` Final v1.0 (root-stack wiring `:9002`, `loa_consult` init)
- Root `AGENTS.md` (Rule 0/0.5, No Auto-Pilot) + `principles.md` (SDD+TDD) + `platform.md` (gotchas)
- Assembly `AGENTS.md` §1.7–§1.10 (HealthTest, docs, format, behavioral coverage)
- Cert pattern: `loa-cert-platform/test-suite.md` + `WithJwt` precedent

---

## Document Control

- **Status:** Final v1.0 (user-approved 2026-09-23)
- **Created:** 2026-09-18 as v0.1; rewritten 2026-09-23 to §1.9 template (metadata + RFC 2119 + CON/DEC/ACC/D); promoted Final 2026-09-23
- **Next:** implementation per ACC-*; admin+import coverage expands §5 when its contract lands
