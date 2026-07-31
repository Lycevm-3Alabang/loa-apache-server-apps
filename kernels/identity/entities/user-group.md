# UserGroup Entity

## Identity Kernel

### Purpose

Flexible grouping mechanism for organizing users. Universal across all applications.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | integer | PK | Unique identifier |
| name | string | unique, required | Group identifier |
| description | string | nullable | Human-readable description |
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

1. Name must be unique
2. Groups are universal (not app-specific)
3. A user can belong to multiple groups

### Relationships

- belongsToMany User (via user_user_group)
- belongsToMany Permission (via user_group_permission)

### Factory

```php
UserGroup::create([
    'name' => 'Faculty',
    'description' => 'All teaching staff',
]);
```
