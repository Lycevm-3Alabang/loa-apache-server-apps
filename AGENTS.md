# AI Agent Instructions

## Root Principle

> **The spec is the source of truth. Code matches the spec — never the reverse. SDD writes the contract; TDD tests it against reality.**

**SDD tells us what we intend to build; TDD aggressively exercises that specification to implement it, discover its boundaries, expose missing behavior, and increase confidence the final system satisfies the refined specification.**

```text
Specification first → implementation baseline → TDD exploration → edge-case discovery → specification refinement → continued TDD → verification
```

- **Specification first** — read the Final spec completely. Extract intent, requirements, rules, acceptance criteria, inputs/outputs, validation, errors, constraints, invariants. Distinguish `Specified vs. Unspecified behavior`. Invent nothing silently.
- **Baseline, then interrogate** — minimum implementation first (RED → GREEN → REFACTOR), then boundaries, rule interactions, state, failures, double-submits, concurrency, security.
- **Classify discoveries** — A. implementation defect → fix code. B. test defect → fix test. C. spec gap → refine the spec, then code. Never silently choose behavior.
- **Coverage is an outcome** — happy, boundary, invalid, combinations, failure, state, security, concurrency, regression. Not a percentage.

Detail: `principles.md` (loop, phases, verification gate). Map: `platform.md`. Status: `PROJECT.md`. Consult assembly contract: `assemblies/loa-consult-platform/AGENTS.md` (exclusive standing contract for consult work — outranks ad-hoc instructions there).

## ⛔ MANDATORY: Docker From Repo Root Only — User Runs It

**NEVER run `docker compose` yourself. The USER runs docker commands.** Root-level `docker-compose.yml` only (`loa-platform`) — never an assembly-level compose file. Ask the user to run tests (e.g. `docker compose exec auth-app php artisan test`); never run overlapping suites; wait for pasted results.

## ⛔ MANDATORY: Specs Before Code (Rule 0)

**NEVER write code until the spec exists.** Search `kernels/`, `domains/`, `business-contexts/`, `services/`, `assemblies/`, `integration/specs/` → read it → Draft/missing means spec-first, Final means code exactly to it → owned elsewhere means reference by contract/ID. No spec + no prior discussion → ask first. **Spec authorship is not code** — a user request to create, edit, or promote a spec is always honored, never blocked.

## ⛔ MANDATORY: No CLI Without Permission (Rule 0.5)

No terminal commands of any kind without explicit current-session permission — even read-only ones. Report, don't run.

## ⛔ MANDATORY: No Auto-Pilot — Always Ask

Every significant action (code/spec writes, migrations, Docker, installs, tests, trackers, architecture, commits) needs an explicit yes first. Unsure → ask anyway.

## Testing

Behavioral coverage, one behavior per test, observable status + shape (detail: `principles.md`). **USER runs tests** — no change is complete until green results are pasted.

## Layers

```text
Product Assemblies ▲ Business Contexts ▲ Industry Domains ▲ Platform Services ▲ Platform Kernels
```

Dependencies point downward only; assemblies hold no business logic; contexts collaborate via events (detail: `platform.md`, `dependency-rules.md`). Shared plans stay independent (never defined by an assembly); assemblies declare what they compose; joint contracts live in `integration/specs/`. Ownership: reference by ID, never duplicate.

## When Starting

1. `platform.md` for placement → 2. spec exists? reuse : create first → 3. code only after approval. **No spec = no code.**

## Quick Reference

| You Need | Look In |
|----------|---------|
| Loop + gate + coding detail | `principles.md` |
| Architecture + gotchas | `platform.md` |
| Status + decisions | `PROJECT.md` |
| Dependencies | `dependency-rules.md` |
| Consult assembly | `assemblies/loa-consult-platform/AGENTS.md` (exclusive — working agreements, scaffold, status, spec pointers for consult only) |
| Specs | `kernels/` `domains/` `business-contexts/` `services/` `assemblies/` `integration/specs/` |
