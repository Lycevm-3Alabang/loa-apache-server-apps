# LoginAttempt Entity

## Identity Kernel

### Purpose

Tracks authentication attempts for brute-force protection and audit.

### Attributes

| Attribute | Type | Constraints | Description |
|-----------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| user_id | UUID | FK, nullable | Associated user (if known) |
| email_attempted | string | required | Email used in attempt |
| ip_address | string | required | Client IP address |
| success | boolean | required | Whether attempt succeeded |
| attempted_at | timestamp | required | Time of attempt |

### Invariants

1. user_id is nullable (unknown users)
2. ip_address is logged for security
3. success is boolean (true/false)
4. Records retained for 90 days

### Relationships

- belongsTo User (nullable)

### Business Rules

1. After 5 consecutive failures for same user_id:
   - Set user.status = 'locked'
   - Set user.locked_until = now + 30 minutes
2. After 5 consecutive failures for same ip_address:
   - Log warning
   - Consider rate limiting

### Factory

```php
LoginAttempt::create([
    'user_id' => $user->id,
    'email_attempted' => $user->email,
    'ip_address' => '127.0.0.1',
    'success' => false,
    'attempted_at' => now(),
]);
```
