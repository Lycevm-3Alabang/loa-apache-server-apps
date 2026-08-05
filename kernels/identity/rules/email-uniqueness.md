# Email Uniqueness Rule

## Identity Kernel — Business Rule

### Purpose

Ensures every user account has a unique email address.

### Rule

No two users may share the same email address. Email is the primary user identifier for authentication.

### Enforcement Point

- `UserRepository.create()` — before insert, check that email does not exist
- `UserRepository.update()` — before update, check that new email is not taken by another user

### Case Sensitivity

Email comparison is case-insensitive. `User@lyceumalabang.edu.ph` is the same as `user@lyceumalabang.edu.ph`.

### Anti-Patterns

- Do not allow multiple accounts with the same verified email
- Do not silently deduplicate — reject with a clear error
- Do not treat email uniqueness as optional
