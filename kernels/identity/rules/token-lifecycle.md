# Token Lifecycle Rule

## Identity Kernel — Business Rule

### Purpose

Defines the lifetime, rotation, and revocation rules for JWT tokens.

### Token Types

| Token | Lifetime | Storage | Revocable |
|-------|----------|---------|-----------|
| Access token | 15 minutes | Stateless (JWT) | No |
| Refresh token | 7 days | Database | Yes |

### Rotation

On every `refresh()` call:
1. Validate the current refresh token
2. Issue a new access token
3. Issue a new refresh token
4. Revoke the old refresh token (invalidate in database)

This means refresh tokens are single-use.

### Revocation Scenarios

| Action | Effect |
|--------|--------|
| `logout()` | Revokes the provided refresh token |
| Password change | Revokes all refresh tokens for the user |
| Password reset | Revokes all refresh tokens for the user |
| Account locked | Revokes all refresh tokens for the user |
| Account disabled | Revokes all refresh tokens for the user |

### Validation

Access tokens are validated locally (stateless) using HMAC-SHA256 shared secret:
1. Verify signature
2. Check expiry
3. Extract claims (userId, groups, permissions, tokenVersion)

No HTTP call to the auth service is required per request.

### Token Claims

```
{
  "sub": "user-uuid",
  "email": "user@loa.edu.ph",
  "groups": ["Faculty", "CCS"],
  "permissions": [
    "consult.appointments.create",
    "cert.certificates.issue",
    "users.view"
  ],
  "tokenVersion": 1,
  "iat": 1680000000,
  "exp": 1680000900
}
```

### Permission Claims

The `permissions` claim is the user's effective permission set, computed at token issuance by the AuthorizationService:

```
(Group A permissions) ∪ (Group B permissions) ∪ ... ± (user overrides), deny-wins
```

Consumer apps enforce access by checking `permissions` for the required key. They never resolve groups themselves.

Permission changes take effect at next token refresh (bounded by the 15-minute access token lifetime). For immediate revocation, bump `tokenVersion` to invalidate all outstanding access tokens.

### Anti-Patterns

- Do not store access tokens in database (defeats stateless validation)
- Do not use long-lived access tokens (max 15 minutes)
- Do not reuse refresh tokens (rotation is mandatory)
- Do not validate access tokens via HTTP on every request
