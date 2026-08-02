# Tenant App Endpoint Catalog
## Product Assembly Component Specification

**Version:** 3.1
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface
**Audience:** Architects, Engineers, AI Development Agents

> Companion to `group-permission-management.md` and `admin-dashboard.md` §3.8.
> Replaces the implicit "flat path list" grant model with a **per-tenant endpoint catalog** that declares each protected API endpoint and the **permission level required** to call it (`read` / `write` / `admin`).

---

## 1. Purpose

It answers:

> **"What API endpoints does this tenant's app expose, and what level does a caller need to reach each one?"**

A platform admin registers a tenant app's guarded endpoints once (manually, or by importing the tenant app's `permissions.json` export). The catalog is tenant-scoped; the platform-wide scope (`tenant_id NULL`) holds shared endpoints like `loa-auth-admin` paths. Groups are later granted levels against these cataloged endpoints (next spec: `tenant-group-endpoint-grants.md`).

This mirrors `e-consultation`'s static `lib/page-api-map.ts`, but makes it **dynamic per tenant** and **level-aware**, and is the Auth Platform source of truth that the front-end session store consumes.

---

## 2. Ownership

### Owns
- Per-tenant endpoint definitions (`tenant_app_endpoint`).
- The `required_level` vocabulary (`read`/`write`/`admin`) and its enforcement semantics.
- Bulk import from a tenant app's `permissions.json`.

### Does Not Own
- The tenant app's route implementations (tenant apps declare; Auth Platform governs).
- Group grants against endpoints (see `tenant-group-endpoint-grants.md`, next).
- User-level overrides (see `group-permission-management.md` §5.4).

---

## 3. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `kernels/identity/entities/permission.md` | Conceptual origin of "endpoint + permission"; this spec instantiates it per-tenant with a simplified `required_level` instead of a free-form `endpoint_pattern`. |
| `kernels/identity/entities/data-driven-permission-policy.md` | The catalog is the per-tenant analogue of `route_policies`. `permissions.json` import (§3) seeds this catalog. |
| `kernels/identity/tenancy.md` §3.1, §3.4 | `tenant_id` scoping matches; `NULL` = platform-wide (shared endpoints). |
| `assemblies/loa-auth-platform/group-permission-management.md` | Group grant API (`POST /groups/{id}/permissions`) adapts to write grants against cataloged endpoints. |
| `kernels/identity/rules/permission-resolution.md` | Resolution order (union of groups, deny-wins, user overrides last) — unchanged. |
| `assemblies/loa-auth-platform/admin-dashboard.md` §3.8 | Route group `/admin/tenants/{tenant}/*` — this spec adds `/endpoints` under it. |
| `assemblies/loa-auth-platform/web-ui.md` §4.1 | Admin session gate (`web.admin`) applies. |

---

## 4. Model

### Permission levels

| Level | Meaning at enforcement | Covers |
|-------|------------------------|--------|
| `read` | safe `GET` view operations | list / view |
| `write` | create / update / delete (non-destructive admin) | `POST`, `PUT`, `PATCH`, `DELETE` |
| `admin` | reserved label for destructive / administrative endpoints | same operations as `write` |

**Semantics:** `read` < `write` == `admin`. `admin` is a label that enforces as `write` (per product decision: "admin → write (update, create, delete)"). A caller granted `write` or `admin` may call any endpoint whose `required_level` is `read`, `write`, or `admin`; a caller granted only `read` may call `read`-required endpoints only.

This vocabulary is intentionally coarse: it drives the **admin UI dropdowns** and the **session payload**. Finer data filtering (owner-scoped, department-scoped) remains the tenant app's job per-endpoint (cf. `permission-claims.md` authored/scoped — consumed, not managed, here).

---

### Table: `tenant_app_endpoint`

```sql
tenant_app_endpoint (
  tenant_id   UUID NULL FK -> tenants(id) ON DELETE CASCADE,   -- NULL = platform-wide (shared)
  method      VARCHAR(10) NOT NULL,                           -- GET | POST | PUT | PATCH | DELETE | *
  path        VARCHAR(512) NOT NULL,                          -- '/api/v1/appointments/{id}'
  label       VARCHAR(255) NULL,
  description TEXT NULL,
  required_level ENUM('read','write','admin') NOT NULL DEFAULT 'read',
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (tenant_id, method, path)
)
```

Invariants:
1. `path` uses `{param}` syntax (e.g. `/api/v1/appointments/{id}`).
2. `(tenant_id, method, path)` unique.
3. `tenant_id` NULL allowed only when `path` is a shared/platform-wide endpoint (e.g. `loa-auth-admin` tooling); enforced at write-time.
4. `required_level` ∈ {`read`,`write`,`admin`}.
5. Endpoints with no catalog entry are **deny-all** (closed-by-default) — matches `proxy.ts`-style closed-by-default behaviour.

---

## 5. Admin Operations

All routes require `auth` (web guard) + `web.admin` + `users.manage`, scoped to a tenant. Platform-wide endpoints require platform-admin (`loa-auth-admin`).

Route group (added to `routes/web.php` under §3.8):

| Method | URI | Action | Route name |
|--------|-----|--------|------------|
| `GET` | `/admin/tenants/{tenant}/endpoints` | list catalog | `admin.tenants.endpoints` |
| `POST` | `/admin/tenants/{tenant}/endpoints` | create one | `admin.tenants.endpoints.store` |
| `POST` | `/admin/tenants/{tenant}/endpoints/bulk` | import/replace from `permissions.json` | `admin.tenants.endpoints.import` |
| `PATCH` | `/admin/tenants/{tenant}/endpoints` | update one | `admin.tenants.endpoints.update` |
| `DELETE` | `/admin/tenants/{tenant}/endpoints` | delete one | `admin.tenants.endpoints.destroy` |

---

## 6. API Contracts

### 6.1 List catalog
`GET /admin/tenants/{tenant}/endpoints` → `200`
```json
[
  {
    "method": "GET",
    "path": "/api/v1/appointments",
    "label": "List appointments",
    "description": "Paginated appointment list",
    "required_level": "read",
    "tenant_id": "tenant_ccs",
    "created_at": "2026-08-02T00:00:00Z"
  },
  {
    "method": "POST",
    "path": "/api/v1/appointments",
    "label": "Create appointment",
    "description": "Create a consultation appointment",
    "required_level": "write",
    "tenant_id": "tenant_ccs",
    "created_at": "2026-08-02T00:00:00Z"
  },
  {
    "method": "DELETE",
    "path": "/api/v1/appointments/{id}",
    "label": "Delete appointment",
    "description": "Remove an appointment",
    "required_level": "admin",
    "tenant_id": "tenant_ccs",
    "created_at": "2026-08-02T00:00:00Z"
  }
]
```

### 6.2 Create one endpoint
`POST /admin/tenants/{tenant}/endpoints`
```json
{
  "method": "DELETE",
  "path": "/api/v1/appointments/{id}",
  "label": "Delete appointment",
  "description": "Remove an appointment (idempotent)",
  "required_level": "admin",
  "tenant_id": "tenant_ccs"
}
```
- Omit `tenant_id` → tenant inferred from route; `null` allowed only for platform-admin.
- `422` on unknown `method`/`required_level`, missing `path`, or duplicate `(tenant_id, method, path)`.
- `201` on success.

### 6.3 Bulk import (from tenant app's `permissions.json`)
The tenant app exports its guarded endpoints (`data-driven-permission-policy.md` §3 import format → this catalog shape). The import maps `claims` → `required_level`:

| Imported claim | `required_level` |
|----------------|------------------|
| `read` / `read-authored` / `read-scoped` | `read` |
| `write` / `write-authored` / `write-scoped` | `write` |
| (`admin` label, if present) | `admin` |

`POST /admin/tenants/{tenant}/endpoints/bulk`
```json
{
  "endpoints": [
    { "method": "GET",    "path": "/api/v1/appointments",         "label": "List appointments",  "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/appointments",         "label": "Create appointment", "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/appointments/{id}",    "label": "View appointment",   "required_level": "read" },
    { "method": "PUT",    "path": "/api/v1/appointments/{id}",    "label": "Update appointment", "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/appointments/{id}",    "label": "Delete appointment", "required_level": "admin" }
  ],
  "replace": false
}
```
- `replace: true` → delete existing entries for the tenant first, then upsert.
- `422` on any invalid `method`/`required_level`/malformed `path`.
- `200` with `{ inserted, updated, skipped, errors[] }`.

### 6.4 Update one endpoint
`PATCH /admin/tenants/{tenant}/endpoints`
```json
{
  "method": "DELETE",
  "path": "/api/v1/appointments/{id}",
  "label": "Delete appointment",
  "required_level": "write"
}
```

### 6.5 Delete one endpoint
`DELETE /admin/tenants/{tenant}/endpoints`
```json
{ "method": "DELETE", "path": "/api/v1/appointments/{id}" }
```
Cascading concern: if any group currently grants this endpoint, report `409` with the referencing groups and require explicit confirmation (`force: true`) — prevents dangling grants.

---

## 7. Validation (`/admin/tenants/{tenant}/endpoints/validate`)

Mirrors `permissions:validate` (`permission-registry.md` §3). `GET` (or POST re-validate) against the tenant app's live route set:
1. Schema check (required fields, enums).
2. Naming/path check (`{param}` syntax, no duplicates).
3. **Dead grant check:** any catalog endpoint with no grant on any group? (report)
4. **Dead route check:** any tenant-app route not in the catalog? (report)

Returns `{ valid: bool, errors: [], warnings: [] }`.

---

## 8. Enforcement hook (runtime contract)

This spec **declares** the catalog; the runtime gate (Next.js `proxy.ts` or Laravel `ClaimPolicyMiddleware` — see SESSION-PROMPT "Auth Platform implementation complete") consults it:

```
for an inbound request (method, path):
  endpoint = tenant_app_endpoint[tenant, method, path-match]
  if no endpoint:        # closed-by-default
     if /api/      → 403 JSON
     else          → next()  (UI locked client-side)   # e-consultation behaviour
  required_level = endpoint.required_level
  granted_level  = resolve caller's granted level for this endpoint   # groups + overrides (next spec)
  ALLOW iff granted_level >= required_level
```

`path-match` is param-aware: `/api/v1/appointments/{id}` matches `/api/v1/appointments/123`.

The resolved per-caller set is published to the front-end session store via `GET /api/auth/access` as:
```jsonc
{
  "groups": [ /* user's tenant-scoped groups */ ],
  "permissions": [    // level-aware, e.g. "read:/api/v1/appointments", "write:/api/v1/appointments/{id}"
    "<level>:<path>"
  ]
}
```
The front-end gates API calls by membership of `<required_level>:<path>` (or higher) in `permissions`.

---

## 9. Invariants (consolidated)

1. Endpoints are declared at the tenant level (catalog), not globally — except platform-wide (`tenant_id NULL`) for shared tooling.
2. Each endpoint declares one `required_level` ∈ {`read`,`write`,`admin`}; `admin` enforces as `write`.
3. Platform-wide endpoints (`tenant_id NULL`) may be created/modified by platform admins only.
4. Tenant-scoped endpoints require the target tenant to be `active` (`tenancy.md` §3.1 §2).
5. Deleting an endpoint with existing group grants returns `409` (no silent breakage).
6. Validation cross-checks against the tenant app's live routes.

---

## 10. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Hardcoding `required_level` per route in the tenant app | Two sources of truth, drift | Catalog is the single source; app imports/exports |
| Per-user level grants for an endpoint the group already owns | Over-permissioning, audit gap | Prefer group grants; use per-user overrides sparingly (`user_permissions`) |
| Skipping param-aware matching | `/api/v1/appointments/{id}` never matches real calls | `{param}`-aware matcher (like `e-consultation/lib/page-api-map.ts`) |
| Allowing non-admins to create platform-wide (`tenant_id NULL`) endpoints | Privilege escalation across tenants | Gate on `web.admin` + platform-admin group |
| Catalog endpoint with no grants and no route handler | Stale entry | Run validation; prune |

---

## 11. Security Checklist

- [ ] All routes behind `auth` + `web.admin` + `users.manage`
- [ ] Platform-wide (`tenant_id NULL`) create limited to `loa-auth-admin`
- [ ] CSRF on every form/POST
- [ ] Param-aware path matching (no literal-only matches)
- [ ] Closed-by-default: un-categoru endpoint → 403 (API) / pass-through + client lock (UI)
- [ ] Delete endpoint with grants → 409 (opt-in `force`)
- [ ] Validation compares catalog vs tenant app's live route set

---

## 12. Implementation Inventory

| Layer | Item |
|-------|------|
| Kernel | `kernels/identity/tenancy.md` §3.1 (`tenants`) |
| Kernel | `kernels/identity/entities/permission.md` (endpoint_pattern origin) |
| Kernel | `kernels/identity/entities/data-driven-permission-policy.md` §3 (`permissions.json`) |
| Kernel | `kernels/identity/rules/permission-resolution.md` |
| Assembly (spec) | `assemblies/loa-auth-platform/admin-dashboard.md` §3.8 (route group) |
| Assembly (spec) | `assemblies/loa-auth-platform/group-permission-management.md` §5 (grants) |
| Assembly (spec) | `assemblies/loa-auth-platform/tenant-endpoint-catalog.md` (this) |
| Assembly (next) | `assemblies/loa-auth-platform/tenant-group-endpoint-grants.md` |
