# Access Config Import / Export

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
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
  "tenant_slug": "loa-e-cert",               // export only; ignored on import (target tenant from route)

  "groups": [
    {
      "name": "Faculty",
      "description": "Teaching staff",
      "priority": 5,
      "tenant_id": "tenant_loa",
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
      "tenant_id": "tenant_loa",
      "grants": [
        { "method": "GET", "path": "/api/v1/appointments", "level": "read" },
        { "method": "GET", "path": "/api/v1/certificates", "level": "read" }
      ]
    }
  ],

  "user_overrides": [
    {
      "email": "dean@lyceumalabang.edu.ph",
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
| `groups[].priority` | No | Integer 1–10000, default 10. Lower = higher precedence. |
| `groups[].tenant_id` | No | Export-only. Tenant ID or `null` for platform-wide groups. Ignored on import (target tenant from route). |
| `groups[].grants` | No | Array of grant objects. Empty = no grants for this group. |
| `groups[].grants[].method` | Yes | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, or `*` (matches any method) |
| `groups[].grants[].path` | Yes | Must match a cataloged endpoint for the target tenant (or platform-wide). |
| `groups[].grants[].level` | Yes | `read`, `write`, `admin`, or `deny` (see §5.4) |
| `user_overrides` | No | Array of override objects. |
| `user_overrides[].email` | Yes | User email. Resolved to `user_id` at import time. |
| `user_overrides[].overrides` | Yes | Array of override entries. |
| `user_overrides[].overrides[].method` | Yes | HTTP method. |
| `user_overrides[].overrides[].path` | Yes | Must match a cataloged endpoint. |
| `user_overrides[].overrides[].level` | Yes | `read`, `write`, `admin`, or `deny` (see §5.4) |

### 3.2 Template vs Export

| Feature | Template | Export |
|---------|----------|--------|
| `version` | `"1.0"` | `"1.0"` |
| `exported_at` | absent | ISO 8601 timestamp |
| `tenant_slug` | absent | Current tenant slug |
| `groups` | 3 example entries (with `_comment` fields) | All groups with real data |
| `groups[].tenant_id` | absent | Each group's `tenant_id` (or `null` for platform-wide) |
| `groups[].grants` | 3–6 example entries | All real grants |
| `user_overrides` | 1 example entry | All real overrides |

---

## 4. API Contracts

### 4.1 Download Template

`GET /admin/tenants/{tenant}/access-config/template`

**Response:** `application/json` file download (`Content-Disposition: attachment`).

Returns the template JSON (§3) with sample placeholder data. The `groups` array contains realistic example entries with `_comment` fields explaining each field. These comments are stripped on parse.

**Template content:**

```jsonc
{
  "version": "1.0",
  "groups": [
    {
      "name": "Faculty",
      "description": "Teaching staff — full access to appointments and certificates",
      "priority": 5,
      "_comment": "priority: 1=highest, 10000=lowest, default=10. Lower value = higher precedence.",
      "grants": [
        { "method": "GET",  "path": "/api/v1/appointments",            "level": "read" },
        { "method": "POST", "path": "/api/v1/appointments",            "level": "write" },
        { "method": "PUT",  "path": "/api/v1/appointments/{id}",       "level": "write" },
        { "method": "DELETE","path": "/api/v1/appointments/{id}",       "level": "admin" },
        { "method": "GET",  "path": "/api/v1/certificates",            "level": "read" },
        { "method": "POST", "path": "/api/v1/certificates/{id}/sign",  "level": "admin" }
      ]
    },
    {
      "name": "Students",
      "description": "Students — read-only access to appointments and certificates",
      "priority": 20,
      "grants": [
        { "method": "GET", "path": "/api/v1/appointments",  "level": "read" },
        { "method": "GET", "path": "/api/v1/certificates",  "level": "read" }
      ]
    },
    {
      "name": "Registrar-Staff",
      "description": "Registrar — can manage certificates but not appointments",
      "priority": 10,
      "_comment": "This group has higher priority than Students (10 < 20). If both grants conflict, this group wins.",
      "grants": [
        { "method": "GET",  "path": "/api/v1/certificates",            "level": "read" },
        { "method": "POST", "path": "/api/v1/certificates",            "level": "write" },
        { "method": "PUT",  "path": "/api/v1/certificates/{id}",       "level": "write" },
        { "method": "DELETE","path": "/api/v1/certificates/{id}",       "level": "admin" }
      ]
    }
  ],
  "user_overrides": [
    {
      "email": "dean@lyceumalabang.edu.ph",
      "_comment": "User must already exist in the system. Overrides replace group-resolution for that endpoint.",
      "overrides": [
        { "method": "DELETE", "path": "/api/v1/appointments/{id}", "level": "write" }
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
3. **Check** tenant is active. Return `403` if suspended.
4. **Preview** — compute what will be created/updated/skipped. Return preview without applying.
5. **Confirm** — if `confirm=true` (form checkbox or query param), apply changes inside a DB transaction.

**Parameter modes:**

| Parameters | Behavior |
|------------|----------|
| `POST .../import` (no params) | Preview only |
| `POST .../import?dry_run=true` | Preview only (explicit alias) |
| `POST .../import?confirm=true` | Apply changes |
| `POST .../import?dry_run=true&confirm=true` | `dry_run` wins — preview only (safe default) |

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
    "errors": ["User not found: unknown@lyceumalabang.edu.ph"]
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
- **Grants:** delete the `TenantEndpointGrant` row if it exists (revert to default resolution). If the row does not exist, this is a silent no-op.
- **Overrides:** delete the `TenantEndpointOverride` row if it exists (revert to group resolution). If the row does not exist, this is a silent no-op.

This allows the JSON to explicitly revoke access. The response reports `"skipped": 0` for no-op deletions (the row was already absent).

### 5.5 Platform-Wide Groups

Platform-wide groups (`tenant_id = NULL`) can only be exported, not imported via the tenant-scoped import route. The import route always targets a specific tenant — groups in the JSON are created/updated under that tenant.

When importing an export that contains platform-wide groups:
- Platform-wide groups in the JSON are **skipped** with a warning: `"Platform-wide group '{name}' skipped — use platform admin to manage."`
- Platform-wide grants on those groups are **not imported** (they are not tenant-scoped).

Platform-wide group management is handled directly via the platform admin routes in `admin-dashboard.md`, not via this import mechanism.

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
| `POST` | `/api/v1/admin/tenants/{tenant}/access-config/import` | preview import (default) |
| `POST` | `/api/v1/admin/tenants/{tenant}/access-config/import?dry_run=true` | preview import (explicit) |
| `POST` | `/api/v1/admin/tenants/{tenant}/access-config/import?confirm=true` | apply import |

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
2. **Grants per group:** For each group, `TenantEndpointGrant::where('group_id', $group->id)->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))->get()`
3. **User overrides:** `TenantEndpointOverride::where('tenant_id', $tenantId)->get()`

Serializes to the §3 JSON schema with `exported_at` timestamp. Each group's `tenant_id` is included in the JSON (or `null` for platform-wide groups).

**Platform-wide groups** (tenant_id NULL) are included in the export. Their grants are included if they have `tenant_id = NULL` (platform-wide) or `tenant_id = $tenantId` (tenant-scoped grants on platform-wide groups).

---

## 10. Validation Rules

### 10.1 Schema Validation

```php
$validator = Validator::make($data, [
    'version' => 'required|string|in:1.0',
    'groups' => 'nullable|array',
    'groups.*.name' => 'required|string|max:255',
    'groups.*.description' => 'nullable|string|max:255',
    'groups.*.priority' => 'nullable|integer|min:1|max:10000',
    'groups.*.tenant_id' => 'nullable|string',  // export-only, ignored on import
    'groups.*.grants' => 'nullable|array',
    'groups.*.grants.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
    'groups.*.grants.*.path' => 'required|string|max:512',
    'groups.*.grants.*.level' => 'required|string|in:read,write,admin,deny',
    'user_overrides' => 'nullable|array',
    'user_overrides.*.email' => 'required|email',
    'user_overrides.*.overrides' => 'required|array',
    'user_overrides.*.overrides.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
    'user_overrides.*.overrides.*.path' => 'required|string|max:512',
    'user_overrides.*.overrides.*.level' => 'required|string|in:read,write,admin,deny',
]);
```

**Notes:**

- `deny` triggers row deletion on import (§5.4). It is not stored in the database — the DB enums are `read`, `write`, `admin`, `deny`.
- `*` as a method value matches any HTTP method. A grant with `"method": "*"` applies to all methods for the given path.

### 10.2 Business Validation

After schema validation:

1. **Tenant active:** The target tenant must not be suspended. Return `403` if suspended.
2. **Endpoint existence:** Each grant/override `(method, path)` must exist in `tenant_app_endpoint` for the target tenant (or platform-wide).
3. **User existence:** Each `user_overrides[].email` must exist in `users`.
4. **Group name uniqueness:** Within the target tenant, group names must be unique. Duplicates in the same JSON payload → last-wins.
5. **Priority range:** 1–10000. Default 10 if omitted.

---

## 11. Invariants

1. Import is **tenant-scoped** — all groups, grants, and overrides are created under the target tenant.
2. Import is **additive by default** — existing groups are updated, existing grants are upserted. Groups/grants not in the JSON are **not deleted**.
3. `"level": "none"` explicitly deletes a grant or override row (revert to default). No-op if row doesn't exist.
4. Platform-wide groups in the import JSON are **skipped** with a warning. Platform-wide management is via `admin-dashboard.md` routes.
5. Export includes platform-wide groups and their grants (both platform-wide and tenant-scoped) for full round-trip fidelity.
6. `groups[].tenant_id` in the JSON is **export-only** — ignored on import (target tenant from route).
7. Template is always safe to download — no side effects.
8. Import preview never writes — dry-run only. `dry_run` takes precedence over `confirm` if both are set.
9. JSON `version` field enables future schema migration without breaking existing imports.
10. Group name is the natural key — no IDs in the JSON payload.
11. Target tenant must be active — imports into suspended tenants are rejected.

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
| Spec | `access-config-import-export.md` | **Final v1.0** |
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
