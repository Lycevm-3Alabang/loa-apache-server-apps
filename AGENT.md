# AI Agent Instructions

> Sole agent entry point (merged 2026-09-22 from `AGENT.md` + `AI-RULES.md` + `AI-GUIDE.md`).
> `AI-RULES.md` and `AI-GUIDE.md` are deprecated and removed — detail lives in `principles.md` (timeless rules) and `platform.md` (LOA architecture + gotchas). Status lives in `PROJECT.md`.

## ⛔ MANDATORY: SDD + TDD — Specification First, Then Test-Driven Discovery

**SDD tells us what we intend to build; TDD aggressively exercises that specification to implement it, discover its boundaries, expose missing behavior, and increase confidence the final system satisfies the refined specification.**

Required development model:

```text
Specification first → implementation baseline → TDD exploration → edge-case discovery → specification refinement → continued TDD → verification
```

1. **Specification first** — read the Final spec completely before touching code. Extract business intent, requirements, rules, acceptance criteria, inputs/outputs, validation, error behavior, constraints, invariants. Distinguish `Specified behavior vs. Unspecified behavior`. Do not silently invent requirements.
2. **Implementation baseline** — implement the minimum behavior to satisfy the spec. No unrelated architectural changes.
3. **TDD exploration** — RED → GREEN → REFACTOR. Do not stop at happy-path examples. Systematically interrogate: null/empty/min/max/duplicate/combinations; rule interactions; state before/after create/delete/retry; dependency failure, missing data, double-submit; concurrency (dual pass of app-level checks, DB-level uniqueness); unauthorized/invalid ownership/tampered IDs.
4. **Analyze discoveries** — classify every new behavior:
   - A. Implementation defect (spec ✓, test ✓, code ✗) → fix code.
   - B. Test defect (spec ✓, test ✗) → fix test.
   - C. Specification gap (spec incomplete) → record it, refine the spec, then code. Never silently choose behavior.
5. **Refine + continue** — `TDD discovery → spec update → acceptance criteria update → test update → implementation hardening`. Repeat until behavioral coverage (happy, boundary, invalid, rule combinations, failure, state, security, concurrency, regression) is adequate. Coverage is an outcome, not the objective.

Full loop, phase checklists, and verification gate: `principles.md`. LOA layer map: `platform.md`. Current status: `PROJECT.md`.

## ⛔ MANDATORY: Docker From Repo Root Only — User Runs It

**NEVER run `docker compose` yourself. The USER runs docker commands.**

- All docker work goes through the **root-level** `docker-compose.yml` (project `loa-platform`) — never an assembly-level compose file (e.g. `assemblies/*/docker-compose.yml` creates a duplicate, stale stack).
- To ask the user to run tests, give them exactly:

  ```powershell
  cd D:\loa\loa-apache-server-apps
  docker compose exec auth-app php artisan view:clear
  docker compose exec auth-app php artisan test
  ```

  (Replace `auth-app` with `cert-app` / `consult-app` per assembly.)
- Never launch overlapping/backgrounded runs — two concurrent suites deadlock the shared test DB (lock-wait timeouts masquerade as failures).
- Wait for the user to paste results; do not poll or re-run on your own.

## ⛔ MANDATORY: Specs Before Code (Rule 0)

**NEVER write code until the spec exists.**

Before writing, modifying, or refactoring ANY code, the AI agent MUST:

1. Search the repo for the relevant spec `.md` file (kernels/, domains/, business-contexts/, services/, assemblies/)
2. Read the spec completely
3. If NO spec exists → **stop and write the spec first** (or ask the user)
4. If a spec exists but is incomplete → **finish the spec first**
5. Only after the spec is written and approved → write code that matches it exactly

| Situation | Required Action |
|-----------|-----------------|
| No spec exists | Write the spec FIRST, or ask. Do NOT write implementation code. |
| Spec is Draft | Complete the spec FIRST. Do NOT write implementation code. |
| Spec is Final | Read it completely, then code exactly to it. |
| Concept owned elsewhere | Reference by contract/ID. Do NOT duplicate. |

If the task has no spec and no prior discussion, ask the user before writing any code.

**Rule of thumb:** Spec exists → code. Spec missing → spec first. Always.

### Spec authorship is NOT "code" — never block it

Rule 0 restricts **implementation code** (app code, migrations, routes, blade views, config, etc.). It does **NOT** restrict the user's ability to author or maintain specs.

- User says "update this Draft spec to Final" → edit the spec, mark it Final, save. Do NOT refuse.
- Refusing to edit a spec the user asked to edit is a failure. When in doubt, ask — do not refuse.

## ⛔ MANDATORY: No CLI Without Permission (Rule 0.5)

**The AI agent MUST NOT execute ANY CLI/terminal commands** — including `docker`, `docker compose`, `git`, `composer`, `npm`, `php`, `artisan`, `curl`, `ssh`, PowerShell/bash — **without explicit user permission in the current session.**

- Applies even to read-only commands (`docker compose ps`, `git status`, `ls`). "Harmless" is not an excuse.
- **Report, don't run.** Describe the command, state the expected outcome, wait for approval.
- Permission is per-command/task in the current session only — never carried over.

## ⛔ MANDATORY: No Auto-Pilot — Always Ask

**Every significant action requires explicit user confirmation.**

Requires a "yes" or specific instruction first:

- Writing, modifying, or deleting code or spec files
- Running database migrations, Docker commands, package installs, tests
- Updating `PROJECT.md`, `PROJECT_UPDATES.md`, or any tracker file
- Architectural decisions, commits, pushes, anything changing repo/service state

**No auto-piloting. No assumption-based action. No "I'll just do this real quick."** If unsure whether confirmation is needed → ask anyway. If you started without asking → stop, report, ask for the remainder.

## Testing

- Behavioral coverage over line coverage: happy paths, boundaries, invalid inputs, rule combinations, failure paths, state transitions, security, concurrency, regressions. One behavior per test, Arrange/Act/Assert, descriptive names. Request-level over mocks; assert observable behavior (status + shape), not internals.
- **USER runs tests** (Rule 0.5 + Docker rule above). No code change is complete until the user pastes green results. If tests are slow, ask for at least the affected file. If no suite exists, ask the user how to verify.
- Per-assembly runner: `docker compose exec <app> php artisan test` (single file: append `tests/Feature/Api/<Name>Test.php`; single method: append `--filter <testName>`).

## Ownership

Always check ownership before creating entities. Never duplicate shared concepts. Reference shared concepts by contract/ID (e.g. `PartyId`, `VehicleId`), never by embedding.

## Layer Responsibilities

```text
Product Assemblies (deployable apps)
        ▲
Business Contexts (workflows)
        ▲
Industry Domains (reusable knowledge)
        ▲
Platform Services (technical capabilities)
        ▲
Platform Kernels (canonical concepts)
```

## Dependency Rules

- Dependencies point downward only. Never upward or circular.
- Assemblies contain no business logic. Contexts never reference each other directly (use events).

## Naming & Conventions (essentials)

- Classes/interfaces/properties: PascalCase. Locals/privates: camelCase. Markdown docs: kebab-case. Constants: SCREAMING_SNAKE_CASE. One class per file; filename matches class.
- Entities: singular (`Vehicle`); collections: plural. Interfaces prefixed `I` (`IRepository`). Events: past tense (`VehicleCreated`), immutable, JSON-serialized.
- New Laravel models: `HasUuids` trait + `$table->uuid('id')->primary()` (legacy `Tenant`/`AuditLog`/`User` use manual `Str::uuid()` boot — both valid).
- REST: nouns + HTTP verbs, versioned `/api/v1/...`. Statuses: 200 / 201 / 204 / 400 / 401 / 403 / 404 / 500.
- Migrations: one per change, reversible, descriptive timestamped names; separate schema from data changes.

## Laravel Gotchas (do not relearn)

- **Scaffold:** every assembly needs `artisan`, `public/index.php`, `bootstrap/app.php` (no `providers` array — Laravel 11+ auto-registers), quoted-space `.env`, `config/app.php` (no `providers` array), `routes/api.php` (must exist).
- **`.env` quoting:** quote every value with spaces (`APP_NAME="LOA Cert Platform"`), or bootstrap crashes.
- **Apache strips `Authorization` on cPanel:** every `public/.htaccess` MUST forward it before the front controller, else all JWT endpoints 401 in prod while passing locally:
  `RewriteCond %{HTTP:Authorization} .` + `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`.
- **Secret parity:** `JWT_SECRET` and `ENCRYPTION_KEY` byte-identical across Auth ↔ consumers; tenant slug must match Auth DB. Verify via tinker post-deploy, never commit secrets.

## When Starting Development

1. Check `dependency-rules.md`
2. Check `platform.md` for layer placement
3. **Check if the spec already exists** — reuse it
4. **If no spec exists, create it first** in the correct layer folder (`README.md`, `entities/`, `events/`, `contracts/`, `rules/`)
5. Only code after spec is approved (Rule 0); confirm each step (No Auto-Pilot)

**No spec = no code. This is mandatory, not optional.**

## Quick Reference

| You Need | Look In |
|----------|---------|
| SDD+TDD loop + verification gate + coding detail | `principles.md` |
| Architecture + layer map + Laravel gotchas (full) | `platform.md` |
| Project status + decisions | `PROJECT.md` |
| Dependencies | `dependency-rules.md` |
| Kernels | `kernels/` |
| Domains | `domains/` |
| Contexts | `business-contexts/` |
| Services | `services/` |
| Assemblies | `assemblies/` |

> Former `AI-RULES.md` / `AI-GUIDE.md` — merged here and removed (2026-09-22). Update any stale references to point at this file.
