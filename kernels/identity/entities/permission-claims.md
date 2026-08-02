# Permission Claims

## Identity Kernel

> **SUPERSEDED** by `data-driven-permission-policy.md` (Final). Claim types, group claims, and user overrides defined here were merged into the data-driven permission policy. Retained for historical reference only.

**Version:** 1.0
**Status:** Draft (Superseded)
**Layer:** Platform Kernel (Identity)
**Audience:** Architects, Engineers, AI Development Agents

### Purpose

Defines the claim types used for access control. Claims are simple action-based permissions that map users (via groups) to endpoints.

It answers:

> **"What actions can this user perform?"**

### Problem

The existing permission model uses granular keys (`cert.certificates.issue`, `templates.manage`). This is flexible but complex:
- Every app must define its own permission keys
- Permission discovery is manual
- No standardized action vocabulary

Claims provide a simplified, standardized vocabulary for access control.

---

## Claim Types

| Claim | Meaning | Scope Check |
|-------|---------|-------------|
| `read` | View any resource | None (JWT has claim → allow) |
| `write` | Create/update/delete any resource | None (JWT has claim → allow) |
| `read-authored` | View own authored resources | Owner check (resource.author_id = user.id) |
| `write-authored` | Create/update/delete own authored resources | Owner check (resource.author_id = user.id) |
| `read-scoped` | View resources within user's scope | Scope check (resource.scope ∈ user.scopes) |
| `write-scoped` | Create/update/delete resources within user's scope | Scope check (resource.scope ∈ user.scopes) |
| (none) | No access | Deny all |

### Claim Definitions

#### `read`

Allows viewing any resource regardless of ownership or scope.

**Use case:** Admin viewing all certificates.

#### `write`

Allows creating, updating, or deleting any resource regardless of ownership or scope.

**Use case:** Admin managing all users.

#### `read-authored`

Allows viewing only resources where the user is the author/owner.

**Use case:** Student viewing their own certificates.

**Ownership check:** `resource.author_id = user.id` (or equivalent field)

#### `write-authored`

Allows creating, updating, or deleting only resources where the user is the author/owner.

**Use case:** Student submitting their own evaluation.

**Ownership check:** `resource.author_id = user.id` (or equivalent field)

#### `read-scoped`

Allows viewing resources within the user's assigned scope (department, faculty, campus, etc.).

**Use case:** Dean viewing students in their department.

**Scope check:** `resource.scope_id ∈ user.scope_ids` (derived from group membership)

#### `write-scoped`

Allows creating, updating, or deleting resources within the user's assigned scope.

**Use case:** Department head managing faculty in their department.

**Scope check:** `resource.scope_id ∈ user.scope_ids` (derived from group membership)

---

## Model

```
Users → Groups → Claims
                    ↓
              Endpoints
```

### Components

| Component | Owns | Layer |
|-----------|------|-------|
| Claim Types | `read`, `write`, `read-authored`, `write-authored`, `read-scoped`, `write-scoped` | Identity Kernel |
| Group Claims | Which claims a group has | Identity Kernel |
| Endpoint Claims | Which claims an endpoint requires | Identity Kernel (registry) |
| Ownership Check | How to verify author ownership | Business Context |
| Scope Check | How to verify scope membership | Business Context |

---

## Registry Format

Each app declares which claims its endpoints require.

```json
{
  "app": "loa-cert-platform",
  "version": "1.0.0",
  "routes": [
    { "method": "GET", "path": "/api/v1/certificates", "claims": ["read"] },
    { "method": "POST", "path": "/api/v1/certificates", "claims": ["write"] },
    { "method": "GET", "path": "/api/v1/certificates/{id}", "claims": ["read-authored"] },
    { "method": "PUT", "path": "/api/v1/certificates/{id}", "claims": ["write-authored"] }
  ]
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `app` | string | yes | App identifier |
| `version` | string | yes | Registry version (semver) |
| `routes` | array | yes | Endpoint claim requirements |

#### Route Entry

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `method` | string | yes | HTTP method: `GET`, `POST`, `PUT`, `DELETE`, `*` |
| `path` | string | yes | Route path (uses `{param}` syntax) |
| `claims` | array | yes | Required claims (empty = no access) |

---

## Group Claims

Groups are assigned claims. Users inherit claims from their groups.

### Data Model

```sql
group_claim (
  group_id FK,
  claim varchar NOT NULL,
  created_at timestamp,
  PRIMARY KEY (group_id, claim)
)
```

### Resolution

```
user_claims(user) = ∪ group.claims WHERE user ∈ group
```

**Deny wins:** If any group denies a claim, it is denied (future extension).

**No override:** User-level claim overrides are not supported in v1.0.

---

## Access Control Flow

```
1. Request arrives at endpoint
2. Registry lookup: GET method + path → required claims
3. JWT validation: extract user_id, groups
4. Claim resolution: user_claims = ∪ group.claims
5. Claim check: required claims ⊆ user_claims?
6. If read-scoped/write-scoped: scope check
7. If read-authored/write-authored: ownership check
8. Allow or deny
```

---

## Scope Resolution (read-scoped / write-scoped)

The `read-scoped` and `write-scoped` claims require scope resolution. The scope is derived from the user's group membership.

### How It Works

1. User belongs to group "Dean of Engineering"
2. Group has scope: `department_id = engineering`
3. User requests `GET /api/v1/students` with `read-scoped` claim
4. API filters: `WHERE department_id = 'engineering'`

### Scope Definition

Scopes are defined at the group level:

```sql
user_groups (
  id uuid PK,
  name varchar,
  scope_type varchar,  -- 'department', 'faculty', 'campus'
  scope_id varchar,    -- 'engineering', 'science', etc.
  ...
)
```

### Scope Check Implementation

```php
// Pseudocode
function hasScopeAccess(user, resource, claim) {
    if (claim === 'read' || claim === 'write') {
        return true; // No scope check needed
    }
    
    if (claim === 'read-scoped' || claim === 'write-scoped') {
        userScopes = user.groups.map(g => g.scope_id);
        return resource.scope_id in userScopes;
    }
    
    if (claim === 'read-authored' || claim === 'write-authored') {
        return resource.author_id === user.id;
    }
    
    return false;
}
```

---

## Invariants

1. Every endpoint MUST declare required claims in the registry
2. Empty claims array means no access (deny all)
3. Claims are inherited from group membership
4. `read-scoped` and `write-scoped` require scope definition on groups
5. `read-authored` and `write-authored` require owner field on resources
6. Claim check is performed on every request (no caching beyond token lifetime)

---

## Relationship to Existing Specs

| Spec | Relationship |
|------|-------------|
| `permission.md` | Claims replace granular permission keys |
| `permission-resolution.md` | Claims simplify resolution (union of group claims) |
| `permission-registry.md` | Registry now uses claims instead of permission keys |
| `tenancy.md` | Claims are tenant-scoped (same as permissions) |
| `group-permission-management.md` | Groups now grant claims instead of permissions |

---

## Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Skipping claim check | Security violation | Check claims on every endpoint |
| Caching claim resolution | Stale permissions | Resolve per-request |
| Using `read` when `read-authored` needed | Over-permissioning | Use least-privilege claim |
| Hardcoding scope logic in registry | Scope resolution is implementation | Registry declares claims, API implements scope |
| Granting `write` when `write-scoped` needed | Over-permissioning | Use least-privilege claim |

---

## Security Checklist

- [ ] All protected endpoints have claims declared in registry
- [ ] Empty claims = deny all
- [ ] `read-scoped` and `write-scoped` have scope checks in API
- [ ] `read-authored` and `write-authored` have ownership checks in API
- [ ] Claims are checked on every request
- [ ] No claim caching beyond token lifetime
- [ ] Least-privilege principle: use most restrictive claim

---

## Implementation Inventory

### New Files

| File | Purpose |
|------|---------|
| `kernels/identity/entities/permission-claims.md` | This spec |

### Modified Files

| File | Change |
|------|--------|
| `kernels/identity/entities/permission-registry.md` | Update to use claims instead of permission keys |
| `assemblies/*/permissions.json` | Update to use claims format |
| `database/migrations/*_add_group_claims_table.php` | New table for group claims |
| `app/Services/AuthorizationService.php` | Add claim resolution methods |

---

## Dependency References

| Spec | Role |
|------|------|
| `kernels/identity/entities/permission.md` | Legacy permission model (being replaced) |
| `kernels/identity/rules/permission-resolution.md` | Resolution algorithm (simplified with claims) |
| `kernels/identity/tenancy.md` | Tenant scoping (claims are tenant-scoped) |
| `assemblies/loa-auth-platform/group-permission-management.md` | Admin UI for group claims |
