# User Entity

## Identity Kernel

### Purpose

Represents a person who can authenticate with the platform.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| email | string | unique, required | Login credential |
| password | string | required | bcrypt hash |
| name | string | required | Display name |
| status | enum | required | active, disabled, locked |
| failed_attempts | integer | default: 0 | Consecutive login failures |
| locked_until | timestamp | nullable | Account lock expiry |
| created_at | timestamp | auto | Creation time |
| updated_at | timestamp | auto | Last update time |

### Status Values

- `active` — can authenticate
- `disabled` — cannot authenticate (admin action)
- `locked` — cannot authenticate (too many failures)

### Invariants

1. Email must be unique across system
2. Password must be hashed with bcrypt/argon2
3. Account locks after 5 consecutive failed attempts
4. Lockout duration: 30 minutes
5. Status changes are audited

### Relationships

- belongsToMany UserGroup (via user_user_group)
- hasMany LoginAttempt
- hasMany PasswordResetToken
- hasMany UserPermission (overrides)

### Factory

```php
User::create([
    'email' => 'user@loa.edu.ph',
    'password' => Hash::make('password'),
    'name' => 'John Doe',
    'status' => 'active',
]);
```
