# AI Agent Instructions

## ⛔ MANDATORY: Specs Before Code

**NEVER write code until the spec exists.**

This is a hard requirement, not a suggestion. Violations are treated as failures.

Before writing, modifying, or refactoring ANY code, the AI agent MUST:

1. Search the repo for the relevant spec `.md` file (kernels/, domains/, business-contexts/, services/, assemblies/)
2. Read the spec completely
3. If NO spec exists → **stop and write the spec first** (or ask the user)
4. If a spec exists but is incomplete → **finish the spec first**
5. Only after the spec is written and approved → write code that matches it exactly

If the task has no spec and no prior discussion, ask the user before writing any code.

**Rule of thumb:** Spec exists → code. Spec missing → spec first. Always.

## Development Pattern

**Spec-first, code second.**

1. Develop the spec (.md files) before any code
2. Review the spec for bottlenecks and redesign
3. Only then write code against the spec

This preserves architectural integrity and allows early detection of issues.

## Ownership

Always check ownership before creating entities. Never duplicate shared concepts.

## Layer Responsibilities

```
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

- Dependencies point downward only
- Never generate upward or circular dependencies
- Assemblies contain no business logic
- Contexts never reference each other directly (use events)

## When Starting Development

1. Check `dependency-rules.md`
2. Check `AI-GUIDE.md` for layer placement
3. **Check if the spec already exists** — reuse it
4. **If no spec exists, create it first** in the correct layer folder
5. Follow folder structure: `README.md`, `entities/`, `events/`, `contracts/`, `rules/`
6. Only code after spec is approved

**No spec = no code. This is mandatory, not optional.**

## Quick Reference

| You Need | Look In |
|----------|---------|
| Architecture | `AI-GUIDE.md` |
| Naming | `AI-RULES.md` |
| Dependencies | `dependency-rules.md` |
| Kernels | `kernels/` |
| Domains | `domains/` |
| Contexts | `business-contexts/` |
| Services | `services/` |
| Assemblies | `assemblies/` |
