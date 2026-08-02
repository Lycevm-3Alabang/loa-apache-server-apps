# Permission Registry

## Identity Kernel

> **SUPERSEDED** by `data-driven-permission-policy.md` (Final). The JSON registry format defined here was replaced by the `permissions.json` import format → `route_policies` table. Retained for historical reference only.

**Version:** 1.1
**Status:** Draft (Superseded)
**Layer:** Platform Kernel (Identity)
**Audience:** Architects, Engineers, AI Development Agents

### Purpose

A declarative contract where each app declares which claims its endpoints require. Replaces manual permission discovery with a machine-readable, version-controlled registry.

It answers:

> **"What claims does this app need, and where?"**

### Problem

Currently, permission discovery is manual. The Auth Platform seeds permission definitions into the global catalog, but there is no formal mechanism for apps to declare which permissions they consume. This creates:

- Stale permissions (granted but never enforced)
- Missing permissions (enforced but not registered)
- No automated validation that apps and the identity kernel agree on the permission catalog

### Ownership

- **Owns**: Registry schema, registry file format, registry validation contract
- **Does not own**: Claim types (`permission-claims.md`), permission resolution (`permission-resolution.md`), grant management (`group-permission-management.md`)

---

## Registry Format

Each app includes a `permissions.json` file at its project root.

### File Location

```
{assembly-root}/permissions.json
```

### Schema

```json
{
  "app": "string",
  "version": "string",
  "routes": [
    {
      "method": "string",
      "path": "string",
      "claims": ["string"]
    }
  ]
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `app` | string | yes | App identifier (matches directory name) |
| `version` | string | yes | Registry version (semver: `1.0.0`) |
| `routes` | array | yes | Endpoint claim requirements |

#### Route Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `method` | string | yes | HTTP method: `GET`, `POST`, `PUT`, `DELETE`, `*` |
| `path` | string | yes | Route path (uses `{param}` syntax for path parameters) |
| `claims` | array | yes | Required claims (empty array = deny all) |

#### Claim Values

| Claim | Meaning |
|-------|---------|
| `read` | View any resource |
| `write` | Create/update/delete any resource |
| `read-authored` | View own authored resources only |
| `write-authored` | Create/update/delete own authored resources only |
| `read-scoped` | View resources within user's scope |
| `write-scoped` | Create/update/delete resources within user's scope |
| `[]` (empty) | No access |

See `permission-claims.md` for full claim definitions.

---

## Validation Contract

### At Build Time

A validation tool scans all `{assembly-root}/permissions.json` files and:

1. Validates JSON schema (required fields, types)
2. Checks claim values are valid (`read`, `write`, `read-authored`, `write-authored`, `read-scoped`, `write-scoped`)
3. Detects endpoint collisions (same method + path with different claims)
4. Reports undeclared endpoints (API has routes not in registry)
5. Reports unimplemented claims (registry claims not used by any route)

**Output format:**

```
{app-name}: {count} routes, {errors} errors

Warnings:
  - GET /api/v1/certificates: claims ['read'] but no scope check found

Collisions:
  - GET /api/v1/certificates/{id}: conflicting claims ['read', 'read-authored']
```

### At Runtime (Middleware)

Apps use the registry to configure claim middleware declaratively. The registry is NOT read at runtime. The middleware checks the JWT claims against the required claims. The registry exists for validation and documentation.

### At Seed Time

A seeding tool reads all `permissions.json` files and ensures the global permission catalog contains every declared claim. The tool must:

1. Scan all `{assembly-root}/permissions.json` files
2. Parse each registry
3. Upsert each claim to the database (insert if missing)
4. Report inserted and skipped counts

---

## Invariants

1. Every endpoint MUST declare required claims in the registry
2. Empty claims array means no access (deny all)
3. Registry `app` field MUST match the directory name
4. Registry `version` MUST be valid semver
5. No two apps MAY declare conflicting claims for the same endpoint
6. Claims are checked on every request (no caching beyond token lifetime)
7. `read-scoped` and `write-scoped` require scope checks in API implementation
8. `read-authored` and `write-authored` require ownership checks in API implementation

---

## Lifecycle

### Adding a New Endpoint

1. Developer adds entry to app's `permissions.json`
2. Run validation — confirm no errors
3. Implement claim check in API middleware/controller
4. Deploy

### Changing Claims

1. Update `permissions.json` (bump `version`)
2. Run validation
3. Update API implementation if claim logic changes
4. Deploy

---

## Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Skipping claim check | Security violation | Check claims on every endpoint |
| Caching claim resolution | Stale permissions | Resolve per-request |
| Using `read` when `read-authored` needed | Over-permissioning | Use least-privilege claim |
| Reading `permissions.json` at runtime | File I/O on every request | Registry is for build-time validation only |
| Declaring claims but not implementing checks | False security | Always implement claim checks |

---

## Dependency References

| Spec | Role |
|------|------|
| `kernels/identity/entities/permission-claims.md` | Claim type definitions |
| `kernels/identity/rules/permission-resolution.md` | Resolution algorithm |
| `kernels/identity/tenancy.md` | Tenant scoping |
| `assemblies/loa-auth-platform/group-permission-management.md` | Admin UI for group claims |
