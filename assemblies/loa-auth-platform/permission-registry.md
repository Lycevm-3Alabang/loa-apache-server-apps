# LOA Auth Platform — Permission Registry Implementation
## Assembly-Level Implementation Guide

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** Engineers, AI Development Agents

---

# 1. Purpose

This document provides the LOA-specific implementation details for the permission registry contract defined in `kernels/identity/entities/permission-registry.md`. It contains:

- Concrete `permissions.json` files for each LOA assembly
- Artisan commands for validation, seeding, and pruning
- Seeder implementation that reads from registries

---

# 2. Registry Files

## 2.1 Cert Platform (`assemblies/loa-cert-platform/permissions.json`)

```json
{
  "app": "loa-cert-platform",
  "version": "1.0.0",
  "permissions": [
    {
      "key": "cert.events.manage",
      "description": "Create, update, delete events",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/events" },
        { "method": "PUT", "path": "/api/v1/events/{id}" },
        { "method": "DELETE", "path": "/api/v1/events/{id}" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "cert.attendees.manage",
      "description": "Add, remove, import event attendees",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/events/{id}/attendees" },
        { "method": "POST", "path": "/api/v1/events/{id}/attendees/import" },
        { "method": "DELETE", "path": "/api/v1/events/{id}/attendees/{aid}" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "cert.templates.manage",
      "description": "Create, update, delete certificate templates",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/templates" },
        { "method": "PUT", "path": "/api/v1/templates/{id}" },
        { "method": "DELETE", "path": "/api/v1/templates/{id}" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "cert.certificates.issue",
      "description": "Issue individual or bulk certificates",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/certificates" },
        { "method": "POST", "path": "/api/v1/certificates/bulk" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "cert.certificates.manage",
      "description": "Full certificate lifecycle (revoke, delete, email)",
      "endpoints": [
        { "method": "PUT", "path": "/api/v1/certificates/{id}/revoke" },
        { "method": "DELETE", "path": "/api/v1/certificates/{id}" },
        { "method": "POST", "path": "/api/v1/certificates/{id}/email" }
      ],
      "page": null,
      "required": false
    },
    {
      "key": "cert.certificates.view_all",
      "description": "View all certificates (not just own)",
      "endpoints": [
        { "method": "GET", "path": "/api/v1/certificates" }
      ],
      "page": null,
      "required": false
    },
    {
      "key": "cert.admin.dashboard",
      "description": "Access admin dashboard and user management",
      "endpoints": [
        { "method": "GET", "path": "/api/v1/admin/dashboard" },
        { "method": "GET", "path": "/api/v1/admin/users" },
        { "method": "PUT", "path": "/api/v1/admin/users/{id}/role" }
      ],
      "page": "/admin",
      "required": false
    }
  ]
}
```

## 2.2 Consult Platform (`assemblies/loa-consult-platform/permissions.json`)

```json
{
  "app": "loa-consult-platform",
  "version": "1.0.0",
  "permissions": [
    {
      "key": "consult.appointments.create",
      "description": "Create consultation appointments",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/appointments" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "consult.appointments.view",
      "description": "View consultation appointments",
      "endpoints": [
        { "method": "GET", "path": "/api/v1/appointments" },
        { "method": "GET", "path": "/api/v1/appointments/{id}" }
      ],
      "page": null,
      "required": true
    },
    {
      "key": "consult.evaluations.submit",
      "description": "Submit evaluation results",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/evaluations" }
      ],
      "page": null,
      "required": false
    }
  ]
}
```

## 2.3 Auth Platform (`assemblies/loa-auth-platform/permissions.json`)

```json
{
  "app": "loa-auth-platform",
  "version": "1.0.0",
  "permissions": [
    {
      "key": "users.view",
      "description": "View user list",
      "endpoints": [
        { "method": "GET", "path": "/api/v1/users" }
      ],
      "page": "/admin/users",
      "required": true
    },
    {
      "key": "users.manage",
      "description": "Manage users (enable/disable, create, assign groups)",
      "endpoints": [
        { "method": "POST", "path": "/api/v1/users" },
        { "method": "PUT", "path": "/api/v1/users/{id}" },
        { "method": "POST", "path": "/api/v1/groups" },
        { "method": "DELETE", "path": "/api/v1/groups/{id}" },
        { "method": "POST", "path": "/api/v1/groups/{id}/permissions" },
        { "method": "POST", "path": "/api/v1/users/{id}/groups" },
        { "method": "POST", "path": "/api/v1/users/{id}/permissions" }
      ],
      "page": "/admin/groups",
      "required": true
    },
    {
      "key": "web.admin",
      "description": "Access admin dashboard (session-based)",
      "endpoints": [],
      "page": "/admin",
      "required": true
    }
  ]
}
```

---

# 3. Validation Command

## `permissions:validate`

Scans all `permissions.json` files across assemblies and reports errors, warnings, and collisions.

```bash
php artisan permissions:validate
```

**Behavior:**

1. Scan `assemblies/*/permissions.json`
2. Validate JSON schema (required fields, types)
3. Check naming convention (`{context}.{resource}.{action}`)
4. Detect duplicate keys across apps
5. Detect endpoint collisions (same method + path protected by different keys)
6. Compare against database: report declared-but-unseeded and seeded-but-undeclared
7. Check `required: true` permissions exist in database
8. Exit with code 1 if any errors found

**Output:**

```
✓ loa-cert-platform: 7 permissions, 0 errors
✓ loa-consult-platform: 3 permissions, 0 errors
✓ loa-auth-platform: 3 permissions, 0 errors

Warnings:
  - cert.certificates.view_all: declared but not seeded in database
  - consult.evaluations.submit: declared but not seeded in database

Collisions:
  - GET /api/v1/certificates: protected by cert.certificates.view_all AND cert.certificates.manage

Errors (must fix before deploy):
  - cert.certificates.manage: required permission not found in database
```

---

# 4. Seeder Command

## `permissions:seed`

Reads all `permissions.json` files and upserts to the `permissions` table.

```bash
php artisan permissions:seed
```

**Behavior:**

1. Scan `assemblies/*/permissions.json`
2. For each permission entry:
   - If `key` exists: update `description`
   - If `key` missing: insert new row
3. Report inserted, updated, skipped counts
4. Do NOT delete any permissions (use `permissions:prune` for that)

**Output:**

```
✓ loa-cert-platform: 3 inserted, 4 updated, 0 skipped
✓ loa-consult-platform: 3 inserted, 0 updated, 0 skipped
✓ loa-auth-platform: 3 inserted, 0 updated, 0 skipped

Total: 9 inserted, 4 updated, 0 skipped
```

---

# 5. Pruner Command

## `permissions:prune`

Removes permissions from the database that are not declared in any registry and have no grants.

```bash
php artisan permissions:prune
```

**Behavior:**

1. Scan all `permissions.json` files, collect declared keys
2. Query `permissions` table for keys NOT in declared set
3. For each undeclared key:
   - Check if any grants reference it (`user_group_permission` or `user_permission`)
   - If no grants: delete from `permissions` table
   - If grants exist: skip (report as "protected by grants")
4. Report deleted, protected, skipped counts

**Output:**

```
Pruning permissions not declared in any registry...

Deleted (no grants):
  - old.permission.one
  - old.permission.two

Protected (have grants):
  - legacy.permission: 3 grants found, skipped

Total: 2 deleted, 1 protected, 0 skipped
```

---

# 6. Seeder Integration

The existing `PermissionSeeder` should be updated to read from `permissions.json` files instead of a hardcoded array.

**Current (hardcoded):**

```php
$permissions = [
    ['key' => 'users.view', 'description' => 'View user list'],
    ['key' => 'users.manage', 'description' => 'Manage users'],
    // ...
];

foreach ($permissions as $perm) {
    Permission::updateOrCreate(['key' => $perm['key']], $perm);
}
```

**Updated (registry-based):**

```php
$registries = glob(base_path('assemblies/*/permissions.json'));

foreach ($registries as $file) {
    $registry = json_decode(file_get_contents($file), true);

    foreach ($registry['permissions'] as $perm) {
        Permission::updateOrCreate(
            ['key' => $perm['key']],
            ['description' => $perm['description']]
        );
    }
}
```

---

# 7. CI Integration

Add validation to CI pipeline before deploy:

```yaml
# .github/workflows/deploy.yml (example)
- name: Validate permissions
  run: php artisan permissions:validate

- name: Seed permissions
  run: php artisan permissions:seed
```

---

# 8. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Editing `permissions.json` without running validation | Stale registries break grants | Always validate before deploy |
| Hardcoding permissions in seeder after implementing registry | Two sources of truth | Registry is the single source |
| Skipping `permissions:seed` on deploy | Database out of sync with code | Seed as part of deploy |
| Running `permissions:prune` without checking grants | Data loss | Always check grants first |

---

# 9. Dependency References

| Spec | Role |
|------|------|
| `kernels/identity/entities/permission-registry.md` | Abstract contract (schema, invariants, lifecycle) |
| `kernels/identity/entities/permission.md` | Permission entity definition |
| `assemblies/loa-auth-platform/group-permission-management.md` | Admin UI for grants |
| `assemblies/loa-cert-platform/README.md` | Cert Platform API surface |
| `assemblies/loa-consult-platform/README.md` | Consult Platform API surface |
