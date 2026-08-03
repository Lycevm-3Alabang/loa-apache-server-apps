# UserGroup Entity

## Identity Kernel

### Purpose

Flexible grouping mechanism for organizing users. Universal across all applications.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | integer | PK | Unique identifier |
| name | string | required | Group identifier |
| description | string | nullable | Human-readable description |
| tenant_id | uuid | nullable FK | `NULL` = platform-global; set = tenant-scoped (v3.0) |
| priority | integer | default 10 | Group precedence for tenant-endpoint level resolution (v4.0); **1 = highest** — lower value wins |
| created_at | timestamp | auto | Creation time |

### Naming Convention

- Use nouns: "Faculty", "Students", "CCS"
- Use role-like names: "Program Chair", "Assistant Dean"
- No app-specific prefixes

### Examples

| Name | Description |
|------|-------------|
| Faculty | All teaching staff |
| Students | All learners |
| CCS | College of Computer Studies members |
| Program Chair CS | Computer Science program chair |
| Assistant Dean | Assistant dean level |

### Invariants

1. Name uniqueness is scoped: `(tenant_id, name)` for tenant groups; globally unique for platform groups (`tenant_id IS NULL`)
2. Groups are universal (not app-specific)
3. A user can belong to multiple groups
4. Tenant groups only apply within their tenant (`kernels/identity/tenancy.md` §3.3)
5. `priority` is a precedence rank used **only** by the tenant-endpoint level model (`assemblies/loa-auth-platform/tenant-group-endpoint-grants.md` §3.3/§4). **1 = highest** (lower value = more precedence). Equal priorities are allowed; on a tie `deny` wins, else `admin`/`write` > `read`. The claims-based model does not use it.

### Relationships

- belongsToMany User (via user_user_group)
- belongsToMany Permission (via user_group_permission)
- belongsTo Tenant (nullable)

### Factory

```php
UserGroup::create([
    'name' => 'Faculty',
    'description' => 'All teaching staff',
]);
```
