# LOA Cert Platform — Test Suite Specification

**Version:** 1.1
**Status:** Draft
**Layer:** Product Assembly (`loa-cert-platform`)
**Audience:** AI Development Agents

---

## 1. Purpose

This document defines the current automated test baseline for the LOA Cert Platform. The implementation is expected to use PHPUnit and Laravel's built-in test helpers so the suite remains fast, isolated, and easy to run.

## 2. Current test stack

| Component | Choice | Notes |
|---|---|---|
| Framework | PHPUnit | The project uses Laravel's PHPUnit integration. |
| Database | SQLite in-memory | Recommended for isolated feature and unit tests. |
| HTTP testing | Laravel test helpers | Use `json()` and `actingAs()` where appropriate. |
| Environment | `testing` | Set by the PHPUnit config. |

## 3. How to run tests

From the assembly directory:

```bash
php artisan test
```

From the shared Docker stack:

```bash
cd /path/to/loa-apache-server-apps
docker compose exec cert-app php artisan test
```

## 4. Test layout

Keep the suite organized as follows:

```text
assemblies/loa-cert-platform/
├── phpunit.xml.dist
└── tests/
    ├── TestCase.php
    ├── Feature/
    │   ├── Api/
    │   └── Web/
    └── Unit/
```

Use these conventions:

- Put public API tests in `tests/Feature/Api`.
- Put admin or middleware behavior tests in `tests/Feature/Web`.
- Put certificate logic and service behavior in `tests/Unit`.
- Use `RefreshDatabase` for any test that writes to the database.

## 5. Minimum coverage expectations

The initial suite should cover:

- Public verification routes and view routes.
- Certificate lifecycle behavior: create, read, update, revoke, and export.
- Event and attendee flows.
- Template lifecycle and validation rules.
- Admin-only dashboard or reporting calls.

## 6. Test design rules

- Prefer end-to-end request handling over mock-heavy unit tests where practical.
- Keep seed data minimal and explicit.
- Use SQLite in-memory for speed and deterministic resets.
- Avoid depending on the production environment or a live database.
- Do not test implementation details when the observable behavior is easier to verify.

## 7. Anti-patterns

- Do not rely on the local Docker database for unit tests.
- Do not skip the database reset between tests.
- Do not depend on the production `.env` file for test execution.
- Do not write tests that only assert internal method calls instead of user-visible behavior.
