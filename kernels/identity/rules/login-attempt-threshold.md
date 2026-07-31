# Login Attempt Threshold Rule

## Identity Kernel — Business Rule

### Purpose

Prevents brute-force password guessing by locking accounts after repeated failures.

### Rule

| Property | Value |
|----------|-------|
| Max consecutive failures | 5 |
| Lockout duration | 30 minutes |
| Counter resets on | Successful login |

### Flow

```
Login attempt
  ├── Success → reset failed_attempts = 0
  └── Failure → increment failed_attempts
                  └── if failed_attempts >= 5
                       → status = 'locked'
                       → locked_until = now + 30 minutes
                       → publish UserLocked
```

### Enforcement Point

- `IdentityService.login()` — checked before and after each attempt

### Edge Cases

- Once `locked_until` passes, the next login attempt auto-unlocks and resets the counter
- Admin can manually unlock at any time
- Failed attempts on a locked account do not extend the lockout duration

### Anti-Patterns

- Do not lock accounts indefinitely without admin intervention
- Do not disclose remaining attempts count in error messages
- Do not apply threshold to IP address alone (user-level lock is primary)
