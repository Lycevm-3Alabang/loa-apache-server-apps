# Password Policy Rule

## Identity Kernel — Business Rule

### Purpose

Defines minimum strength requirements for user passwords.

### Rule

A password is valid only if all of the following pass:

| Check | Requirement |
|-------|-------------|
| Minimum length | At least 8 characters |
| Uppercase letter | At least one A–Z character |
| Lowercase letter | At least one a–z character |
| Digit | At least one 0–9 character |

### Enforcement Point

- `IdentityService.register()` — during account creation
- `IdentityService.updatePassword()` — during authenticated password change
- `IdentityService.resetPassword()` — during password reset flow

### Failure Behavior

Return a clear error message identifying which requirement failed. Do not reveal which specific check failed in login context (only in registration/password change).

### Storage

Passwords are stored as bcrypt hashes. Never stored in plain text or unsalted hashes.

### Anti-Patterns

- Do not enforce arbitrary character restrictions (e.g., disallowing special characters)
- Do not enforce periodic rotation (NIST no longer recommends mandatory expiry)
- Do not reveal password strength on login failure
