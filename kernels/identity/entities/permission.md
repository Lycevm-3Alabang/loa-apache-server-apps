# Permission Entity

## Identity Kernel

### Purpose

Fine-grained access control mapped to endpoints or pages.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | integer | PK | Unique identifier |
| key | string | unique, required | Permission identifier |
| description | string | nullable | Human-readable description |
| endpoint_pattern | string | nullable | Maps to API endpoint |
| created_at | timestamp | auto | Creation time |

### Naming Convention

```
{context}.{resource}.{action}
```

### Examples

| Key | Endpoint Pattern | Description |
|-----|------------------|-------------|
| consult.appointments.create | POST /api/v1/appointments | Create appointments |
| consult.appointments.view | GET /api/v1/appointments | View appointments |
| cert.certificates.issue | POST /api/v1/certificates | Issue certificates |
| cert.templates.edit | PUT /api/v1/templates/{id} | Edit templates |
| users.view | GET /api/v1/users | View users |
| users.manage | * /api/v1/users/* | Manage users |

### Invariants

1. Key must be unique
2. Key follows naming convention
3. Endpoint pattern is optional (some permissions are page-based)

### Relationships

- belongsToMany Role (via role_permission)
- belongsToMany User (via user_permission)

### Factory

```php
Permission::create([
    'key' => 'consult.appointments.create',
    'description' => 'Create consultation appointments',
    'endpoint_pattern' => 'POST /api/v1/appointments',
]);
```
