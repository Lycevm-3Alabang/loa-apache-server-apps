# Data-Driven Permission Policy

## Identity Kernel

**Version:** 1.0
**Status:** Final
**Layer:** Platform Kernel (Identity)
**Audience:** Architects, Engineers, AI Development Agents

### Purpose

Defines a fully data-driven access control model where the permission policy is stored in the database and managed through the LOA Auth Platform admin UI. Apps consume the resolved policy — they do not hardcode claim checks or data-filter logic.

It answers:

> **"Who can call this endpoint, what claims unlock it, and how should the app filter data for each claim level?"**

### Why Data-Driven

The previous design kept filter logic (`if admin → all, if read → scoped, if authored → own`) hardcoded in each app's controllers. This is unmanageable at scale:

- Policy changes require code deploys in every app
- No single place to see what a claim actually means per route
- Dean/scope rules differ per app with no shared governance

By storing the policy in the database, the LOA Auth Platform admin UI becomes the single management surface for all access rules across all apps.

### How Apps Declare Their Guard Surface

Each tenant app ships a `permissions.json` file that declares which of its API endpoints must be guarded and which claims unlock them. The Auth Platform **imports** this file so it knows, per app, which endpoints exist and need guarding. Import populates `route_policies`; the admin UI manages them afterward (add/remove claims, change filters).

```
{assembly-root}/permissions.json  →  import (auth platform)  →  route_policies table
```

---

## Model Overview

```
Auth Platform (owns policy)
  ├── JSON import per app         (app declares endpoints + guard claims)
  ├── Claims vocabulary           (what claims exist)
  ├── Route policies              (route → acceptable claims + per-claim filter) ← seeded by import
  ├── Group claims                (group → claims + scope)
  ├── User claim overrides        (user → claim grant/deny)
  │
  └── resolved into JWT:
        ├── permissions: [ "user.admin", "user.read" ]
        └── scopes:       [ { "type": "department", "id": "engineering" } ]

Tenant Apps (consume policy)
  ├── Gate:   check JWT permissions against route policy
  └── Filter: apply declared filter type using JWT scopes
```

---

## Claims Vocabulary

Claims are stored in the `claims` table. They replace ad-hoc permission keys with a standardized vocabulary.

### Table

```sql
claims (
  id uuid PK,
  key varchar UNIQUE NOT NULL,          -- 'user.read', 'user.admin'
  resource varchar NOT NULL,            -- 'user', 'cert', 'appointment'
  action varchar NOT NULL,              -- 'read', 'write', 'admin'
  scope varchar NULL,                   -- 'none' | 'author' | 'scope' | 'all'
  description varchar NULL,
  created_at timestamp,
  updated_at timestamp
)
```

### Standard Actions

| Action | Meaning |
|--------|---------|
| `read` | View any resource |
| `write` | Create/update/delete any resource |
| `admin` | Full management (implies read + write + delete) |

### Standard Scope Values

| Scope | Meaning | Filter Applied |
|-------|---------|----------------|
| `none` | No data filter | — |
| `all` | Whole resource set | no filter |
| `author` | Only resources authored by user | `WHERE created_by = user.id` |
| `scope` | Only resources within user's scope | `WHERE {scope_field} IN user.scopes` |

### Examples

| key | resource | action | scope |
|-----|----------|--------|-------|
| `user.admin` | user | admin | all |
| `user.read` | user | read | all |
| `user.read-authored` | user | read | author |
| `user.read-scoped` | user | read | scope |
| `cert.read` | cert | read | all |
| `cert.read-authored` | cert | read | author |
| `cert.write` | cert | write | all |
| `appointment.write-authored` | appointment | write | author |

The Auth Platform admin UI manages this table (add/rename claims).

---

## JSON Import Format

Each tenant app declares its guarded endpoints in `{assembly-root}/permissions.json`.

### Schema

```json
{
  "app": "loa-cert-platform",
  "version": "1.0.0",
  "routes": [
    {
      "method": "GET",
      "path": "/api/v1/certificates",
      "claims": [
        { "key": "cert.read", "precedence": 1, "filter": "all" },
        { "key": "cert.read-authored", "precedence": 2, "filter": "author" }
      ]
    },
    {
      "method": "POST",
      "path": "/api/v1/certificates",
      "claims": [
        { "key": "cert.write", "precedence": 1, "filter": "all" }
      ]
    }
  ]
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `app` | string | yes | App identifier (matches assembly directory) |
| `version` | string | yes | Registry version (semver) |
| `routes` | array | yes | Guarded endpoints |

#### Route Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `method` | string | yes | HTTP method: `GET`, `POST`, `PUT`, `DELETE`, `*` |
| `path` | string | yes | Route path (uses `{param}` syntax) |
| `claims` | array | yes | Acceptable claims in precedence order |

#### Claim Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `key` | string | yes | Claim key (must exist in `claims` vocabulary) |
| `precedence` | int | yes | Evaluation order (1 = highest) |
| `filter` | string | yes | `all` \| `author` \| `scope` \| `none` |

### Semantics

- **Guard only.** The JSON declares which endpoints must be protected and which claims unlock them. It is the discovery mechanism — it tells the Auth Platform what to manage.
- **Import, not runtime.** The Auth Platform imports the file (command or admin UI upload). Apps do not read it at runtime.
- **Authored by the app team, managed by Auth Platform.** The app declares its surface; the Auth Platform owns the policy afterward.

### Import Behavior

1. Parse the JSON (validate schema, claim keys, precedences)
2. Upsert into `route_policies` for `app` (replace existing routes for that app)
3. Report inserted/updated/skipped routes
4. Flag claims in the file that do not exist in `claims` vocabulary (must be added first)

---

## Route Policies

A route policy declares which claims are acceptable for a route, in precedence order, and which filter each claim applies.

### Table

```sql
route_policies (
  id uuid PK,
  app varchar NOT NULL,             -- 'loa-auth-platform', 'loa-cert-platform'
  method varchar NOT NULL,          -- GET, POST, PUT, DELETE, *
  path varchar NOT NULL,            -- '/api/v1/users'
  claim_key varchar FK -> claims.key,
  precedence int NOT NULL,          -- order of evaluation (1 = highest)
  filter varchar NOT NULL,          -- 'all' | 'author' | 'scope' | 'none'
  PRIMARY KEY (app, method, path, claim_key)
)
```

### Semantics

- Claims are OR-ed: the user needs only one acceptable claim to pass the gate
- `precedence` defines which filter wins when the user holds multiple claims
- Example: user holds both `user.read` and `user.read-authored` → the higher-precedence claim's filter is applied

### Example Rows

| app | method | path | claim_key | precedence | filter |
|-----|--------|------|-----------|------------|--------|
| loa-auth-platform | GET | /api/v1/users | user.admin | 1 | all |
| loa-auth-platform | GET | /api/v1/users | user.read | 2 | scope |
| loa-auth-platform | GET | /api/v1/users | user.read-authored | 3 | author |
| loa-cert-platform | GET | /api/v1/certificates | cert.read | 1 | all |
| loa-cert-platform | GET | /api/v1/certificates | cert.read-authored | 2 | author |
| loa-cert-platform | POST | /api/v1/certificates | cert.write | 1 | all |

**Auth Platform admin UI:** route policy CRUD (which app, which routes, which claims, filter per claim). Seeded initially from each app's `permissions.json` import.

---

## Group Claims

Groups hold claims. Users inherit claims from all groups they belong to.

### Table

```sql
group_claims (
  group_id uuid FK -> user_groups.id,
  claim_key varchar FK -> claims.key,
  scope_type varchar NULL,     -- 'department', 'faculty', 'campus'
  scope_id varchar NULL,       -- 'engineering', 'science'
  PRIMARY KEY (group_id, claim_key, scope_type, scope_id)
)
```

### Scoping

- A group with `user.read-scoped` + `scope_type=department` + `scope_id=engineering` means: members can read users, filtered to the engineering department
- Multiple rows = multiple scopes for the same group
- Scopeless rows (`scope_type NULL`) carry no scope data

### Example

| group | claim_key | scope_type | scope_id |
|-------|-----------|------------|----------|
| Students | user.read-authored | NULL | NULL |
| Students | cert.read-authored | NULL | NULL |
| Faculty | user.read | NULL | NULL |
| Dean-Engineering | user.read-scoped | department | engineering |
| Dean-Engineering | cert.write | NULL | NULL |
| Admin | user.admin | NULL | NULL |
| Admin | cert.write | NULL | NULL |

**Auth Platform admin UI:** existing group management extended to assign claims + scope per group.

---

## User Claim Overrides

Individual users can be granted or denied specific claims, overriding group-inherited claims.

### Table

```sql
user_claim_overrides (
  user_id uuid FK -> users.id,
  claim_key varchar FK -> claims.key,
  granted boolean NOT NULL,
  PRIMARY KEY (user_id, claim_key)
)
```

### Resolution

```
user_claims(user) =
    ∪ group_claims( user.groups )
  + user_claim_overrides( user )        -- overrides apply last

- granted = false → remove claim (deny wins)
- granted = true  → add claim
```

### Example

Juan (Student → `user.read-authored`) gets an override:

| user | claim_key | granted |
|------|-----------|---------|
| Juan | user.read | true |

→ Juan's effective claims: `user.read`, `user.read-authored`

**Auth Platform admin UI:** existing user-permission override management extended to claims.

---

## JWT Claims

At login, the Auth Platform resolves the user's effective claims and scopes, then embeds them in the JWT.

```
{
  "sub": "usr_abc",
  "permissions": ["user.read", "user.read-authored", "cert.read-authored"],
  "scopes": [
    { "type": "department", "id": "engineering" }
  ],
  "tenant": { "id": "...", "slug": "loa" },
  "exp": ...
}
```

- `permissions` = effective claim keys
- `scopes` = union of group scopes (for `scope`-filtered claims)
- Apps validate locally using the shared `JWT_SECRET`

**Caveat:** claims in the JWT are valid for the token lifetime. Group changes take effect at next token issuance (login/refresh). If real-time revocation is required, apps may re-validate via the Auth API (`/api/v1/auth/verify`) — optional, per-app decision.

---

## App-Side Enforcement

Apps consume the policy. They do not hardcode claim logic.

### Gate (can the user call this route?)

```
allowed = ∃ claim ∈ route_policies(app, method, path)
          WHERE claim ∈ jwt.permissions
```

### Filter (which rows to return?)

```
pick claim with highest precedence that user holds
apply its filter:
  'all'    → no filter
  'author' → WHERE {author_field} = jwt.sub
  'scope'  → WHERE {scope_field} IN jwt.scopes[type]
  'none'   → return empty (or allow if gate-only)
```

### Public Routes

Routes with no `route_policies` rows for an app are public (no auth).

### Thin Interpreter (per app)

Each app implements a small policy interpreter — it reads its own routes from the policy DB (or a cached/synced copy) and applies the filter vocabulary to its own schema. The app knows which columns map to `author_field` and `scope_field`; the auth platform never needs to know app schemas.

---

## Filter Vocabulary (Contract)

| filter | Contract | App must provide |
|--------|----------|------------------|
| `all` | Return whole result set | — |
| `author` | Rows where author/creator = current user | name of author column |
| `scope` | Rows where scope field matches one of user's scopes of that type | name of scope column + scope type mapping |
| `none` | No rows returned | — |

The auth platform manages which filter a claim applies per route; the app maps the filter vocabulary to its schema. The vocabulary itself is fixed — apps implement it once.

---

## Invariants

1. Every claim key in `group_claims` and `user_claim_overrides` MUST exist in `claims`
2. Every `route_policies.claim_key` MUST exist in `claims`
3. Route policies are unique per `(app, method, path, claim_key)`
4. Precedence must be unique within a route policy
5. `scope`-filtered routes MUST have scope data in the JWT for the affected claim
6. `author`-filtered routes MUST resolve an author column in the consuming app
7. Empty policy set for a route = public (no auth)
8. User overrides are applied after group claims (overrides win)
9. Claims and scopes in the JWT are valid only for the token lifetime

---

## Security Checklist

- [ ] Route policies are manageable only via Auth Platform admin UI (not app code)
- [ ] Claim/group/override changes are audited (audit trail)
- [ ] JWT permissions + scopes are validated with shared secret in every app
- [ ] `scope` filters never return cross-scope rows (scopes are strict)
- [ ] No claim check, filter, or precedence logic hardcoded in apps
- [ ] Public routes explicitly lack policy rows (never accidentally protected)
- [ ] User overrides cannot escalate beyond the claims vocabulary

---

## Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `permission.md` | Legacy permission keys — replaced by `claims` vocabulary |
| `permission-resolution.md` | Resolution now claims-based: group claims ∪ user overrides |
| `permission-registry.md` | Superseded — JSON registry replaced by `permissions.json` import → `route_policies` table |
| `permission-claims.md` | Merged into this spec (claims + scopes + group mapping) |
| `tenancy.md` | Claims and scopes remain tenant-scoped |
| `group-permission-management.md` | Admin UI extended to manage claims + scopes |

---

## Implementation Inventory

### New Tables

| Table | Purpose |
|-------|---------|
| `claims` | Claim vocabulary |
| `route_policies` | Route → acceptable claims + per-claim filter |
| `group_claims` | Group → claims + scope |
| `user_claim_overrides` | User → claim grant/deny |

### Auth Platform Admin UI

| Page | Purpose |
|------|---------|
| Claims | Manage claim vocabulary |
| Route Policies | Manage route → claims + filters |
| Group Claims | Assign claims + scopes to groups (extend existing) |
| User Overrides | Assign claim overrides to users (extend existing) |

### Tenant App

| File | Purpose |
|------|---------|
| Policy interpreter | Gate + filter per app's schema |

### Deprecations

| Item | Replaced by |
|------|-------------|
| Old `permissions.json` format (permission keys + endpoints) | `permissions.json` import format (route → claims + filter) |
| Hardcoded `if/else` claim chains in controllers | Policy interpreter |
