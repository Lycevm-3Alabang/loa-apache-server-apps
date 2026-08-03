# Access Config Import / Export

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface
**Audience:** Architects, Engineers, AI Development Agents

> Companion to `tenant-group-endpoint-grants.md` and `tenant-endpoint-catalog.md`.
>
> This spec adds JSON-based **template download**, **export**, and **import** for the full access configuration: groups (with priority), endpoint grants per group, and per-user endpoint overrides. It enables admins to snapshot, migrate, and bulk-provision access configs across tenants without manual UI entry.

---

## 1. Purpose

It answers:

> **"How do I download a template, fill in my access config, and import it? Or export my current config and re-import it into another tenant?"**

Three operations:

| Operation | Direction | Description |
|-----------|-----------|-------------|
| **Template** | Download | Blank JSON with correct structure + comments. Admin fills in, then imports. |
| **Export** | Download | Current config for a tenant serialized as JSON. Admin edits or imports into another tenant. |
| **Import** | Upload | JSON file (template or export) applied to a tenant. Upserts groups, grants, and overrides. |

---

## 2. Ownership

### Owns

- JSON schema for the access config payload.
- Template generation endpoint (returns empty payload with structural hints).
- Export endpoint (serializes current tenant config to JSON).
- Import endpoint (validates + applies JSON payload to a tenant).
- Admin UI for template download, file upload, and import preview/confirmation.

### Does Not Own

- Individual group CRUD, grant CRUD, or override CRUD (owned by `tenant-group-endpoint-grants.md`).
- Endpoint catalog management (owned by `tenant-endpoint-catalog.md`).
- JWT issuance or session resolution (owned by `IdentityService`).

---

## 3. JSON Schema

The access config payload has three top-level sections:

```jsonc
{
  "version": "1.0",
  "exported_at": "2026-08-03T14:30:00Z",   // export only; ignored on import
  "tenant_slug": "loa",                      // export only; ignored on import (target tenant from route)

  "groups": [
    {
      "name": "Faculty",
      "description": "Teaching staff",
      "priority": 5,
      "grants": [
        { "method": "GET",  "path": "/api/v1/appointments",        "level": "read" },
        { "method": "POST", "path": "/api/v1/appointments",        "level": "write" },
        { "method": "GET",  "path": "/api/v1/certificates",        "level": "read" },
        { "method": "POST", "path": "/api/v1/certificates/{id}/sign", "level": "admin" }
      ]
    },
    {
      "name": "Student-Readonly",
      "description": "Students — read-only access",
      "priority": 20,
      "grants": [
        { "method": "GET", "path": "/api/v1/appointments", "level": "read" },
        { "method": "GET", "path": "/api/v1/certificates", "level": "read" }
      ]
    }
  ],

  "user_overrides": [
    {
      "email": "dean@loa.edu.ph",
      "overrides": [
        { "method": "DELETE", "path": "/api/v1/appointments/{id}", "level": "write" }
      ]
    }
  ]
}
```

### 3.1 Field Rules

| Field | Required | Notes |
|-------|----------|-------|
| `version` | Yes | `"1.0"` — future-proofs schema changes |
| `groups` | No | Array of group objects. Empty array or absent = no groups to import. |
| `groups[].name` | Yes | Group name. Used as the upsert key (unique per tenant). |
| `groups[].description` | No | Defaults to `null`. |
| `groups[].priority` | No | Integer 1–100, default 10. Lower = higher precedence. |
| `groups[].grants` | No | Array of grant objects. Empty = no grants for this group. |
| `groups[].grants[].method` | Yes | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, or `*` |
| `groups[].grants[].path` | Yes | Must match a cataloged endpoint for the target tenant (or platform-wide). |
| `groups[].grants[].level` | Yes | `read`, `write`, `admin`, or `deny` |
| `user_overrides` | No | Array of override objects. |
| `user_overrides[].email` | Yes | User email. Resolved to `user_id` at import time. |
| `user_overrides[].overrides` | Yes | Array of override entries. |
| `user_overrides[].overrides[].method` | Yes | HTTP method. |
| `user_overrides[].overrides[].path` | Yes | Must match a cataloged endpoint. |
| `user_overrides[].overrides[].level` | Yes | `read`, `write`, `admin`, or `deny` |

### 3.2 Template vs Export

| Feature | Template | Export |
|---------|----------|--------|
| `version` | `"1.0"` | `"1.0"` |
| `exported_at` | absent | ISO 8601 timestamp |
| `tenant_slug` | absent | Current tenant slug |
| `groups` | 2 example entries (commented out) | All groups with real data |
| `groups[].grants` | 3 example entries | All real grants |
| `user_overrides` | 1 example entry | All real overrides |

---

## 4. API Contracts

### 4.1 Download Template

`GET /admin/tenants/{tenant}/access-config/template`

**Response:** `application/json` file download (`Content-Disposition: attachment`).

Returns the template JSON (§3) with example data. The `groups` array contains placeholder entries with `_comment` fields explaining each field. These comments are stripped on parse.

**Template content:**

```jsonc
{
  "version": "1.0",
  "groups": [
    {
      "name": "Example-Group",
      "description": "Replace with actual group name",
      "priority": 10,
      "_comment": "priority: 1=highest, 100=lowest, default=10",
      "grants": [
        {
          "method": "GET",
          "path": "/api/v1/example",
          "level": "read",
          "_comment": "level: read | write | admin | deny"
        }
      ]
    }
  ],
  "user_overrides": [
    {
      "email": "user@example.com",
      "_comment": "User must already exist in the system",
      "overrides": [
        {
          "method": "GET",
          "path": "/api/v1/example",
          "level": "read"
        }
      ]
    }
  ]
}
```

### 4.2 Export Current Config

`GET /admin/tenants/{tenant}/access-config/export`

**Response:** `application/json` file download.

Serializes all groups (with priority), their grants, and all user overrides for the target tenant. Platform-wide groups and grants are included if they apply to this tenant.

**Response shape:** §3 schema with real data, plus `exported_at` and `tenant_slug`.

### 4.3 Import Config

`POST /admin/tenants/{tenant}/access-config/import`

**Request:** `multipart/form-data` with a `file` field containing the JSON file.

**OR** `application/json` with the payload directly in the body (for API consumers).

**Flow:**

1. **Parse** JSON. Return `422` on malformed JSON.
2. **Validate** schema (§3.1). Return `422` with field-level errors.
3. **Preview** — compute what will be created/updated/skipped. Return preview without applying.
4. **Confirm** — if `confirm=true` (form checkbox or query param), apply changes inside a DB transaction.

**Preview response (200):**

```jsonc
{
  "status": "preview",
  "groups": {
    "create": ["Faculty", "Student-Readonly"],
    "update": ["Existing-Group"],
    "skip": [],
    "errors": []
  },
  "grants": {
    "upsert": 12,
    "skip": 0,
    "errors": []
  },
  "user_overrides": {
    "upsert": 1,
    "skip": 0,
    "errors": ["User not found: unknown@loa.edu.ph"]
  },
  "endpoint_validation": {
    "valid": true,
    "missing_endpoints": []
  }
}
```

**Apply response (200):**

```jsonc
{
  "status": "applied",
  "groups": {
    "created": 2,
    "updated": 1,
    "skipped": 0
  },
  "grants": {
    "upserted": 12,
    "skipped": 0
  },
  "user_overrides": {
    "upserted": 1,
    "skipped": 0,
    "errors": []
  }
}
```

### 4.4 Import Validation (dry-run)

`POST /admin/tenants/{tenant}/access-config/import?dry_run=true`

Same as §4.3 but never writes. Always returns the preview.

---

## 5. Import Logic

### 5.1 Group Upsert

For each group in `groups[]`:

1. Look up `UserGroup` where `name = group.name` AND `tenant_id = targetTenantId`.
2. **If found:** update `description`, `priority` (if provided).
3. **If not found:** create `UserGroup` with `name`, `description`, `priority`, `tenant_id = targetTenantId`.
4. Group name is the **natural key** — no ID required in the JSON.

### 5.2 Grant Upsert

For each grant in `groups[].grants[]`:

1. Validate the endpoint `(method, path)` exists in `tenant_app_endpoint` for the target tenant (or is platform-wide, `tenant_id NULL`). If not, add to `missing_endpoints` error list.
2. Upsert `TenantEndpointGrant` matching on `(group_id, tenant_id, method, path)`. Set `level` from the JSON.
3. Grants not present in the JSON are **left untouched** (no destructive delete). To revoke grants, the admin should use the UI or include `"level": "none"` in the JSON (which deletes the grant row).

### 5.3 User Override Upsert

For each override in `user_overrides[]`:

1. Resolve `user_id` from `email` via `users` table. If not found, add to errors.
2. Validate the endpoint exists in the catalog.
3. Upsert `TenantEndpointOverride` matching on `(user_id, tenant_id, method, path)`.
4. Overrides not present in the JSON are **left untouched**.

### 5.4 "none" Level Handling

If a grant or override has `"level": "none"`:
- **Grants:** delete the `TenantEndpointGrant` row (revert to default resolution).
- **Overrides:** delete the `TenantEndpointOverride` row (revert to group resolution).

This allows the JSON to explicitly revoke access.

### 5.5 Platform-Wide Groups

If a group in the JSON has `"tenant_id": null` (exported from a platform-wide group), the import:
- Requires platform-admin (`loa-auth-admin`) to import.
- Creates/updates the group with `tenant_id = NULL`.
- Grant `tenant_id` is set to `NULL` (platform-wide).

In the JSON, platform-wide groups are indicated by absence of a tenant scope (the import target tenant determines scope).

---

## 6. Admin UI

### 6.1 Access Config Actions Bar

Added to the tenant show page (`/admin/tenants/{tenant}`) and the tenant groups page (`/admin/tenants/{tenant}/groups`):

| Button | Action | Route |
|--------|--------|-------|
| **Download template** | Downloads blank JSON template | `admin.tenants.access-config.template` |
| **Export config** | Downloads current config as JSON | `admin.tenants.access-config.export` |
| **Import config** | Opens file upload dialog | `admin.tenants.access-config.import` |

### 6.2 Import Dialog

A modal or dedicated page with:

1. **File upload** — `<input type="file" accept=".json">` or a textarea for pasting JSON.
2. **Preview button** — sends the file/payload to the import endpoint (dry-run).
3. **Preview results** — shows created/updated/skipped/errors in a table.
4. **Confirm checkbox** — "I understand this will modify groups and permissions."
5. **Apply button** — sends with `confirm=true`.

### 6.3 Import Errors

Errors are non-fatal where possible:
- Unknown user email → skip that override, report error.
- Missing endpoint → skip that grant, report error.
- Duplicate group name → update existing group (not an error).
- Invalid JSON → hard stop, show parse error.

### 6.4 Export Download

Click "Export config" → browser downloads `access-config-{tenant_slug}-{date}.json`.

---

## 7. Routes

### 7.1 Web Routes (under `admin-dashboard.md` §3.8 route group)

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/admin/tenants/{tenant}/access-config/template` | download template | `admin.tenants.access-config.template` |
| `GET` | `/admin/tenants/{tenant}/access-config/export` | download export | `admin.tenants.access-config.export` |
| `GET` | `/admin/tenants/{tenant}/access-config/import` | show import form | `admin.tenants.access-config.import` |
| `POST` | `/admin/tenants/{tenant}/access-config/import` | process import | `admin.tenants.access-config.import.store` |

### 7.2 API Routes

| Method | URI | Action |
|--------|-----|--------|
| `GET` | `/api/v1/admin/tenants/{tenant}/access-config/template` | download template |
| `GET` | `/api/v1/admin/tenants/{tenant}/access-config/export` | download export |
| `POST` | `/api/v1/admin/tenants/{tenant}/access-config/import` | import (JSON body) |
| `POST` | `/api/v1/admin/tenants/{tenant}/access-config/import?dry_run=true` | validate only |

---

## 8. Controller

New controller: `AccessConfigController`

```
App\Http\Controllers\AccessConfigController
```

**Dependencies:**
- `PermissionPolicyService` — for `isPlatformAdmin()` check
- `TenantService` — for tenant lookup + validation
- `AuthorizationService` — for group membership (if needed)

**Methods:**

| Method | Description |
|--------|-------------|
| `template(Tenant $tenant)` | Returns template JSON as download |
| `export(Tenant $tenant)` | Serializes current config to JSON download |
| `importForm(Tenant $tenant)` | Shows the import Blade view |
| `import(Request $request, Tenant $tenant)` | Validates + previews + applies JSON import |

---

## 9. Export Serialization

The export method queries:

1. **Groups:** `UserGroup::where('tenant_id', $tenantId)->orWhereNull('tenant_id')->get()`
2. **Grants per group:** For each group, `TenantEndpointGrant::where('group_id', $group->id)->where('tenant_id', $tenantId)->get()`
3. **User overrides:** `TenantEndpointOverride::where('tenant_id', $tenantId)->get()`

Serializes to the §3 JSON schema with `exported_at` timestamp.

**Platform-wide groups** (tenant_id NULL) are included only if they have grants that apply to this tenant.

---

## 10. Validation Rules

### 10.1 Schema Validation

```php
$validator = Validator::make($data, [
    'version' => 'required|string|in:1.0',
    'groups' => 'nullable|array',
    'groups.*.name' => 'required|string|max:255',
    'groups.*.description' => 'nullable|string|max:255',
    'groups.*.priority' => 'nullable|integer|min:1|max:100',
    'groups.*.grants' => 'nullable|array',
    'groups.*.grants.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
    'groups.*.grants.*.path' => 'required|string|max:512',
    'groups.*.grants.*.level' => 'required|string|in:read,write,admin,deny,none',
    'user_overrides' => 'nullable|array',
    'user_overrides.*.email' => 'required|email',
    'user_overrides.*.overrides' => 'required|array',
    'user_overrides.*.overrides.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
    'user_overrides.*.overrides.*.path' => 'required|string|max:512',
    'user_overrides.*.overrides.*.level' => 'required|string|in:read,write,admin,deny,none',
]);
```

### 10.2 Business Validation

After schema validation:

1. **Endpoint existence:** Each grant/override `(method, path)` must exist in `tenant_app_endpoint` for the target tenant (or platform-wide).
2. **User existence:** Each `user_overrides[].email` must exist in `users`.
3. **Group name uniqueness:** Within the target tenant, group names must be unique. Duplicates in the same JSON payload → last-wins.
4. **Priority range:** 1–100. Default 10 if omitted.

---

## 11. Invariants

1. Import is **tenant-scoped** — all groups, grants, and overrides are created under the target tenant.
2. Import is **additive by default** — existing groups are updated, existing grants are upserted. Groups/grants not in the JSON are **not deleted**.
3. `"level": "none"` explicitly deletes a grant or override row (revert to default).
4. Platform-wide imports (tenant_id NULL groups/grants) require platform-admin.
5. Export includes platform-wide groups that apply to the target tenant.
6. Template is always safe to download — no side effects.
7. Import preview never writes — dry-run only.
8. JSON `version` field enables future schema migration without breaking existing imports.
9. Group name is the natural key — no IDs in the JSON payload.

---

## 12. Security Checklist

- [ ] All routes behind `auth` (web guard) + `web.admin` + `users.manage`
- [ ] Platform-wide imports limited to `loa-auth-admin`
- [ ] CSRF on every POST (web routes)
- [ ] File upload: validate JSON mime type, max size (1 MB)
- [ ] No SQL injection — all queries use Eloquent
- [ ] Import preview never writes to DB
- [ ] Export does not leak sensitive data (passwords, tokens — groups/grants only)

---

## 13. Implementation Inventory

| Layer | Item | Status |
|-------|------|--------|
| Spec | `access-config-import-export.md` | **Draft** |
| Controller | `AccessConfigController` | To implement |
| Routes | Web + API routes for template/export/import | To add |
| Model | No new models — uses existing `UserGroup`, `TenantEndpointGrant`, `TenantEndpointOverride` | Existing |
| Migration | None required | — |
| Admin UI | Import dialog (file upload + preview + confirm) | To implement |
| Admin UI | Template + Export download buttons on tenant pages | To implement |
| Tests | Controller tests for template/export/import/dry-run | To write |

---

## 14. Dependency References

| Spec | Role |
|------|------|
| `tenant-group-endpoint-grants.md` (Final v1.1) | Defines groups, grants, overrides, priority resolution — this spec imports/exports that data |
| `tenant-endpoint-catalog.md` (Final v3.2) | Defines the endpoint catalog — import validates grants/overrides against it |
| `admin-dashboard.md` | Route group, admin UI patterns |
| `kernels/identity/entities/user-group.md` | Group entity with `priority` |
| `kernels/identity/tenancy.md` | Tenant scoping, membership model |
