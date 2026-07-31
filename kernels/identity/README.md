# Identity Kernel

## Platform Kernel Specification

**Version:** 2.0
**Status:** Draft
**Layer:** Platform Kernel
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Identity Kernel owns digital identities, authentication, and authorization for the LOA platform ecosystem.

It answers:

> **"Who is this?"**
> **"What groups do they belong to?"**
> **"What can they do?"**

It does not own business workflows, consultation logic, evaluation, or certificate generation.

---

## 2. Responsibilities

### Owns

- User accounts and lifecycle
- Authentication methods (password)
- Credential metadata (hash, attempts, lockout)
- Identity lifecycle (active, disabled, locked)
- UserGroup definitions (flexible, universal)
- Permission definitions (fine-grained, per endpoint/page)
- UserGroup-Permission mappings
- User-UserGroup membership
- JWT token issuance and validation
- Password reset flow
- Login attempt tracking

### Does Not Own

- Department structure (Education Domain)
- Consultation workflows
- Evaluation logic
- Certificate generation
- Any business-specific logic

---

## 3. Core Concepts

### User

Represents a person who can authenticate.

**Attributes:**
- id (UUID)
- email (unique)
- password (hashed)
- name
- status (active, disabled, locked)
- failed_attempts
- locked_until
- created_at
- updated_at

**Invariants:**
- Email must be unique
- Password must be hashed (bcrypt/argon2)
- Account locks after 5 failed attempts
- Lockout duration: 30 minutes

---

### UserGroup

Flexible grouping mechanism. Universal across all applications.

**Attributes:**
- id
- name (unique, e.g., "Faculty", "CCS", "Program Chair")
- description
- created_at

**Naming Convention:**
- Use nouns for groups: "Faculty", "Students", "CCS"
- Use role-like names: "Program Chair", "Assistant Dean"
- No app-specific prefixes

**Examples:**
- "Faculty" — all teaching staff
- "CCS" — College of Computer Studies members
- "Program Chair CCS-CS" — CS program chair
- "Students" — all learners

**Invariants:**
- Name must be unique
- Groups are universal (not app-specific)
- A user can belong to multiple groups

---

### Permission

Fine-grained access control, maps to endpoints/pages.

**Attributes:**
- id
- key (unique, e.g., "consult.appointments.create")
- description
- endpoint_pattern (e.g., "POST /api/v1/appointments")
- created_at

**Naming Convention:**
```
{context}.{resource}.{action}

Examples:
consult.appointments.create
consult.appointments.view
cert.certificates.issue
cert.templates.edit
users.view
users.manage
```

---

### UserGroupPermission

Maps user groups to permissions.

**Attributes:**
- user_group_id
- permission_id
- granted (boolean)

**Invariants:**
- One permission per group
- Can grant or deny

---

### UserUserGroup

Maps users to user groups.

**Attributes:**
- user_id
- user_group_id
- created_at

**Invariants:**
- A user can belong to multiple groups
- A group can have many users

---

### LoginAttempt

Tracks authentication attempts for brute-force protection.

**Attributes:**
- id
- user_id (nullable — unknown users)
- email_attempted
- ip_address
- success (boolean)
- attempted_at

---

### PasswordResetToken

Tokens for password reset flow.

**Attributes:**
- id
- user_id
- token (hashed)
- expires_at
- used_at (nullable)
- created_at

---

### RefreshToken

Persists refresh tokens for validation, rotation, and revocation.

**Attributes:**
- id
- user_id
- jti (hashed JWT ID)
- expires_at
- revoked_at (nullable)
- replaced_by (nullable)
- created_at

**Invariants:**
- jti is stored hashed (never raw)
- Refresh tokens are single-use (rotated on every refresh)
- A revoked or rotated token is permanently rejected

---

## 4. Business Rules

### Authentication

1. User authenticates with email + password
2. System checks account status (active, disabled, locked)
3. System validates password against stored hash
4. On success: issue access token + refresh token
5. On failure: increment failed_attempts
6. After 5 failures: lock account for 30 minutes

### Authorization

1. JWT validates identity (who)
2. User's groups are resolved
3. Permissions from all groups are merged
4. User-level permission overrides applied last
5. Final permission set determines access

### Permission Resolution

```
User permissions = 
  (Group permissions from Group 1)
  ∪ (Group permissions from Group 2)
  ∪ ...
  ± (User overrides)
```

**Deny wins:** If any group denies a permission, user-level override can grant it.

### Token Lifecycle

1. Access token: 15 minutes, stateless
2. Refresh token: 7 days, stored in database
3. Refresh rotates both tokens
4. Logout revokes refresh token

---

## 5. Domain Events

- UserCreated
- UserDisabled
- UserLocked
- UserUnlocked
- PasswordChanged
- PasswordResetRequested
- PasswordResetCompleted
- LoginSucceeded
- LoginFailed
- UserGroupCreated
- UserGroupDeleted
- UserAddedToGroup
- UserRemovedFromGroup
- PermissionGranted
- PermissionRevoked

---

## 6. Public Contracts

### IdentityService

```
register(email, password, name) → User
login(email, password) → TokenPair
refresh(refreshToken) → TokenPair
logout(refreshToken) → void
getUser(userId) → User
updatePassword(userId, oldPassword, newPassword) → void
requestPasswordReset(email) → void
resetPassword(token, newPassword) → void
```

### AuthorizationService

```
hasPermission(userId, permissionKey) → boolean
getPermissions(userId) → Permission[]
getGroups(userId) → UserGroup[]
addToGroup(userId, groupId) → void
removeFromGroup(userId, groupId) → void
grantGroupPermission(groupId, permissionKey) → void
revokeGroupPermission(groupId, permissionKey) → void
```

### TokenService

```
generateTokenPair(user) → TokenPair
validateToken(token) → Claims
revokeRefreshToken(token) → void
revokeAllRefreshTokens(userId) → void
```

### UserGroupService

```
getGroup(groupId) → UserGroup
getGroupByName(name) → UserGroup
createGroup(data) → UserGroup
updateGroup(groupId, data) → UserGroup
deleteGroup(groupId) → void
getGroupMembers(groupId) → User[]
getGroupPermissions(groupId) → Permission[]
```

---

## 7. Anti-Patterns

### Business Logic in Identity

```
Identity Kernel
  schedules appointments
```

Appointment scheduling belongs to Consultation Business Context.

### Direct Database Access

```
Consult App
  SELECT * FROM identity.users
```

Cross-app data access is via API only.

### Hardcoded Permissions

```
if (user.role === 'ADMIN') { ... }
```

Use permission keys, not role names in business logic.

### App-Specific Groups

```
Identity Kernel
  creates "consult-admin" group
```

Groups are universal. App-specific usage is at the application layer.

---

## 8. Guiding Principle

The Identity Kernel is the single source of truth for:

- **Who** can authenticate
- **What groups** they belong to
- **What** they can access

It does not define:

- What users do after authentication
- Business workflows
- Domain-specific logic
