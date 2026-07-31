# Permission Resolution Rule

## Identity Kernel — Business Rule

### Purpose

Defines how a user's effective permissions are computed from their group memberships and individual overrides.

### Resolution Order

```
User permissions =
    (Group A permissions)
  ∪ (Group B permissions)
  ∪ ...
  ± (User-level overrides)
```

### Rules

1. **Union of groups:** Permissions from all groups the user belongs to are merged (OR logic)
2. **Deny wins within a group:** If a group has both grant and deny for the same permission, deny takes precedence
3. **User override:** A user-level explicit grant can override a group-level deny. A user-level explicit deny can override a group-level grant
4. **Final decision:** If any effective rule grants the permission after applying overrides, access is allowed

### Example

| Source | Permission | Value |
|--------|-----------|-------|
| Group: Faculty | consult.appointments.create | Grant |
| Group: CCS | consult.appointments.create | Grant |
| User override | consult.appointments.create | Deny |

**Result:** Deny (user override wins).

### Enforcement Point

- `AuthorizationService.hasPermission()` — called by middleware on every protected request

### Anti-Patterns

- Do not hardcode permission checks (`if (user.role === 'admin')`)
- Do not skip permission resolution for any protected endpoint
- Do not cache permissions for longer than the access token lifetime (15 min)
