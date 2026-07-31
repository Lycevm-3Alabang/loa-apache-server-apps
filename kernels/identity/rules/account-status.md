# Account Status Rule

## Identity Kernel — Business Rule

### Purpose

Determines whether a user account is allowed to authenticate based on its current status.

### Status Values

| Status | Can Authenticate | Description |
|--------|-----------------|-------------|
| active | Yes | Normal operation |
| disabled | No | Admin action, manual re-enable required |
| locked | No | Automatic, clears after lockout or manual unlock |

### Flow

```
Login attempt
  → status = disabled  → reject (AccountDisabledException)
  → status = locked    → reject (AccountLockedException)
                           → check locked_until
                               → expired → auto-unlock → proceed
                               → not expired → reject
  → status = active    → proceed to password validation
```

### Enforcement Point

- `IdentityService.login()` — first check before password validation

### Transitions

```
      ┌─────────────────────────────┐
      │                             │
      ▼                             │
  ┌────────┐    admin     ┌──────────┐
  │ active │─────────────►│ disabled │
  └───┬────┘              └──────────┘
      │                        │
      │ 5 failures          admin
      ▼                        │
  ┌────────┐    auto or     ┌───▼────┐
  │ locked │◄──────────────►│ active │
  └────────┘   admin unlock └────────┘
```

### Anti-Patterns

- Do not allow a disabled account to authenticate under any circumstances
- Do not allow partial authentication (e.g., password validation before status check)
- Do not allow status changes to bypass audit logging
