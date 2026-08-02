# Permission Registry

## Identity Kernel

**Version:** 1.0
**Status:** Final
**Layer:** Platform Kernel (Identity)
**Audience:** Architects, Engineers, AI Development Agents

### Purpose

A declarative contract where each app declares which permissions it requires, and which endpoints they protect. Replaces manual permission discovery with a machine-readable, version-controlled registry.

It answers:

> **"Which permissions does this app use, and where?"**

### Problem

Currently, permission discovery is manual. The Auth Platform seeds permission definitions into the global catalog, but there is no formal mechanism for apps to declare which permissions they consume. This creates:

- Stale permissions (granted but never enforced)
- Missing permissions (enforced but not registered)
- No automated validation that apps and the identity kernel agree on the permission catalog

### Ownership

- **Owns**: Registry schema, registry file format, registry validation contract
- **Does not own**: Permission definitions (existing `permission.md` entity), permission resolution (`permission-resolution.md`), grant management (`group-permission-management.md`)

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
  "permissions": [
    {
      "key": "string",
      "description": "string",
      "endpoints": [
        {
          "method": "string",
          "path": "string"
        }
      ],
      "page": "string | null",
      "required": "boolean"
    }
  ]
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `app` | string | yes | App identifier (matches directory name) |
| `version` | string | yes | Registry version (semver: `1.0.0`) |
| `permissions` | array | yes | List of permissions this app uses |

#### Permission Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | yes | Permission key (must match `{context}.{resource}.{action}` naming) |
| `description` | string | yes | Human-readable description |
| `endpoints` | array | no | API endpoints this permission protects |
| `page` | string | null | Admin page this permission gates (if not API-based) |
| `required` | boolean | yes | `true` = app cannot function without this permission; `false` = optional/legacy |

#### Endpoint Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `method` | string | yes | HTTP method: `GET`, `POST`, `PUT`, `DELETE`, `*` |
| `path` | string | yes | Route path (uses `{param}` syntax for path parameters) |

---

## Naming Convention

Permission keys follow the existing `{context}.{resource}.{action}` pattern from `permission.md`:

| Prefix | Context | Examples |
|--------|---------|----------|
| `cert.*` | Certificate Platform | `cert.certificates.issue`, `cert.templates.manage` |
| `consult.*` | Consult Platform | `consult.appointments.create`, `consult.evaluations.submit` |
| `users.*` | Auth Platform (cross-app) | `users.view`, `users.manage` |
| `web.*` | Auth Platform (session) | `web.admin` |

### Reserved Prefixes

| Prefix | Owner | Purpose |
|--------|-------|---------|
| `users.*` | Auth Platform | Cross-app user management |
| `tenants.*` | Auth Platform | Tenant administration |
| `web.*` | Auth Platform | Session-based admin access |

Apps MUST NOT use reserved prefixes for their own permissions.

---

## Validation Contract

### At Build Time

A validation tool scans all `{assembly-root}/permissions.json` files and:

1. Validates JSON schema (required fields, types)
2. Checks naming convention (`{context}.{resource}.{action}`)
3. Detects duplicate keys across apps
4. Detects endpoint collisions (same method + path protected by different keys)
5. Reports permissions declared but not seeded in database
6. Reports permissions seeded in database but not declared by any app

**Output format:**

```
{app-name}: {count} permissions, {errors} errors

Warnings:
  - {permission_key}: declared but not seeded in database

Collisions:
  - {METHOD} {path}: protected by {key1} AND {key2}
```

### At Runtime (Middleware)

Apps use the registry to configure permission middleware declaratively. The registry is NOT read at runtime. The middleware checks the JWT `permissions` claim against the hardcoded key. The registry exists for validation and documentation.

### At Seed Time

A seeding tool reads all `permissions.json` files and ensures the global permission catalog contains every declared key. The tool must:

1. Scan all `{assembly-root}/permissions.json` files
2. Parse each registry
3. Upsert each permission key to the database (insert if missing, update description if changed)
4. Report inserted, updated, and skipped counts

---

## Invariants

1. Every permission key in a registry MUST exist in the global `permissions` table
2. Permission keys MUST follow `{context}.{resource}.{action}` naming convention
3. Registry `app` field MUST match the directory name
4. Registry `version` MUST be valid semver
5. No two apps MAY declare the same permission key (single source of truth)
6. Apps MUST NOT use reserved prefixes (`users.*`, `tenants.*`, `web.*`)
7. `required: true` permissions are enforced at app startup (app refuses to start if key missing from database)
8. `required: false` permissions are logged as warnings but do not block startup

---

## Lifecycle

### Adding a New Permission

1. Developer adds entry to app's `permissions.json`
2. Run validation — confirm no errors
3. Run seeder — upsert to database
4. Add middleware to routes
5. Deploy

### Removing a Permission

1. Remove entry from `permissions.json`
2. Run validation — confirm no references remain
3. Remove middleware from routes
4. Run pruner — remove orphaned database rows
5. Deploy

### Rotating Permissions

When an app changes its permission structure:

1. Update `permissions.json` (bump `version`)
2. Run validation
3. Seed new keys
4. Prune old keys (only if no grants reference them)
5. Deploy

---

## Relationship to Existing Specs

| Spec | Relationship |
|------|-------------|
| `permission.md` | Registry entries map to `permissions` table rows (key, description) |
| `permission-resolution.md` | Registry does not affect resolution logic — grants are separate |
| `tenancy.md` | Registry is global (not tenant-scoped) — all apps share the same catalog |
| `group-permission-management.md` | Admin UI grants permissions declared by registries |

---

## Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Hardcoding permission checks without registering | Permissions become invisible to admin UI | Always declare in `permissions.json` |
| Using another app's permission prefix | Ownership violation, cross-app coupling | Use your own context prefix |
| Registering permissions but not using them | Dead configuration, admin confusion | Remove unused entries |
| Skipping validation before deploy | Stale registries break permission grants | Always run validation |
| Reading `permissions.json` at runtime | File I/O on every request | Registry is for build-time validation only |
| Declaring the same permission in multiple apps | Ambiguous ownership, grant conflicts | One permission key = one owner app |

---

## Security Checklist

- [ ] All protected endpoints have a corresponding registry entry
- [ ] No permission key is declared by multiple apps
- [ ] Reserved prefixes are not used by tenant apps
- [ ] Validation runs in CI before deploy
- [ ] Seeder runs on deploy to ensure database parity
- [ ] `required: true` permissions block app startup if missing
- [ ] Registry version is bumped on every change (audit trail)

---

## Implementation Inventory

### New Files

| File | Purpose |
|------|---------|
| `{assembly-root}/permissions.json` | App permission declarations |

### New Tools (platform-specific implementations)

| Tool | Purpose |
|------|---------|
| Registry Validator | Scan registries, validate schema, detect conflicts |
| Registry Seeder | Upsert registry entries to global permission catalog |
| Registry Pruner | Remove orphaned permissions (no registry + no grants) |

### Modified Files

| File | Change |
|------|--------|
| Permission seeder | Read from `permissions.json` files instead of hardcoded list |

---

## Dependency References

| Spec | Role |
|------|------|
| `kernels/identity/entities/permission.md` | Permission entity definition, naming convention |
| `kernels/identity/rules/permission-resolution.md` | Resolution algorithm (unchanged by registry) |
| `kernels/identity/tenancy.md` | Tenant scoping (registry is global) |
| `assemblies/loa-auth-platform/group-permission-management.md` | Admin UI for grants (consumes registry keys) |
