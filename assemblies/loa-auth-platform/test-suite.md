# LOA Auth Platform — Test Suite Specification

**Version:** 1.1
**Status:** Draft
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** AI Development Agents

---

## 1. Purpose

This document defines the current automated test baseline for the LOA Auth Platform. The project is already using PHPUnit and Laravel's built-in testing helpers, so the goal here is to keep the suite small, predictable, and easy to run locally.

## 2. Current test stack

| Component | Choice | Notes |
|---|---|---|
| Framework | PHPUnit | The project already ships with PHPUnit via Composer. |
| Database | SQLite in-memory | Configured through `phpunit.xml.dist` and `RefreshDatabase`. |
| HTTP testing | Laravel test helpers | Use `actingAs()` and `json()` for API coverage. |
| Environment | `testing` | Set by the PHPUnit config. |

## 3. How to run tests

From the assembly directory:

```bash
php artisan test
```

From the shared Docker stack:

```bash
cd /path/to/loa-apache-server-apps
docker compose exec auth-app php artisan test
```

## 4. Test layout

The current layout is intentionally simple:

```text
assemblies/loa-auth-platform/
├── phpunit.xml.dist
└── tests/
    ├── TestCase.php
    ├── CreatesApplication.php
    ├── Feature/
    │   ├── Api/
    │   └── Web/
    └── Unit/
```

Use the following conventions:

- Put API behavior tests in `tests/Feature/Api`.
- Put web or middleware behavior tests in `tests/Feature/Web`.
- Put service-logic and model-logic checks in `tests/Unit`.
- Use `RefreshDatabase` for every test that writes to the database.

## 5. Minimum coverage expectations

At a minimum, add or maintain tests for the following flows:

- Authentication and token lifecycle: register, login, refresh, logout, verify.
- Password reset and change flows.
- User status transitions and lockout behavior.
- Group and permission assignment logic.
- Middleware and web routes that depend on auth roles.

## 6. Test design rules

- Prefer real Laravel request handling over isolated mocks where possible.
- Use SQLite in-memory for speed and isolation.
- Keep test data minimal and explicit.
- Avoid depending on the shared Docker services for unit tests.
- Use `APP_ENV=testing` and the configured test environment rather than local `.env` values.

## 7. Anti-patterns

- Do not write tests that depend on a live database or a manually seeded environment.
- Do not test implementation details instead of observable behavior.
- Do not skip the database reset between tests.
- Do not rely on the production `.env` file for test execution.
