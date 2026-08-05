# LOA Cert Platform — API Endpoints
## Product Assembly Component Specification

**Version:** 1.2
**Status:** Draft
**Layer:** Product Assembly (`loa-cert-platform`)
**Audience:** Architects, Engineers, AI Development Agents

> **Priority spec.** This is the source of truth for the LOA Cert Platform's REST API. It refines and supersedes the API surface sketch in `assemblies/loa-cert-platform/README.md` §10 (REST conventions corrected: PATCH for partial updates, POST for state actions). It also refines README §11 (SSO): §9 of this spec makes the SSO/JWT/permission contract concrete and supersedes the deferred note from v1.0.

---

# 1. Purpose

It answers:

> **"What endpoints does the LOA Cert Platform expose, what do they accept, what do they return, and who may call them?"**

The requirements were extracted from the legacy `e-cert` application:

- `route-documentation.md` — route + server-action inventory
- `schema-documentation.md` — Postgres schema (adapted to MySQL 8)
- `API_Endpoints_Documentation.md` — endpoint semantics

The API is **API-first and level-gated**. Authentication is delegated to the LOA Auth Platform (JWT bearer); the Cert Platform enforces access using the Auth Platform's **level-based endpoint grants** (`<level>:<path>` entries in the JWT `permissions` claim), per `tenant-group-endpoint-grants.md` (Final v1.1).

---

# 2. Scope

## 2.1 In Scope (this spec)

| Group | Endpoints | required_level |
|-------|-----------|----------------|
| Auth (SSO) | callback, refresh, logout | public (SSO payload / cookie) |
| Events | CRUD + stats + template clone + event issuance actions | `read` (list/detail/stats), `write` (create/update/delete/issue), `admin` (reissue, revoke-expired) |
| Attendees | CRUD + CSV import + delete preview + file data | `read` (list/preview/file-data), `write` (CRUD/import), `admin` (with-cert) |
| Templates | CRUD (certificate + email types) | `read` (list/detail), `write` (CRUD) |
| Certificates | issue, bulk, upload, list, detail, PDF, revoke, delete, email, reissue, expire, QR | `read` (list/detail/pdf/download/email-logs/qr), `write` (issue/bulk/upload/email), `admin` (revoke/delete/reissue/expire) |
| Participant | own certificates (`/me`) | `read` + owner rule (§9.6) |
| Author | own events/templates (`/me/events`, `/me/templates`, item ops) | `read`/`write` + author rule (§9.6) |
| Public | verify by number, view by id | none (no auth) |
| Dashboard | stats + recent activity | `read` |
| Audit | query + export | `admin` |

## 2.2 Out of Scope (deferred or excluded)

| Feature | Reason |
|---------|--------|
| Self-hosted auth (login, register, forgot/update password, confirm, `/auth/role`) | Owned by Auth Platform. Cert Platform has no login form. |
| User / role / membership management (`users`, `organizations/members`) | Owned by Auth Platform (`users.manage`) and Admin Dashboard. |
| Permission resolution / endpoint catalog management | Owned by Auth Platform (`/api/v1/admin/tenants/{tenant}/endpoints/*`). Cert consumes the JWT `permissions` claim. |
| Demo actions (impersonate, demo mode) | Environment-gated; not shipped. |
| `.well-known/workflow/v1/*` | Framework runtime internals, not domain logic. |
| `POST /api/health` (admin master-reset) | Dev/reset tool, not an API. Excluded. |
| `DELETE /api/storage/cleanup` | Excluded by decision. If needed, re-add as `POST /api/v1/admin/storage/cleanup`. |
| Auth-type templates (`auth_process`) | Auth email content belongs to Auth Platform. Template `type` limited to `certificate` / `email`. |

---

# 3. Cross-Cutting Conventions

## 3.1 Base URL & Versioning

```
https://cert-api.lyceumalabang.edu.ph/api/v1
```

- Version in the URL path (`/api/v1/...`).
- Media type `application/json` unless stated (multipart, PDF binary).
- No trailing slashes.

## 3.2 Authentication

- All endpoints except the Public group and the SSO/auth group require `Authorization: Bearer <access_token>`.
- The access token is a JWT issued by the LOA Auth Platform; the Cert Platform validates it **locally** using the shared `JWT_SECRET` (HMAC-SHA256) — no HTTP call per request.
- Two middleware aliases gate every non-public route:
  - `jwt.auth` — signature, `type=access`, `exp`, and tenant-claim validation (§9.4).
  - `jwt.endpoint` — level-based permission check against the local catalog mirror and the JWT `permissions` claim (§9.5).
- `401` when the token is missing/expired/invalid. `403` when the token is valid but the caller lacks the required level (or tenant mismatch).

## 3.3 Tenant & Organization Scoping

- LOA runs a **single organization**. The `organizations` table is kept and seeded with one row (`Lyceum of Alabang`), matching `CERT_TENANT_SLUG=loa`.
- The current organization is **resolved server-side** from the authenticated JWT `tenant` claim via `config/cert-platform.php` (`tenant_slug` → organization). Clients never send `organization_id`.
- Every query is implicitly filtered to the resolved organization. Cross-organization access is impossible by construction.
- Tenant mismatch (token `tenant.slug` ≠ configured `loa`) → `403`.

## 3.4 Response Envelope

Success — single resource:

```json
{ "data": { ... } }
```

Success — collection:

```json
{
  "data": [ ... ],
  "meta": { "limit": 25, "offset": 0, "total": 137, "has_more": true }
}
```

Error:

```json
{
  "status": "error",
  "message": "Human-readable summary",
  "errors": { "field": ["rule broken"] }
}
```

- `errors` omitted when not applicable.
- `204 No Content` on successful deletes (no body).

## 3.5 Pagination

- Query params: `limit` (default 25, max 100), `offset` (default 0).
- Collection responses include the `meta` block above.
- Filtering via query params; documented per endpoint.

## 3.6 IDs & Timestamps

- Entity IDs: UUID strings (`char(36)`), e.g. `9d2e3c1a-...`.
- Timestamps: RFC 3339 / ISO 8601 UTC, e.g. `2026-08-05T08:00:00Z`.
- Dates: `YYYY-MM-DD`.

## 3.7 Binary & Multipart

- PDF responses are **raw binary streams** (`Content-Type: application/pdf`), never base64-embedded in JSON (except the `rendered_html` regeneration cache). Headers: `Content-Disposition: attachment; filename="<certificate_number>.pdf"`.
- File uploads (CSV import, certificate file upload) use **multipart/form-data**, not base64 JSON.

## 3.8 Idempotency & Bulk Semantics

- LOA has **no workflow runtime**. Bulk operations execute **synchronously** and return a per-item result object: `{ "success": n, "failed": n, "errors": [...] }`.
- State actions (`revoke`, `reissue`, `email`) are idempotent at the record level: revoking an already-revoked certificate returns the existing state (no error).

---

# 4. Authorization Model

## 4.1 Level-Based Enforcement

The Cert Platform has **no local role model and no users table**. Runtime access control uses the **level-based endpoint-grant model** from `tenant-group-endpoint-grants.md` (Final v1.1) and `tenant-endpoint-catalog.md`:

1. Every non-public Cert endpoint has a `required_level` (`read` | `write` | `admin`) declared in the **Auth Platform's tenant endpoint catalog** (Appendix A is the Cert catalog import payload for `POST /api/v1/admin/tenants/{tenant}/endpoints/bulk`).
2. The Auth Platform resolves each user's **effective level per endpoint** (group-union grants, group priority, deny-wins, user overrides) and publishes the result in the JWT `permissions` claim at login/refresh as `<level>:<path>` entries.
3. The Cert Platform validates the JWT locally (shared `JWT_SECRET`) and enforces against a **local mirror** of the catalog — no DB query and no HTTP call per request. The Auth Platform remains the source of truth for *granting*; the local copy is used only for *matching* (required level + claim entries).

## 4.2 Levels

| Level | Ordinal | Meaning | Example grants |
|-------|---------|---------|----------------|
| `read` | 1 | View / read-only | list events, view certificates, dashboard stats |
| `write` | 2 | Create / update / state actions | create event, import attendees, issue certificate, send email |
| `admin` | 2 | `write` + sensitive operations | revoke, delete, reissue, expire, delete attendee-with-cert, audit logs |
| `deny` | special | Explicit block; wins on group-priority ties | — |
| `none` | 0 | No grant (never published in the JWT) | — |

> `write` and `admin` share ordinal 2 in the Auth Platform model. The distinction is **operational**: `required_level=admin` endpoints are granted only to the admin group; `write`-granted staff do not receive grants on `admin` paths. `admin` is never auto-derived from `write`.

## 4.3 JWT `permissions` Claim

- The Auth Platform publishes one entry per cataloged endpoint the user may access, in the form `<level>:<path>`:

```
read:/api/v1/events
write:/api/v1/certificates
admin:/api/v1/certificates/{id}/revoke
```

- Paths use catalog (param-aware) form: `{id}` matches one path segment.
- A leading `*` after the colon is an optional method-wildcard: `read:*:/api/v1/events` — supported by the middleware, not produced by the Auth generator.
- Only levels `> none` and not `deny` are published (the Auth Platform filters).
- The Cert middleware matches claim entries against the request path (param-aware, case-insensitive level) and compares ordinals (§9.5).

## 4.4 Role → Grant Guidance

The e-cert admin/staff/participant vocabulary maps to **groups granted levels in the Auth Platform**, not to a Cert-local role model:

| e-cert role | Grant pattern |
|-------------|---------------|
| `admin` | `admin` on every cataloged path (Appendix A). Bypasses the owner rule (§9.6). |
| `staff` | `write` on management paths (events, attendees, templates, certificates issue/email), `read` on read paths. No grants on `admin` paths (revoke, delete, reissue, expire, audit, with-cert). |
| `staff` (author-scoped) | `read`/`write` on **item paths only** (`/api/v1/events/{id}`, `/api/v1/templates/{id}`) + `read` on `/api/v1/me/events` and `/api/v1/me/templates`. **Not** granted the unscoped collection reads (`/api/v1/events`, `/api/v1/templates`). Author scope enforced in controllers (§9.6). |
| `participant` | `read` on participant paths only: `/api/v1/me/certificates`, `/api/v1/me/certificates/{id}`, `/api/v1/certificates/{id}`, `/api/v1/certificates/{id}/pdf`, `/api/v1/certificates/{id}/download`, `/api/v1/events/{id}`, `/api/v1/certificates/qr`. Subject to the owner rule (§9.6). |

## 4.5 `cert.*` Keys Are Not Enforced

The v1.0 draft gated endpoints with `cert.*` claim keys (`cert.events.manage`, `cert.certificates.issue`, ...). Per the level-based decision, those keys are **not consulted** by the Cert runtime. They remain defined in `group-permission-management.md` (Final v2.0) but play no part in endpoint enforcement; access is granted with **endpoint grants (levels)** in the Auth Platform.

## 4.6 Scoping: Which Records May I Touch?

Levels decide *whether* a caller may invoke an endpoint; **scope** decides *which records*. Three scope types, resolved in the controller (§9.6):

- **recipient** — participant certificates: `jwt.email === recipient_email`.
- **author** — events/templates created by the caller: `jwt.sub === created_by` (requires `created_by` tracking, §7.2).
- **unscoped** — `admin`-level grants: no filter.

Author/recipient scope is expressed with **dedicated `/me/*` listing paths** (grants are cleanly separated per path) plus a controller rule for item operations. See §9.6 for the full rule and grant patterns.

---

# 5. Endpoint Reference

> **Auth legend:** `read` / `write` / `admin` = required_level in the endpoint catalog (§4). "owner/author rule" = §9.6. `public` = no JWT required.

## 5.1 Events

### `GET /api/v1/events`
List events for the organization.

**Auth:** `read`

**Query:**

| Param | Type | Notes |
|-------|------|-------|
| `search` | string | Matches `name`, `location`, `organizer` (LIKE) |
| `status` | string | `draft` \| `active` \| `archive` |
| `limit`, `offset` | int | Pagination |

**Response 200:**

```json
{
  "data": [
    {
      "id": "9d2e3c1a-...",
      "name": "SPARK Bootcamp 2026",
      "description": "Student leaders bootcamp",
      "event_date": "2026-08-15",
      "location": "Multipurpose Hall",
      "organizer": "SAO",
      "certificate_title": "Certificate of Participation",
      "certificate_number_pattern": "LOA-2026-####",
      "valid_until": "2026-09-15",
      "status": "active",
      "template_id": null,
      "email_template_id": null,
      "attendees_count": 42,
      "certificates_issued": 40,
      "created_at": "2026-08-01T09:00:00Z",
      "updated_at": "2026-08-01T09:00:00Z"
    }
  ],
  "meta": { "limit": 25, "offset": 0, "total": 3, "has_more": false }
}
```

**Errors:** 401, 403.

### `POST /api/v1/events`
Create an event.

**Auth:** `write`

**Body:**

```json
{
  "name": "SPARK Bootcamp 2026",
  "description": "Student leaders bootcamp",
  "event_date": "2026-08-15",
  "location": "Multipurpose Hall",
  "organizer": "SAO",
  "certificate_title": "Certificate of Participation",
  "certificate_number_pattern": "LOA-2026-####",
  "valid_until": "2026-09-15",
  "template_id": "uuid",
  "email_template_id": "uuid",
  "status": "draft"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | yes | |
| `description` | string | no | |
| `event_date` | date | no | |
| `location` | string | no | |
| `organizer` | string | no | |
| `certificate_title` | string | no | default `Certificate of Participation` |
| `certificate_number_pattern` | string | no | default `LOA-YYYY-####`; supports `YYYY`, `####` placeholders (see §7.4) |
| `valid_until` | date | no | default certificate expiry source |
| `template_id` | uuid | no | must belong to org; must be `type=certificate` |
| `email_template_id` | uuid | no | must belong to org; must be `type=email` |
| `status` | string | no | `draft` \| `active` \| `archive`; default `draft` |

**Response 201:** event resource (as list item shape).

**Errors:** 401, 403, 422 (validation, template not found/wrong type), 404 (referenced template).

### `GET /api/v1/events/{id}`
Get a single event.

**Auth:** `read` (participants are granted `read` on this path).

**Response 200:** event resource. **Errors:** 401, 403, 404.

### `PATCH /api/v1/events/{id}`
Partial update.

**Auth:** `write`

**Body:** any subset of the create fields plus `status`. `template_id` / `email_template_id` may be set to `null` to detach.

**Response 200:** updated event resource. **Errors:** 401, 403, 404, 422.

### `DELETE /api/v1/events/{id}`
Delete an event and its attendees (cascades to linked certificates per data model).

**Auth:** `write`

**Response 204.** **Errors:** 401, 403, 404.

> Delete-preview is available per-event via `GET /api/v1/events/{id}/stats` (counts) before deleting. Attendee-level delete preview: §5.2.

### `GET /api/v1/events/{id}/stats`
Event statistics.

**Auth:** `read`

**Response 200:**

```json
{
  "data": {
    "event_id": "9d2e3c1a-...",
    "attendees": { "total": 42, "attended": 38, "completed": 36 },
    "certificates": { "issued": 40, "active": 38, "revoked": 1, "expired": 1 },
    "expiring": 5
  }
}
```

**Errors:** 401, 403, 404.

### `POST /api/v1/events/{id}/clone-template`
Clone a certificate template under a name derived from the event and attach it to the event.

**Auth:** `write`

**Body:**

```json
{
  "source_template_id": "uuid",
  "name": "SPARK Bootcamp 2026 Certificate"
}
```

**Response 200:** `{ "data": { "template_id": "uuid", "name": "SPARK Bootcamp 2026 Certificate" } }` — template is attached as the event's `template_id` if it wasn't already.

**Errors:** 401, 403, 404 (source template), 422.

### `POST /api/v1/events/{id}/clone-email-template`
Same as clone-template but for `type=email`; attaches as `email_template_id`.

**Auth:** `write`

**Body:** `{ "source_template_id": "uuid", "name": "SPARK Bootcamp 2026 Email" }`

**Errors:** 401, 403, 404, 422.

### `POST /api/v1/events/{id}/bulk-issue`
Bulk-issue certificates for selected attendees of the event.

**Auth:** `write`

**Body:**

```json
{
  "attendee_ids": ["uuid", "uuid"],
  "send_email": true
}
```

**Behavior:** for each attendee, generate a certificate number atomically (§7.4), render the PDF, link the attendee, optionally send email. Synchronous.

**Response 200:**

```json
{
  "data": {
    "success": 2,
    "failed": 0,
    "errors": [],
    "certificates": ["uuid", "uuid"]
  }
}
```

**Errors:** 401, 403, 404 (event), 422 (empty `attendee_ids`, event without template/pattern).

### `POST /api/v1/events/{id}/reissue`
Re-issue certificates for selected attendees (revoke existing + issue new number).

**Auth:** `admin`

**Body:** `{ "attendee_ids": ["uuid"] }`

**Response 200:** same result shape as bulk-issue. **Errors:** 401, 403, 404, 422 (empty list).

### `GET /api/v1/events/{id}/revoke-expired`
Count expired certificates for the event.

**Auth:** `read`

**Response 200:** `{ "data": { "event_id": "uuid", "expired": 1 } }`

**Errors:** 401, 403, 404.

### `POST /api/v1/events/{id}/revoke-expired`
Revoke all expired certificates for the event.

**Auth:** `admin`

**Response 200:** `{ "data": { "event_id": "uuid", "revoked": 1 } }`

**Errors:** 401, 403, 404, 500.

### `POST /api/v1/events/{id}/issue-completed`
Issue certificates for attendees flagged `completed`, optionally filtered by `attendee_ids`.

**Auth:** `write`

**Body:** `{ "send_email": false, "attendee_ids": ["uuid"] | null }`

**Response 200:** bulk-issue result shape. **Errors:** 401, 403, 404, 422.

---

## 5.2 Attendees

Attendee ids are event-scoped; update/delete endpoints address attendees by their own id.

### `GET /api/v1/events/{id}/attendees`
List attendees for an event.

**Auth:** `read`

**Query:** `search` (name/email), `attended` (bool), `completed` (bool), `has_certificate` (bool), `limit`, `offset`.

**Response 200:**

```json
{
  "data": [
    {
      "id": "uuid",
      "event_id": "uuid",
      "name": "Maria Santos",
      "email": "maria.santos@lyceumalabang.edu.ph",
      "attended": true,
      "completed": true,
      "attended_at": "2026-08-15T10:00:00Z",
      "completed_at": "2026-08-15T10:00:00Z",
      "certificate_id": "uuid",
      "certificate_number": "LOA-2026-0001",
      "generation_mode": "template",
      "created_at": "2026-08-01T09:00:00Z"
    }
  ],
  "meta": { "limit": 25, "offset": 0, "total": 42, "has_more": false }
}
```

**Errors:** 401, 403, 404 (event).

### `POST /api/v1/events/{id}/attendees`
Add a single attendee.

**Auth:** `write`

**Body:**

```json
{
  "name": "Maria Santos",
  "email": "maria.santos@lyceumalabang.edu.ph",
  "attended": false,
  "completed": false,
  "metadata": { "section": "BSIT-3A" }
}
```

**Behavior:** upsert by `(event_id, email)` — duplicate email updates the existing attendee.

**Response 201:** attendee resource. **Errors:** 401, 403, 404, 422 (missing name/email, invalid email).

### `POST /api/v1/events/{id}/attendees/import`
Bulk import attendees from CSV.

**Auth:** `write`

**Request:** `multipart/form-data`

| Field | Type | Notes |
|-------|------|-------|
| `file` | file | CSV with header `name,email` (optional extra columns become `metadata`) |
| `mode` | string | `merge` (default, upsert by email) \| `replace` (clear then insert — destructive) |

**Behavior:** rows are validated; malformed rows are reported, valid rows upserted. `mode=replace` requires `write` and is gated behind an explicit `confirm: true` field.

**Response 200:**

```json
{
  "data": {
    "imported": 40,
    "skipped": 2,
    "errors": [
      { "row": 3, "email": "bad@@x", "reason": "Invalid email" },
      { "row": 7, "email": "", "reason": "Missing email" }
    ]
  }
}
```

**Errors:** 401, 403, 404, 422 (no file, empty file, missing header).

### `PATCH /api/v1/attendees/{id}`
Update an attendee.

**Auth:** `write`

**Body:** any subset of `name`, `email`, `attended`, `completed`, `attended_at`, `completed_at`, `metadata`.

**Response 200:** attendee resource. **Errors:** 401, 403, 404, 422 (email conflict with another attendee in same event).

### `DELETE /api/v1/attendees/{id}`
Remove an attendee. Unlinks any linked certificate (does not delete the certificate).

**Auth:** `write`

**Response 204.** **Errors:** 401, 403, 404.

### `DELETE /api/v1/attendees/{id}/with-cert`
Remove an attendee **and** delete the linked certificate.

**Auth:** `admin`

**Response 204.** **Errors:** 401, 403, 404.

### `GET /api/v1/attendees/{id}/delete-preview`
Preview the impact of deleting an attendee.

**Auth:** `read`

**Response 200:**

```json
{
  "data": {
    "attendee_id": "uuid",
    "name": "Maria Santos",
    "email": "maria.santos@lyceumalabang.edu.ph",
    "linked_certificate": { "id": "uuid", "number": "LOA-2026-0001", "status": "active" },
    "deletes_certificate": true
  }
}
```

**Errors:** 401, 403, 404.

### `GET /api/v1/attendees/{id}/file-data`
Return the uploaded certificate source file for an attendee (`generation_mode=file`).

**Auth:** `read`

**Response 200:** PDF/multipart bytes or `{ "data": { "generation_mode": "template" } }` when none.

**Errors:** 401, 403, 404, 410 (file removed).

---

## 5.3 Templates

Template `type` is limited to `certificate` / `email` (§2.2).

### `GET /api/v1/templates`
List templates.

**Auth:** `read`

**Query:** `type` (`certificate` \| `email`), `search`, `limit`, `offset`.

**Response 200:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "SPARK Bootcamp Certificate",
      "description": "Standard landscape certificate",
      "type": "certificate",
      "html_content": "<div>{{recipient_name}}</div>",
      "css_content": "body { width: 1123px; height: 794px; }",
      "is_locked": true,
      "locked_reason": "Referenced by event SPARK Bootcamp 2026",
      "created_at": "2026-08-01T09:00:00Z",
      "updated_at": "2026-08-01T09:00:00Z"
    }
  ],
  "meta": { "limit": 25, "offset": 0, "total": 4, "has_more": false }
}
```

- `is_locked` is true when a template is referenced by an event or issued certificate. Locked templates reject update/delete with `409` (unless `force` is sent for delete; force still fails if certificates reference it).

**Errors:** 401, 403.

### `POST /api/v1/templates`
Create a template.

**Auth:** `write`

**Body:**

```json
{
  "name": "SPARK Bootcamp Certificate",
  "description": "Standard landscape certificate",
  "type": "certificate",
  "html_content": "<div>{{recipient_name}}</div>",
  "css_content": "body { width: 1123px; height: 794px; }"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | yes | unique within organization |
| `description` | string | no | |
| `type` | string | yes | `certificate` \| `email` |
| `html_content` | string | yes | supports placeholders (§7.5) |
| `css_content` | string | no | default empty; certificate templates default canvas `1123x794` |

**Response 201:** template resource. **Errors:** 401, 403, 409 (duplicate name), 422.

### `GET /api/v1/templates/{id}`
Get a template (full content).

**Auth:** `read`

**Response 200.** **Errors:** 401, 403, 404.

### `PATCH /api/v1/templates/{id}`
Partial update.

**Auth:** `write`

**Body:** any subset of create fields.

**Response 200.** **Errors:** 401, 403, 404, 409 (locked), 422.

### `DELETE /api/v1/templates/{id}`
Delete a template.

**Auth:** `write`

**Body (optional):** `{ "force": true }`

**Behavior:** existing certificates that reference the template retain a snapshot of the rendered HTML (deletion never affects issued certificates). If the template is referenced by issued certificates, deletion returns `409`; `force` is not honored in that case.

**Response 204.** **Errors:** 401, 403, 404, 409 (locked/in-use).

---

## 5.4 Certificates

### Certificate status derivation

`status` is derived, never stored:

| Status | Rule |
|--------|------|
| `revoked` | `revoked_at IS NOT NULL` |
| `expired` | `revoked_at IS NULL AND expires_at IS NOT NULL AND expires_at < now()` |
| `active` | otherwise |

### `POST /api/v1/certificates`
Issue a single certificate.

**Auth:** `write`

**Body:**

```json
{
  "event_id": "uuid",
  "template_id": "uuid",
  "recipient_name": "Maria Santos",
  "recipient_email": "maria.santos@lyceumalabang.edu.ph",
  "expires_at": "2026-09-15T23:59:59Z",
  "send_email": false
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `event_id` | uuid | no | if present, links/creates the attendee by `(event_id, email)` |
| `template_id` | uuid | no | defaults to the event's template; required when no event |
| `recipient_name` | string | yes | |
| `recipient_email` | string | yes | |
| `expires_at` | timestamp | no | defaults to event `valid_until` (or null) |
| `send_email` | bool | no | default false |
| `metadata` | object | no | `{ generation_mode, file_name, file_type }` when issuing from an uploaded file |

**Behavior:** atomic certificate number generation (§7.4); enforces one active certificate per `(event_id, recipient_email)`.

**Response 201:**

```json
{
  "data": {
    "id": "uuid",
    "certificate_number": "LOA-2026-0001",
    "recipient_name": "Maria Santos",
    "recipient_email": "maria.santos@lyceumalabang.edu.ph",
    "issued_at": "2026-08-05T08:00:00Z",
    "expires_at": "2026-09-15T23:59:59Z",
    "revoked_at": null,
    "revoke_reason": null,
    "status": "active",
    "event_id": "uuid",
    "template_id": "uuid",
    "file_path": "certificates/LOA-2026-0001.pdf",
    "created_at": "2026-08-05T08:00:00Z"
  }
}
```

**Errors:** 401, 403, 404 (event/template), 409 (duplicate active cert for event+email), 422.

### `POST /api/v1/certificates/bulk`
Bulk-issue certificates for a list of recipients.

**Auth:** `write`

**Body:**

```json
{
  "event_id": "uuid",
  "recipients": [
    { "name": "Maria Santos", "email": "maria.santos@lyceumalabang.edu.ph" }
  ],
  "send_email": true
}
```

**Response 200:** bulk result shape (as §5.1 bulk-issue). **Errors:** 401, 403, 404, 422.

### `POST /api/v1/certificates/upload`
Upload a pre-rendered certificate PDF file for a certificate number.

**Auth:** `write`

**Request:** `multipart/form-data`

| Field | Type | Notes |
|-------|------|-------|
| `certificate_number` | string | target certificate number |
| `file` | file | PDF source file |

**Response 200:** `{ "data": { "certificate_id": "uuid", "file_path": "certificates/LOA-2026-0001.pdf" } }`

**Errors:** 401, 403, 404 (number not found), 422.

### `GET /api/v1/certificates`
List certificates.

**Auth:** `read`

**Query:** `event_id`, `recipient_email`, `status` (`active` \| `revoked` \| `expired`), `search` (name/number), `from`, `to` (issued_at range), `limit`, `offset`.

**Response 200:** paginated list of certificate resources (list shape omits `html` metadata).

**Errors:** 401, 403.

### `GET /api/v1/certificates/{id}`
Get a single certificate (full, including email logs summary).

**Auth:** `read` + owner rule (§9.6).

**Response 200:** certificate resource. **Errors:** 401, 403, 404.

### `GET /api/v1/certificates/{id}/pdf`
Stream the PDF (inline view).

**Auth:** `read` + owner rule.

**Response 200:** PDF binary. **Errors:** 401, 403, 404, 410 (revoked/expired when not `view_all`).

### `GET /api/v1/certificates/{id}/download`
Download the PDF (attachment).

**Auth:** `read` + owner rule.

**Response 200:** PDF binary, `Content-Disposition: attachment; filename="<certificate_number>.pdf"`. **Errors:** 401, 403, 404, 410 (revoked/expired).

### `POST /api/v1/certificates/{id}/revoke`
Revoke a certificate.

**Auth:** `admin`

**Body:** `{ "reason": "Administrative decision" }` — `reason` required for revoked certificates.

**Response 200:** updated certificate resource (status `revoked`). **Errors:** 401, 403, 404, 422 (missing reason), 409 (already revoked).

### `DELETE /api/v1/certificates/{id}`
Delete a certificate permanently.

**Auth:** `admin`

**Response 204.** **Errors:** 401, 403, 404.

### `POST /api/v1/certificates/{id}/email`
Send the certificate email (with PDF attachment) to the recipient.

**Auth:** `write`

**Response 200:** `{ "data": { "email_log_id": "uuid", "sent_to": "maria.santos@lyceumalabang.edu.ph", "status": "sent" } }`

**Errors:** 401, 403, 404, 409 (revoked), 422 (no email template configured).

### `GET /api/v1/certificates/{id}/email-logs`
List email delivery logs for a certificate.

**Auth:** `read`

**Response 200:**

```json
{
  "data": [
    {
      "id": "uuid",
      "sent_to": "maria.santos@lyceumalabang.edu.ph",
      "subject": "Your certificate",
      "sent_at": "2026-08-05T08:01:00Z",
      "status": "sent",
      "error_message": null
    }
  ],
  "meta": { "limit": 25, "offset": 0, "total": 1, "has_more": false }
}
```

**Errors:** 401, 403, 404.

### `POST /api/v1/certificates/{id}/reissue`
Revoke the current certificate and issue a new one for the same recipient (new number).

**Auth:** `admin`

**Body:** `{ "reason": "Correction" }`

**Response 200:** new certificate resource (status `active`). **Errors:** 401, 403, 404, 422.

### `POST /api/v1/certificates/expire`
Auto-revoke all expired certificates and notify recipients nearing expiry.

**Auth:** `admin`

**Body:** none (may accept `{ "dry_run": true }`).

**Response 200:**

```json
{ "data": { "revoked": 12, "expiring_count": 5, "error": null } }
```

**Errors:** 401, 403, 500.

### `GET /api/v1/certificates/qr`
Generate a QR data URL for a certificate's public verification URL.

**Auth:** `read`

**Query:** `certificate_number` (required).

**Response 200:** `{ "data": { "certificate_number": "LOA-2026-0001", "qr_data_url": "data:image/png;base64,..." } }`

**Errors:** 401, 403, 404, 422 (missing number).

---

## 5.5 Participant — My Certificates

Participant access is scoped by **recipient email**, matched against the authenticated user's email claim (owner rule, §9.6).

### `GET /api/v1/me/certificates`
List the caller's own certificates.

**Auth:** `read` + owner scoping (always filtered by the JWT `email` claim).

**Query:** `status`, `limit`, `offset`.

**Response 200:** paginated certificate list (own only). **Errors:** 401, 403.

### `GET /api/v1/me/certificates/{id}`
Get one of the caller's own certificates.

**Auth:** `read` + owner scoping.

**Errors:** 401, 403 (not the owner), 404.

---

## 5.6 Public Verification & View

No authentication. Public responses must **never** expose email addresses, internal ids beyond the certificate id, or `html_content`.

### `GET /api/v1/verify/{certificate_number}`
Verify a certificate by its public number.

**Auth:** public.

**Response 200:**

```json
{
  "data": {
    "valid": true,
    "certificate_number": "LOA-2026-0001",
    "issued_date": "2026-08-05",
    "valid_until": "2026-09-15",
    "status": "active",
    "recipient_name": "Maria Santos",
    "event_name": "SPARK Bootcamp 2026",
    "organization": { "name": "Lyceum of Alabang" }
  }
}
```

- `valid` is `false` when status is `revoked` or `expired` (still returns 200 with status).
- **404** when the number does not exist (cached 60s).
- **Cache:** `Cache-Control: public, s-maxage=300, stale-while-revalidate=600`.
- **Audit:** logs `certificate.viewed`.

**Errors:** 404.

### `GET /api/v1/view/{id}`
Public read-only certificate viewer data.

**Auth:** public.

**Response 200:**

```json
{
  "data": {
    "certificate": {
      "id": "uuid",
      "certificate_number": "LOA-2026-0001",
      "status": "active",
      "recipient_name": "Maria Santos",
      "issued_at": "2026-08-05T08:00:00Z",
      "expires_at": "2026-09-15T23:59:59Z",
      "revoked_at": null
    },
    "template": { "name": "SPARK Bootcamp Certificate", "html_content": "...", "css_content": "..." },
    "event": { "id": "uuid", "name": "SPARK Bootcamp 2026", "event_date": "2026-08-15", "location": "Multipurpose Hall" },
    "qr_data_url": "data:image/png;base64,...",
    "organization": { "name": "Lyceum of Alabang" }
  }
}
```

**Errors:** 404 (not found), 410 (revoked).

---

## 5.7 Dashboard

### `GET /api/v1/dashboard/stats`
Organization-wide summary.

**Auth:** `read`

**Response 200:**

```json
{
  "data": {
    "certificates": { "total": 320, "active": 300, "revoked": 8, "expired": 12, "issued_30d": 45 },
    "events": { "total": 6, "active": 3 },
    "attendees": { "total": 480 },
    "templates": { "total": 4 },
    "expiring_soon": 5
  }
}
```

**Errors:** 401, 403.

### `GET /api/v1/dashboard/activity`
Recent activity feed (most recent audit entries).

**Auth:** `read`

**Query:** `limit` (default 20, max 50).

**Response 200:** `{ "data": [ { "id", "action", "entity_type", "entity_id", "user_email", "created_at", "details" } ], "meta": {...} }`

**Errors:** 401, 403.

---

## 5.8 Audit

### `GET /api/v1/admin/audit-logs`
Query audit logs.

**Auth:** `admin`

**Query:**

| Param | Type | Notes |
|-------|------|-------|
| `action` | string | e.g. `certificate.issued`, `certificate.revoked`, `certificate.viewed` |
| `entity_type` | string | `event`, `attendee`, `certificate`, `template` |
| `entity_id` | uuid | |
| `user_email` | string | |
| `from`, `to` | date | created_at range |
| `limit`, `offset` | int | pagination |

**Response 200:**

```json
{
  "data": [
    {
      "id": "uuid",
      "user_id": "auth-user-uuid",
      "user_email": "admin@lyceumalabang.edu.ph",
      "action": "certificate.revoked",
      "source": "api",
      "entity_type": "certificate",
      "entity_id": "uuid",
      "details": { "certificate_number": "LOA-2026-0001", "reason": "Administrative decision" },
      "ip_address": "203.0.113.7",
      "created_at": "2026-08-05T08:05:00Z"
    }
  ],
  "meta": { "limit": 25, "offset": 0, "total": 3, "has_more": false }
}
```

**Errors:** 401, 403.

### `GET /api/v1/admin/audit-logs/export`
Export matching audit logs.

**Auth:** `admin`

**Query:** same filters as above, plus `format` (`csv` default, `json`).

**Response 200:** CSV/JSON file download (`Content-Disposition: attachment`). **Errors:** 401, 403.

---

## 5.9 Author — My Events & Templates

Author-scoped staff see only records they created (`created_by` = JWT `sub`). Grants: `read` on these paths (§4.4, §9.6). Item detail/update via the standard paths is author-scoped for non-admin grants.

### `GET /api/v1/me/events`
List events the caller created.

**Auth:** `read` + author scope.

**Query:** same filters as `GET /api/v1/events` (`search`, `status`, `limit`, `offset`).

**Response 200:** paginated event list (as §5.1 list shape), filtered to `created_by = jwt.sub`.

**Errors:** 401, 403.

### `GET /api/v1/me/templates`
List templates the caller created.

**Auth:** `read` + author scope.

**Query:** `type`, `search`, `limit`, `offset`.

**Response 200:** paginated template list (as §5.3 list shape), filtered to `created_by = jwt.sub`.

**Errors:** 401, 403.

---

# 6. Route Summary

All domain routes under `/api/v1`. `required_level` refers to the endpoint catalog (Appendix A).

| Method | Path | required_level |
|--------|------|----------------|
| GET | `/events` | `read` |
| POST | `/events` | `write` |
| GET | `/events/{id}` | `read` |
| PATCH | `/events/{id}` | `write` |
| DELETE | `/events/{id}` | `write` |
| GET | `/events/{id}/stats` | `read` |
| POST | `/events/{id}/clone-template` | `write` |
| POST | `/events/{id}/clone-email-template` | `write` |
| POST | `/events/{id}/bulk-issue` | `write` |
| POST | `/events/{id}/reissue` | `admin` |
| GET | `/events/{id}/revoke-expired` | `read` |
| POST | `/events/{id}/revoke-expired` | `admin` |
| POST | `/events/{id}/issue-completed` | `write` |
| GET | `/events/{id}/attendees` | `read` |
| POST | `/events/{id}/attendees` | `write` |
| POST | `/events/{id}/attendees/import` | `write` |
| PATCH | `/attendees/{id}` | `write` |
| DELETE | `/attendees/{id}` | `write` |
| DELETE | `/attendees/{id}/with-cert` | `admin` |
| GET | `/attendees/{id}/delete-preview` | `read` |
| GET | `/attendees/{id}/file-data` | `read` |
| GET | `/templates` | `read` |
| POST | `/templates` | `write` |
| GET | `/templates/{id}` | `read` |
| PATCH | `/templates/{id}` | `write` |
| DELETE | `/templates/{id}` | `write` |
| POST | `/certificates` | `write` |
| POST | `/certificates/bulk` | `write` |
| POST | `/certificates/upload` | `write` |
| GET | `/certificates` | `read` |
| GET | `/certificates/{id}` | `read` + owner |
| GET | `/certificates/{id}/pdf` | `read` + owner |
| GET | `/certificates/{id}/download` | `read` + owner |
| POST | `/certificates/{id}/revoke` | `admin` |
| DELETE | `/certificates/{id}` | `admin` |
| POST | `/certificates/{id}/email` | `write` |
| GET | `/certificates/{id}/email-logs` | `read` |
| POST | `/certificates/{id}/reissue` | `admin` |
| POST | `/certificates/expire` | `admin` |
| GET | `/certificates/qr` | `read` |
| GET | `/me/certificates` | `read` + owner |
| GET | `/me/certificates/{id}` | `read` + owner |
| GET | `/me/events` | `read` + author |
| GET | `/me/templates` | `read` + author |
| GET | `/verify/{certificate_number}` | public |
| GET | `/view/{id}` | public |
| GET | `/dashboard/stats` | `read` |
| GET | `/dashboard/activity` | `read` |
| GET | `/admin/audit-logs` | `admin` |
| GET | `/admin/audit-logs/export` | `admin` |

Total: **50 domain endpoints** (48 JWT-gated + 2 public).

Auth group (public, §9):

| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/callback` | public (throttled) |
| POST | `/auth/refresh` | public (httpOnly cookie) |
| POST | `/auth/logout` | public (httpOnly cookie) |

---

# 7. Data Model Reference

> Adapted from `e-cert/schema-documentation.md` to **MySQL 8** (the LOA target). Auth/identity tables (`users`, `refresh_tokens`, `password_resets`, `email_confirmations`, `user_memberships`) are **dropped**. This section is a reference for the endpoint field contracts; the authoritative DDL spec will be produced during implementation.

## 7.1 General adaptations (Postgres → MySQL)

| Postgres feature | MySQL 8 replacement |
|------------------|----------------------|
| `UUID DEFAULT gen_random_uuid()` | `CHAR(36)` PK, generated in app (or `BINARY(16)` if chosen) |
| `TIMESTAMPTZ` | `DATETIME(6)` UTC |
| `JSONB` | `JSON` |
| `DATE` | `DATE` |
| Partial unique indexes (`WHERE revoked_at IS NULL`) | Generated column trick — see §7.3 |
| RLS | None — authorization enforced server-side in the API layer |
| `next_certificate_number()` / `issue_certificate_atomic()` | Service-layer transaction with row lock (`SELECT ... FOR UPDATE` on `certificate_sequences`) |

## 7.2 Tables

### organizations (single seeded row: `Lyceum of Alabang`)
`id` PK, `name` TEXT NOT NULL, `slug` TEXT UNIQUE NOT NULL (`loa`), `created_at`, `updated_at`.

### certificate_templates
`id` PK, `organization_id` FK CASCADE, `name` NOT NULL, `description`, `type` (`certificate`|`email`) NOT NULL DEFAULT `certificate`, `html_content` NOT NULL, `css_content` DEFAULT ``, `created_by` TEXT NULL (opaque Auth `sub`, no FK — author scope, §9.6), `created_at`, `updated_at`.
- `UNIQUE(organization_id, name)`.

### events
`id` PK, `organization_id` FK CASCADE, `template_id` FK NULL, `email_template_id` FK NULL, `name` NOT NULL, `description`, `event_date` DATE, `location`, `organizer`, `certificate_title` DEFAULT `Certificate of Participation`, `certificate_number_pattern` DEFAULT `LOA-YYYY-####`, `valid_until` DATE, `status` (`draft`|`active`|`archive`) DEFAULT `draft`, `created_by` TEXT NULL (opaque Auth `sub`, no FK — author scope, §9.6), `created_at`, `updated_at`.

### certificates
`id` PK, `organization_id` FK CASCADE, `event_id` FK NULL CASCADE, `template_id` FK NULL, `recipient_name` NOT NULL, `recipient_email` NOT NULL, `certificate_number` NOT NULL, `issued_at` DEFAULT now, `expires_at`, `revoked_at`, `revoke_reason`, `file_path`, `metadata` JSON (holds `rendered_html` regeneration cache; **base64 `rendered_pdf` moved to storage**, only `file_path` kept), `created_at`, `updated_at`.
- `UNIQUE(event_id, recipient_email)` — plain unique (MySQL NULLs never collide, so non-event certs are unaffected).

### event_attendees
`id` PK, `event_id` FK CASCADE, `organization_id` FK CASCADE, `name` NOT NULL, `email` NOT NULL, `attended` BOOL DEFAULT FALSE, `completed` BOOL DEFAULT FALSE, `attended_at`, `completed_at`, `certificate_id` FK NULL, `certificate_number`, `metadata` JSON (`generation_mode` `template`|`file`, `file_name`, `file_type`, `file_path`), `created_at`, `updated_at`.
- `UNIQUE(event_id, email)`.

### certificate_emails
`id` PK, `certificate_id` FK CASCADE, `sent_to` NOT NULL, `subject` NOT NULL, `sent_at` DEFAULT now, `sent_by` **TEXT** (opaque Auth user id, no FK), `status` DEFAULT `sent`, `error_message`.

### certificate_sequences
`organization_id` FK CASCADE, `pattern` NOT NULL, `next_value` INT DEFAULT 1, `created_at`, `updated_at`. PK `(organization_id, pattern)`.

### audit_logs
`id` PK, `organization_id` FK CASCADE, `user_id` **TEXT** (opaque Auth user id, no FK), `user_email`, `action` NOT NULL, `source` NOT NULL, `entity_type`, `entity_id`, `details` JSON, `ip_address`, `user_agent`, `created_at`.

## 7.3 Active-certificate uniqueness (MySQL)

MySQL lacks partial indexes. Simulate `UNIQUE(certificate_number) WHERE revoked_at IS NULL`:

```sql
ALTER TABLE certificates
  ADD COLUMN number_active CHAR(36) GENERATED ALWAYS AS
    (IF(revoked_at IS NULL, certificate_number, NULL)) STORED,
  ADD UNIQUE INDEX uq_cert_number_active (number_active);
```

Re-issuing after revocation reuses the same number (the generated column becomes non-NULL again only when active).

## 7.4 Certificate number generation

- `certificate_number_pattern` supports literal text plus placeholders: `YYYY` (year), `####` (zero-padded sequence, width = count of `#`).
- Sequence is counted per `(organization_id, pattern)` in `certificate_sequences`.
- **Atomicity:** within a transaction, `SELECT ... FOR UPDATE` the sequence row, increment, format, insert certificate. Concurrent issuance can never duplicate a number.
- Default pattern: `LOA-YYYY-####` (e.g. `LOA-2026-0001`).

## 7.5 Template placeholders

`{{recipient_name}}`, `{{certificate_number}}`, `{{issued_date}}`, `{{event_name}}`, `{{event_date}}`, `{{event_location}}`, `{{organization_name}}`, `{{qr_code}}` (per `business-contexts/certificate/entities/template.md` §7).

---

# 8. Design Decisions & Notes

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Single seeded organization, scoped from JWT tenant claim | LOA is single-tenant; `organizations` kept for schema fidelity and future multi-org |
| 2 | `PATCH` for partial updates; `POST` for state actions | Correct REST; supersedes README §10's `PUT /certificates/{id}/revoke` |
| 3 | Synchronous bulk operations (no workflow engine) | LOA has no workflow runtime; per-item result objects preserve observability |
| 4 | PDF streaming endpoints stay binary | e-cert recommendation §3; no base64 over JSON for downloads |
| 5 | Uploads are multipart | e-cert recommendation §6; base64 replaced |
| 6 | `rendered_pdf` base64 moved to storage; `file_path` kept | Heaviest payload in the old schema (schema-documentation warning) |
| 7 | `auth`-type templates dropped | Auth email content owned by Auth Platform |
| 8 | `status` derived, never stored | Matches e-cert behavior; no migration drift |
| 9 | One active cert per `(event_id, recipient_email)` | e-cert `certificates_event_email_unique` invariant |
| 10 | Audit `user_id` / email `sent_by` become opaque TEXT (no FK) | Auth `users` table is not in this database |
| 11 | Public endpoints never expose emails or internal HTML | Recipient privacy |
| 12 | Template locking when referenced | Prevents breaking issued certificates |
| 13 | Runtime authorization is **level-based** (`<level>:<path>`), not `cert.*` keys | Matches `tenant-group-endpoint-grants.md` — levels are the tenant-app model; `cert.*` keys are not enforced by Cert (§4.5) |
| 14 | Cert keeps a **local mirror** of the endpoint catalog for enforcement | No DB/HTTP per request; Auth Platform remains the source of truth for granting |
| 15 | `write` and `admin` share ordinal 2; `admin` is an operational label | Mirrors the Auth Platform model; admin-only paths are granted only to the admin group |
| 16 | JWT validated with **no local user lookup** (no users table) | Account state is enforced by Auth at issuance; cert trusts the signed claims |
| 17 | Refresh/logout are **proxied by Cert** using the httpOnly refresh cookie | Keeps the refresh token out of JS (XSS risk); refines README §11.5–11.6 |
| 18 | Owner rule enforced in the controller using the middleware-resolved granted level | `read` on certificate paths is necessary but not sufficient for participant access |
| 19 | Author-scoped staff use `/api/v1/me/events` + `/api/v1/me/templates` and non-admin item-path grants; records carry `created_by = jwt.sub` | The level model has no authorship dimension (Auth's `author` filter is a stub); `/me` paths mirror the participant pattern and keep grants path-separated (§9.6) |

---

# 9. Auth Platform Integration & SSO

This section is the concrete contract for authentication and authorization. It supersedes the v1.0 "deferred" note and refines `README.md` §11 and `web-ui.md` §4 where noted. Authority specs: `tenant-group-endpoint-grants.md` (Final v1.1), `tenant-endpoint-catalog.md`, `group-permission-management.md` (Final v2.0), `assemblies/loa-auth-platform/web-ui.md` §4.2.

## 9.1 Trust Model

- The Cert Platform trusts the Auth Platform for **identity** and **permission resolution**.
- Shared secrets: `JWT_SECRET` (token signature, HS256) and `ENCRYPTION_KEY` / `ENCRYPTION_KEY_PREVIOUS` (SSO payload AES-256-GCM).
- Cert validates JWT **locally**; the only server-to-server calls to Auth are the SSO callback validation (none — callback is self-contained), refresh, and logout.
- Cert has **no users or roles tables**. Identity is the JWT claims (`sub`, `email`, `name`, `tenant`, `groups`, `permissions`).

## 9.2 SSO Redirect (browser flow)

```
User visits e-cert.vercel.app
    → [no session] frontend redirects to
        https://auth.lyceumalabang.edu.ph/login?redirect=https://e-cert.vercel.app
    → user authenticates on Auth Platform
    → Auth Platform encrypts token payload (AES-256-GCM, nonce[12]+tag[16]+cipher)
    → redirects browser to
        https://e-cert.vercel.app#payload=<base64url_encrypted>
    → Cert frontend extracts the fragment and calls POST /api/v1/auth/callback
    → Cert backend decrypts + validates, sets httpOnly refresh cookie, returns access token
```

- The `redirect` origin (`https://e-cert.vercel.app`) must be in the Auth Platform's `AUTH_ALLOWED_REDIRECTS` (and/or the `loa` tenant's `redirect_origins`).
- URL fragments never reach servers — only the frontend can read `#payload=`.

## 9.3 `POST /api/v1/auth/callback`

Processes the encrypted SSO payload and establishes the Cert session.

**Request:**

```json
{ "payload": "<base64url_encoded_aes256gcm_blob>" }
```

**Decryption (mirror of Auth's `EncryptionService`):**

1. Base64url-decode (restore padding, `strtr('-_', '+/')`).
2. Split bytes: `nonce[12] + auth_tag[16] + ciphertext[...]` (min length 29).
3. `openssl_decrypt(..., 'aes-256-gcm', ENCRYPTION_KEY, OPENSSL_RAW_DATA, nonce, tag)`; on failure retry with `ENCRYPTION_KEY_PREVIOUS`.
4. JSON-decode the plaintext.

**Decrypted payload structure** (per Auth `web-ui.md` §4.2):

```json
{
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 900,
  "user": { "id": "...", "email": "...", "name": "..." },
  "tenant": { "id": "...", "slug": "loa" },
  "iat": 1754000000,
  "exp": 1754000900
}
```

**Validation steps (server):**

1. Payload field present and decrypts → else `400` (missing/malformed/tampered).
2. `exp` not in the past → else `400` (stale payload).
3. JWT `access_token` validates locally (HS256 signature, `type=access`, `exp`, `tenant.slug=loa`) → else `401`.
4. `tenant.slug` matches `config('cert-platform.tenant_slug')` (`loa`) → else `403` (tenant mismatch). Because Auth issued the token for the `loa` tenant, the tenant claim is authoritative for membership — no separate membership lookup (deviation from README §11.3 step 5, which suggested an Auth API call).
5. Nonce/anti-replay: payloads are single-use via `exp`; an application-level cache of recently seen `jti`-like payloads is optional and not required.

**Success 200:**

```json
{
  "status": "success",
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": { "id": "...", "email": "...", "name": "..." },
    "tenant": { "id": "...", "slug": "loa" }
  }
}
```

- `refresh_token` is **not** returned in the body. It is set as an **httpOnly, SameSite=Lax, Secure** cookie `loa_cert_refresh` (`HttpOnly; Path=/api/v1/auth; SameSite=Lax; Secure`) so it is invisible to JS.
- **Errors:** `400` (missing/malformed/expired/tampered payload), `401` (invalid token), `403` (tenant mismatch), `429` (rate limit).
- **Throttle:** `10/min` per IP (`ThrottleRequests`).
- **Audit:** log `auth.sso_callback` (user_email, source, IP) after success.

## 9.4 JWT Validation — `jwt.auth`

Cert mirror of Auth's `JwtMiddleware`, **minus the DB user lookup** (Cert has no `users` table):

1. Extract `Authorization: Bearer <token>` → else `401`.
2. Validate HS256 signature and `exp` via a local `JWTService` (copy of Auth's, reading `config('jwt.secret')`) and require `type === 'access'` → else `401`.
3. Require `claims['tenant']['slug'] === config('cert-platform.tenant_slug')` (`loa`) → else `403` (`reason: tenant_mismatch`).
4. Set request attributes: `jwt_claims`, `jwt_token`, and a lightweight `cert_user` value object (`sub`, `email`, `name`, `tenant`, `groups`, `permissions`).

**Revocation caveat:** JWT claims are valid for the token lifetime; group/grant changes take effect at next issuance. Optional real-time re-validation via `GET /api/v1/auth/access` is a per-app decision and is **not** used here (see `tenant-group-endpoint-grants.md` §14).

## 9.5 Endpoint Permission Enforcement — `jwt.endpoint`

Cert mirror of Auth's `ClaimPolicyMiddleware::handleLevelBased` (the `RoutePolicy`/claims branch is omitted — Cert has no claim-policy tables):

1. Load the **local catalog mirror** from `config/cert-endpoints.php` (method → path → `required_level`, param-aware, same shape as Appendix A).
2. Match request `method + path` (leading `/` restored, param-aware) to a catalog entry.
   - No entry **and** the route is public (`verify`, `view`, `auth/*`) → allow.
   - No entry **and** the route is non-public → `403` (`reason: no_catalog_entry`, closed-by-default).
3. Scan the JWT `permissions` claim for `<level>:<path>` entries (level case-insensitive; optional `*` method prefix) whose path matches the request (param-aware).
4. `granted_level` = the first matching entry's level; no match → `403` (`reason: no_access`, `effective_level: none`).
5. `granted_level === 'deny'` → `403` (`reason: denied`).
6. `ordinal(granted_level) < ordinal(required_level)` → `403` (`reason: insufficient_level`).
7. Store the granted level as request attribute `jwt_endpoint_level` so controllers can apply the owner rule (§9.6).

**Ordinals:** `read=1`, `write=admin=2`, `deny=-1`, `none=0`.

**Catalog sync:** the local mirror is a deployment artifact generated from the same catalog imported into Auth (Appendix A). Auth is authoritative for grants; the local copy only mirrors `required_level` and paths for matching. Add the `permissions:sync-cert-catalog` artisan command to re-generate the mirror during deploys.

## 9.6 Resource Scoping Rules (Owner / Author)

Levels decide *whether* a caller may invoke an endpoint; **scope** decides *which records* the controller may return or mutate. The middleware stores the caller's granted level as `jwt_endpoint_level`; controllers apply the rule below.

| Scope | Resource paths | Controller rule |
|-------|----------------|-----------------|
| `recipient` | `/api/v1/certificates/{id}`, `/{id}/pdf`, `/{id}/download`, `/api/v1/me/certificates*` | Caller is the certificate recipient: `jwt.email === certificate.recipient_email` |
| `author` | `/api/v1/events/{id}`, `/api/v1/events/{id}/*`, `/api/v1/templates/{id}`, `/api/v1/me/events`, `/api/v1/me/templates` | Caller created the record: `jwt.sub === record.created_by` |
| `unscoped` | any | No filter |

**Rule selection:**

1. If `jwt_endpoint_level === 'admin'` → **unscoped** (no record filter).
2. Certificate item endpoints (detail/pdf/download) with granted level `read` → **recipient** scope.
3. Event/template item operations (detail, PATCH, DELETE) and event sub-resource actions with granted level `read` or `write` (i.e., non-admin) → **author** scope.
4. `/me/*` endpoints (`/me/certificates`, `/me/events`, `/me/templates`) are **always** scoped to the caller regardless of level — recipient for certificates, author for events/templates.

**Grant patterns (author-scoped staff):**

- Granted: `read`/`write` on item paths (`/api/v1/events/{id}`, `/api/v1/templates/{id}`), `read` on `/api/v1/me/events` and `/api/v1/me/templates`, plus `read` on participant certificate paths if needed.
- **Not** granted: unscoped collection reads (`/api/v1/events`, `/api/v1/templates`).
- `created_by` is written from `jwt.sub` at create time (events, templates).

**Known limitation:** with the level model, `write` on the collection path `/api/v1/events` covers **both** `POST /api/v1/events` (create) and `GET /api/v1/events` (unscoped list) — the grant entry matches by path across methods. "Create but only see own" cannot be expressed in a single grant. If collection visibility must stay own-scoped, do not grant collection `write`; author-scoped users then cannot create events (a creation endpoint is not provided under `/me/*`).

## 9.7 Token Refresh — `POST /api/v1/auth/refresh`

- Reads the `loa_cert_refresh` httpOnly cookie → else `401`.
- Calls Auth `POST https://auth.lyceumalabang.edu.ph/api/v1/auth/refresh` server-to-server with `{ "refresh_token": "<cookie>" }`.
- On success: re-issues the refresh cookie (rotated by Auth), returns `{ "data": { "access_token", "token_type", "expires_in" } }`.
- On Auth failure: clears the cookie, `401`.
- **Throttle:** `10/min` per IP.
- Refines README §11.5 (previously the frontend called Auth directly; now Cert proxies so the refresh token stays in the httpOnly cookie).

## 9.8 Logout — `POST /api/v1/auth/logout`

- Reads the `loa_cert_refresh` cookie; if present, calls Auth `POST /api/v1/auth/logout` server-to-server with the refresh token.
- Clears the cookie; returns `204`.
- Frontend also discards its in-memory access token (web-ui.md §4).
- Refines README §11.6.

## 9.9 Frontend Permission Store

- After callback, the frontend calls `GET https://auth.lyceumalabang.edu.ph/api/v1/auth/access` with the in-memory access token to load the resolved `<level>:<path>` permission set for UI gating (per `tenant-group-endpoint-grants.md` §9.5). Cert does not proxy this endpoint.
- UI mapping levels → e-cert roles is in `web-ui.md` §5 (updated for levels in a later pass).

## 9.10 Configuration

`.env` (Cert):

```
JWT_SECRET=<shared with Auth Platform>
ENCRYPTION_KEY=<shared AES-256 key, 64-char hex or base64:>
ENCRYPTION_KEY_PREVIOUS=<old key, optional>
AUTH_BASE_URL=https://auth.lyceumalabang.edu.ph
CERT_TENANT_SLUG=loa
CERT_REFRESH_COOKIE=loa_cert_refresh
CERT_REFRESH_COOKIE_TTL=10080
```

`config/cert-platform.php`: `tenant_slug`, `organization` lookup, cookie name/TTL.
`config/cert-endpoints.php`: local catalog mirror (Appendix A shape).
`config/auth-platform.php`: `base_url`, `encryption_key`, `encryption_key_previous`, timeouts.
`config/jwt.php`: `secret`, `access_ttl` (15), `algo` — same defaults as Auth.

---

# 10. Security Checklist

- [ ] All non-public endpoints behind `jwt.auth` + `jwt.endpoint` (§9.4, §9.5)
- [ ] Level checks per endpoint exactly as §4/§6/Appendix A
- [ ] Closed-by-default: any non-public route missing from the local catalog returns 403
- [ ] Public endpoints return no emails, no internal HTML, minimal ids
- [ ] Recipient scope enforced for participant certificate access (email match)
- [ ] Author scope enforced for non-admin event/template item operations (`created_by = jwt.sub`)
- [ ] Tenant claim validated against `CERT_TENANT_SLUG` on every authenticated request
- [ ] SSO payload decryption with key-rotation fallback; `exp` enforced; tenant mismatch rejected
- [ ] Refresh token only in httpOnly `SameSite=Lax` cookie; never in JS-accessible storage
- [ ] Certificate numbers generated atomically (row lock); no race can duplicate a number
- [ ] One active certificate per (event, email) enforced
- [ ] CSV import validated row-by-row; `replace` mode requires explicit confirm
- [ ] Template updates/deletes blocked when referenced (409)
- [ ] PDF streams served as binary, never cached with sensitive headers
- [ ] Audit logged for: issue, revoke, delete, reissue, expire, email, verify(viewed), import, sso callback
- [ ] Pagination limits enforced (max 100)
- [ ] `Content-Type`/size limits on uploads
- [ ] Throttling on `auth/callback` and `auth/refresh` (10/min per IP)

---

# 11. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Base64 PDFs in JSON responses | Bloats payloads, breaks streaming | Binary streaming endpoints |
| Client-supplied `organization_id` | Cross-tenant leakage | Derive org from JWT tenant claim |
| Storing `status` as a column | Drift between column and rules | Derive from `revoked_at`/`expires_at` |
| Letting participants see all certificates | Privacy breach | `/me` scoping + owner rule on detail |
| Reusing certificate numbers while active | Breaks public verification | Active-number uniqueness (§7.3) |
| Per-request Auth HTTP calls for JWT validation | Latency + availability coupling | Local JWT validation with shared secret |
| Exposing auth-email templates | Ownership drift | Auth Platform owns auth content |
| Storing the refresh token in `localStorage` | XSS token theft | httpOnly cookie + Cert-proxied refresh |
| Per-request Auth `/access` calls for enforcement | Latency + availability coupling | Local catalog mirror + JWT permissions claim |
| Gating on `cert.*` keys | Unsupported by the level-based tenant-app model | Level-based `<level>:<path>` enforcement |

---

# 12. Dependency References

| Spec | Role |
|------|------|
| `assemblies/loa-cert-platform/README.md` | Assembly scope; §10 superseded by this spec; §11 refined by §9 here |
| `assemblies/loa-cert-platform/web-ui.md` | Frontend SSO handling §4, permission→role mapping §5 |
| `business-contexts/certificate/README.md` | Domain ownership (certificates, templates, events, attendees) |
| `business-contexts/certificate/entities/certificate.md` | Certificate aggregate rules, invariants |
| `business-contexts/certificate/entities/template.md` | Template types, placeholders, canvas |
| `assemblies/loa-auth-platform/tenant-group-endpoint-grants.md` | **Final v1.1** — level model, `<level>:<path>` claim, enforcement semantics (authority for §4/§9) |
| `assemblies/loa-auth-platform/tenant-endpoint-catalog.md` | Catalog `required_level` vocabulary (authority for Appendix A) |
| `assemblies/loa-auth-platform/group-permission-management.md` | Final v2.0 — `cert.*` keys (superseded for enforcement by levels, §4.5) |
| `assemblies/loa-auth-platform/web-ui.md` | §4.2 encrypted-payload format, splash/redirect flow |
| `assemblies/loa-auth-platform/access-config-import-export.md` | Catalog bulk import/export path (`/endpoints/bulk`) |
| `e-cert/route-documentation.md` | Legacy endpoint requirements (source) |
| `e-cert/schema-documentation.md` | Legacy schema (source; adapted in §7) |

---

# 13. Implementation Inventory

> **Implementation order (approved core slice first):** scaffold + auth integration → events → attendees → templates → certificates. PDF/QR/email/bulk/audit/dashboard endpoints follow in later passes.

| Layer | Item |
|-------|------|
| Assembly | Laravel 12 scaffold in the loa-auth-platform stack (PHP 8.3, MySQL 8, Docker, PHPUnit, l5-swagger) |
| Assembly | `config/cert-platform.php`, `config/cert-endpoints.php` (catalog mirror), `config/auth-platform.php`, `config/jwt.php` |
| Assembly | `JWTService` (HS256, shared secret), `EncryptionService` (AES-256-GCM + previous-key fallback) |
| Assembly | `JwtMiddleware` (`jwt.auth`, no user table), `EndpointPolicyMiddleware` (`jwt.endpoint`, level-based) |
| Assembly | `AuthController` (callback, refresh, logout) + throttles + refresh cookie handling |
| Assembly | `routes/api.php` route groups: `auth/*` public; everything else `['jwt.auth','jwt.endpoint']`; `verify|view` public |
| Assembly | `permissions:sync-cert-catalog` artisan command (generate local catalog mirror) |
| Context | Migrations: organizations (seed 1), certificate_templates (+`created_by`), events (+`created_by`), event_attendees, certificates (+generated column), certificate_emails, certificate_sequences, audit_logs |
| Context | Event + Template + Attendee + Certificate services; `certificate_sequences` atomic number service; author-scope filters (`/me/events`, `/me/templates`, item ownership checks) |
| Service | PDF service (DOMPDF), storage service (`file_path`), notification service (email) |
| Service | QR code generation |
| Tests | JWT + level-enforcement middleware tests; owner-rule tests; core CRUD tests (SQLite `:memory:`, shared test `JWT_SECRET`) |

---

# Appendix A. Endpoint Catalog (`permissions.json`)

The import payload for Auth `POST /api/v1/admin/tenants/{tenant}/endpoints/bulk`. Each entry: `method` (uppercase), `path` (leading `/`, param-aware `{id}`), `required_level` (`read|write|admin`), `label`, `description`. The same data, as `config/cert-endpoints.php`, is the Cert local mirror (§9.5).

```json
{
  "replace": true,
  "endpoints": [
    { "method": "GET",    "path": "/api/v1/events",                       "label": "List events",                       "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events",                       "label": "Create event",                      "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}",                  "label": "Get event",                         "required_level": "read" },
    { "method": "PATCH",  "path": "/api/v1/events/{id}",                  "label": "Update event",                      "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/events/{id}",                  "label": "Delete event",                      "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}/stats",            "label": "Event statistics",                 "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/clone-template",   "label": "Clone certificate template",        "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/clone-email-template", "label": "Clone email template",        "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/bulk-issue",       "label": "Bulk issue certificates",           "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/reissue",          "label": "Reissue certificates for event",    "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/events/{id}/revoke-expired",   "label": "Count expired certificates",        "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/revoke-expired",   "label": "Revoke expired certificates",       "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/events/{id}/issue-completed",  "label": "Issue certificates for completed",  "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/events/{id}/attendees",        "label": "List event attendees",              "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/events/{id}/attendees",        "label": "Add attendee",                      "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/events/{id}/attendees/import", "label": "Import attendees CSV",              "required_level": "write" },
    { "method": "PATCH",  "path": "/api/v1/attendees/{id}",               "label": "Update attendee",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/attendees/{id}",               "label": "Delete attendee",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/attendees/{id}/with-cert",     "label": "Delete attendee with certificate",   "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/attendees/{id}/delete-preview","label": "Attendee delete preview",           "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/attendees/{id}/file-data",     "label": "Attendee certificate source file",   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/templates",                    "label": "List templates",                    "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/templates",                    "label": "Create template",                   "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/templates/{id}",               "label": "Get template",                      "required_level": "read" },
    { "method": "PATCH",  "path": "/api/v1/templates/{id}",               "label": "Update template",                   "required_level": "write" },
    { "method": "DELETE", "path": "/api/v1/templates/{id}",               "label": "Delete template",                   "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates",                 "label": "Issue certificate",                 "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates/bulk",            "label": "Bulk issue certificates",           "required_level": "write" },
    { "method": "POST",   "path": "/api/v1/certificates/upload",          "label": "Upload certificate file",           "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/certificates",                 "label": "List certificates",                 "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}",            "label": "Get certificate",                   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/pdf",        "label": "Certificate PDF (inline)",          "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/download",   "label": "Certificate PDF (download)",         "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/revoke",     "label": "Revoke certificate",                "required_level": "admin" },
    { "method": "DELETE", "path": "/api/v1/certificates/{id}",            "label": "Delete certificate",                "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/email",      "label": "Send certificate email",            "required_level": "write" },
    { "method": "GET",    "path": "/api/v1/certificates/{id}/email-logs", "label": "Certificate email logs",            "required_level": "read" },
    { "method": "POST",   "path": "/api/v1/certificates/{id}/reissue",    "label": "Reissue certificate",               "required_level": "admin" },
    { "method": "POST",   "path": "/api/v1/certificates/expire",          "label": "Expire certificates",               "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/certificates/qr",              "label": "Certificate QR code",               "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/certificates",              "label": "My certificates",                   "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/certificates/{id}",         "label": "My certificate",                    "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/events",                    "label": "My authored events",                "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/me/templates",                 "label": "My authored templates",             "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/dashboard/stats",              "label": "Dashboard statistics",              "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/dashboard/activity",           "label": "Dashboard activity feed",           "required_level": "read" },
    { "method": "GET",    "path": "/api/v1/admin/audit-logs",             "label": "Query audit logs",                  "required_level": "admin" },
    { "method": "GET",    "path": "/api/v1/admin/audit-logs/export",      "label": "Export audit logs",                 "required_level": "admin" }
  ]
}
```

> `verify/{certificate_number}` and `view/{id}` are **public** — no catalog entry, no JWT. The `auth/*` routes are public but not cataloged (cookie/payload flow, §9).
