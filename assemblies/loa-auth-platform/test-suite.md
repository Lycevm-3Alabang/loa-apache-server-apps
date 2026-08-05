# LOA Auth Platform — Test Suite Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Product Assembly (`loa-auth-platform`)
**Audience:** AI Development Agents

---

# 1. Purpose

Defines the automated test suite for the LOA Auth Platform. Covers unit tests for services, feature tests for API/web controllers, and integration tests for middleware.

Answers:

> **"How do we verify every service, endpoint, and middleware works correctly?"**

---

# 2. Test Framework

| Component | Choice | Version |
|-----------|--------|---------|
| Framework | PHPUnit | ^11.0 (already in composer.json) |
| Database | SQLite in-memory | via `RefreshDatabase` trait |
| Factories | Laravel model factories | custom per model |
| HTTP testing | Laravel `RefreshDatabase` + `actingAs` | built-in |

No Pest. PHPUnit only. One test class per service/controller.

---

# 3. Directory Structure

```
assemblies/loa-auth-platform/
├── phpunit.xml.dist                    # NEW
├── tests/
│   ├── TestCase.php                    # NEW - base test case
│   ├── CreatesApplication.php          # NEW - app bootstrap
│   ├── Unit/
│   │   ├── Services/
│   │   │   ├── JWTServiceTest.php
│   │   │   ├── EncryptionServiceTest.php
│   │   │   ├── IdentityServiceTest.php
│   │   │   ├── AuthorizationServiceTest.php
│   │   │   ├── TenantServiceTest.php
│   │   │   └── PasswordResetNotificationServiceTest.php
│   │   └── Models/
│   │       ├── UserTest.php
│   │       ├── RefreshTokenTest.php
│   │       ├── PasswordResetTokenTest.php
│   │       └── TenantTest.php
│   ├── Feature/
│   │   ├── Api/
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterTest.php
│   │   │   │   ├── LoginTest.php
│   │   │   │   ├── RefreshTest.php
│   │   │   │   ├── LogoutTest.php
│   │   │   │   ├── MeTest.php
│   │   │   │   ├── VerifyTest.php
│   │   │   │   ├── PasswordTest.php
│   │   │   │   └── ForgotPasswordTest.php
│   │   │   ├── Users/
│   │   │   │   ├── UserIndexTest.php
│   │   │   │   ├── UserShowTest.php
│   │   │   │   └── UserStatusTest.php
│   │   │   ├── Groups/
│   │   │   │   ├── GroupIndexTest.php
│   │   │   │   ├── GroupStoreTest.php
│   │   │   │   ├── GroupDestroyTest.php
│   │   │   │   ├── GroupPermissionsTest.php
│   │   │   │   └── GroupSyncPermissionsTest.php
│   │   │   └── UserGroups/
│   │   │       ├── UserGroupsIndexTest.php
│   │   │       ├── UserGroupAddTest.php
│   │   │       ├── UserGroupRemoveTest.php
│   │   │       ├── UserPermissionsIndexTest.php
│   │   │       ├── UserPermissionGrantTest.php
│   │   │       └── UserPermissionRevokeTest.php
│   │   └── Web/
│   │       ├── AdminMiddlewareTest.php
│   │       ├── AdminUsersTest.php
│   │       ├── AdminGroupsTest.php
│   │       └── AdminTenantsTest.php
│   └── Traits/
│       └── RefreshJwtSecret.php        # NEW - sets JWT_SECRET for tests
├── database/
│   └── factories/                      # NEW - model factories
│       ├── UserFactory.php
│       ├── TenantFactory.php
│       ├── UserGroupFactory.php
│       ├── PermissionFactory.php
│       ├── RefreshTokenFactory.php
│       ├── PasswordResetTokenFactory.php
│       └── LoginAttemptFactory.php
```

---

# 4. Infrastructure

## 4.1 phpunit.xml.dist

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="JWT_SECRET" value="test-secret-key-for-testing-only-32chars"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>
```

## 4.2 Base TestCase

```php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
```

## 4.3 CreatesApplication

```php
namespace Tests;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
}
```

---

# 5. Model Factories

## 5.1 UserFactory

```php
User::factory()->create([
    'email' => 'admin@lyceumalabang.edu.ph',
    'name' => 'Admin User',
    'status' => 'active',
]);
```

States: `active`, `disabled`, `locked`, `withPassword`

## 5.2 TenantFactory

Creates tenant with random UUID, slug, name, status=active.

## 5.3 UserGroupFactory

Creates group with name, description, optional tenant_id.

## 5.4 PermissionFactory

Creates permission with key, description, endpoint_pattern.

## 5.5 RefreshTokenFactory

Creates refresh token with hashed jti, user_id, expires_at.

## 5.6 PasswordResetTokenFactory

Creates token with hashed token, user_id, expires_at.

## 5.7 LoginAttemptFactory

Creates attempt with user_id, email, ip, success, attempted_at.

---

# 6. Unit Tests

## 6.1 JWTServiceTest

| Test | Asserts |
|------|---------|
| `testGenerateAccessToken` | Token is string, decodeable, has `type=access`, `sub`, `iat`, `exp` |
| `testGenerateRefreshToken` | Token is string, has `type=refresh`, `jti` |
| `testValidateReturnsClaims` | Valid token returns claims array |
| `testValidateReturnsNullForExpired` | Expired token returns null |
| `testValidateReturnsNullForBadSignature` | Tampered token returns null |
| `testValidateReturnsNullForMalformed` | Garbage string returns null |
| `testAccessTtlDefault` | Default TTL is 900 seconds |
| `testRefreshTtlDefault` | Default TTL is 604800 seconds |

## 6.2 EncryptionServiceTest

| Test | Asserts |
|------|---------|
| `testIsConfiguredFalseWhenNoKey` | Returns false with empty config |
| `testEncryptThrowsWhenNotConfigured` | RuntimeException |
| `testEncryptDecryptRoundTrip` | Decrypt(encrypt(data)) === data |
| `testDecryptReturnsNullForGarbage` | Returns null |
| `testDecryptWithPreviousKey` | Decrypt succeeds with previous key |
| `testDecryptReturnsNullWhenNoKey` | Returns null |
| `testKeyHexFormat` | 64-char hex decodes to 32 bytes |
| `testKeyBase64Format` | `base64:` prefix decodes to 32 bytes |
| `testKeyInvalidLengthThrows` | RuntimeException for wrong length |

## 6.3 IdentityServiceTest

| Test | Asserts |
|------|---------|
| `testRegisterCreatesUser` | User exists, password hashed, status=active |
| `testRegisterDuplicateEmailThrows` | Exception |
| `testLoginSuccess` | Returns access+refresh tokens |
| `testLoginInvalidCredentialsThrows` | Exception |
| `testLoginDisabledUserThrows` | Exception |
| `testLoginLockedUserThrows` | Exception |
| `testLoginRecordsAttempt` | LoginAttempt created |
| `testLoginIncrementsFailedAttempts` | failed_attempts increments |
| `testLoginLocksAfterFiveAttempts` | status=locked, locked_until set |
| `testRefreshSuccess` | Returns new token pair |
| `testRefreshInvalidTokenThrows` | Exception |
| `testRefreshRevokedTokenThrows` | Exception |
| `testRefreshReplacesPreviousToken` | Old token revoked, new valid |
| `testLogoutRevokesToken` | Token invalid after logout |
| `testSetUserStatusDisable` | Status=disabled, refresh tokens revoked |
| `testSetUserStatusEnable` | Status=active, lockout fields cleared |
| `testUpdatePasswordSuccess` | Password changed, old refresh tokens revoked |
| `testUpdatePasswordWrongOldThrows` | Exception |
| `testRequestPasswordResetReturnsTokenForValidEmail` | Token string returned |
| `testRequestPasswordResetReturnsNullForUnknownEmail` | Returns null |
| `testResetPasswordSuccess` | Password changed, token marked used |
| `testResetPasswordExpiredTokenThrows` | Exception |
| `testResetPasswordUsedTokenThrows` | Exception |

## 6.4 AuthorizationServiceTest

| Test | Asserts |
|------|---------|
| `testHasPermissionReturnsFalseForUnknownUser` | false |
| `testHasPermissionReturnsFalseForUnknownPermission` | false |
| `testHasPermissionTrueViaGroup` | User in group with granted=true |
| `testHasPermissionFalseViaGroupDeny` | User in group with granted=false |
| `testUserOverrideOverridesGroup` | Override wins over group grant |
| `testGetPermissionsReturnsGroupUnion` | Union of all group permissions |
| `testGetPermissionsDenyWins` | Denied key excluded even if granted elsewhere |
| `testGetPermissionsWithUserOverride` | Override applied on top |
| `testGetGroupsReturnsNames` | Array of group names |
| `testGetGroupsTenantScoped` | Only groups matching tenant |
| `testAddToGroupSuccess` | User in group after add |
| `testAddToGroupNonexistentUserThrows` | Exception |
| `testRemoveFromGroupSuccess` | User not in group after remove |
| `testGrantGroupPermissionSuccess` | Permission pivot created |
| `testRevokeGroupPermissionSuccess` | Permission pivot removed |
| `testTenantScopedPermission` | Grants scoped to tenant |

## 6.5 TenantServiceTest

| Test | Asserts |
|------|---------|
| `testCreateTenantSuccess` | Tenant exists in DB |
| `testCreateTenantInvalidSlugThrows` | Exception |
| `testCreateTenantDuplicateSlugThrows` | Exception |
| `testUpdateTenantSuccess` | Fields updated |
| `testUpdateTenantSlugImmutable` | Slug unchanged on update |
| `testSuspendTenant` | status=suspended |
| `testActivateTenant` | status=active |
| `testGetTenantNotFoundThrows` | Exception |
| `testGetTenantBySlugFound` | Returns tenant |
| `testGetTenantBySlugNotFound` | Returns null |
| `testResolveTenantByRedirectOrigin` | Matches origin to tenant |
| `testAddUserToTenantSuccess` | Pivot exists |
| `testRemoveUserFromTenantSuccess` | Pivot removed |
| `testIsMemberTrue` | Returns true |
| `testIsMemberFalse` | Returns false |
| `testNormalizeOrigin` | Strips path/fragment, lowercases |

## 6.6 Model Tests

### UserTest
| Test | Asserts |
|------|---------|
| `testBootGeneratesUuid` | id is UUID string |
| `testHiddenPassword` | password not in toArray |
| `testIsActive` | Returns true for active |
| `testIsLockedWhenFuture` | Returns true |
| `testIsLockedWhenExpired` | Auto-unlocks, returns false |
| `testInGroupTrue` | Returns true when member |
| `testInGroupFalse` | Returns false when not member |
| `testUserGroupsRelationship` | BelongsToMany |
| `testTenantsRelationship` | BelongsToMany |
| `testUserPermissionsRelationship` | BelongsToMany with pivot |

### RefreshTokenTest
| Test | Asserts |
|------|---------|
| `testIsExpiredTrue` | Returns true |
| `testIsExpiredFalse` | Returns false |
| `testIsRevokedTrue` | Returns true |
| `testIsRevokedFalse` | Returns false |
| `testIsValidTrue` | Not expired, not revoked |
| `testIsValidFalseExpired` | Returns false |
| `testIsValidFalseRevoked` | Returns false |

### PasswordResetTokenTest
| Test | Asserts |
|------|---------|
| `testIsExpiredTrue` | Returns true |
| `testIsUsedTrue` | Returns true |
| `testIsValidTrue` | Not expired, not used |

### TenantTest
| Test | Asserts |
|------|---------|
| `testIsActiveTrue` | Returns true |
| `testUsersRelationship` | BelongsToMany |
| `testUserGroupsRelationship` | HasMany |

---

# 7. Feature Tests

## 7.1 Auth API (8 test classes)

### RegisterTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testRegisterSuccess` | POST /api/v1/auth/register | 201, user created |
| `testRegisterDuplicateEmail` | POST | 409 |
| `testRegisterInvalidEmail` | POST | 422 |
| `testRegisterShortPassword` | POST | 422 |
| `testRegisterMissingName` | POST | 422 |

### LoginTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testLoginSuccess` | POST /api/v1/auth/login | 200, tokens returned |
| `testLoginInvalidCredentials` | POST | 401 |
| `testLoginDisabledAccount` | POST | 403 |
| `testLoginLockedAccount` | POST | 423 |

### RefreshTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testRefreshSuccess` | POST /api/v1/auth/refresh | 200, new tokens |
| `testRefreshInvalidToken` | POST | 401 |
| `testRefreshRevokedToken` | POST | 401 |

### LogoutTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testLogoutSuccess` | POST /api/v1/auth/logout | 204, token revoked |
| `testLogoutInvalidToken` | POST | 401 |

### MeTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testMeSuccess` | GET /api/v1/auth/me | 200, user data |
| `testMeNoToken` | GET | 401 |
| `testMeExpiredToken` | GET | 401 |

### VerifyTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testVerifyValidToken` | GET /api/v1/auth/verify | 200 |
| `testVerifyInvalidToken` | GET | 401 |

### PasswordTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testUpdatePasswordSuccess` | PUT /api/v1/auth/password | 200 |
| `testUpdatePasswordWrongOld` | PUT | 401 |

### ForgotPasswordTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testForgotPasswordSuccess` | POST /api/v1/auth/password/forgot | 200 |
| `testResetPasswordSuccess` | POST /api/v1/auth/password/reset | 200 |

## 7.2 Users API (3 test classes)

### UserIndexTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testIndexSuccess` | GET /api/v1/users | 200, paginated |
| `testIndexRequiresPermission` | GET | 403 |

### UserShowTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testShowSuccess` | GET /api/v1/users/{id} | 200 |
| `testShowNotFound` | GET | 404 |

### UserStatusTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testUpdateStatusSuccess` | PATCH /api/v1/users/{id}/status | 200 |
| `testUpdateStatusInvalid` | PATCH | 422 |

## 7.3 Groups API (5 test classes)

### GroupIndexTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testIndexReturnsGroups` | GET /api/v1/groups | 200, data array |
| `testIndexFilterByTenant` | GET ?tenant_id=X | 200, filtered |
| `testIndexFilterByNullTenant` | GET ?tenant_id=null | 200, global only |
| `testIndexRequiresPermission` | GET | 403 |

### GroupStoreTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testStoreSuccess` | POST /api/v1/groups | 201 |
| `testStoreDuplicateName` | POST | 409 |
| `testStoreInvalidData` | POST | 422 |

### GroupDestroyTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testDestroySuccess` | DELETE /api/v1/groups/{id} | 204 |
| `testDestroyNotFound` | DELETE | 404 |
| `testDestroyDetachesMembers` | DELETE | Pivot empty |

### GroupPermissionsTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testShowPermissionsSuccess` | GET /api/v1/groups/{id}/permissions | 200 |
| `testShowPermissionsNotFound` | GET | 404 |

### GroupSyncPermissionsTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testSyncPermissionsGrant` | POST /api/v1/groups/{id}/permissions | 200 |
| `testSyncPermissionsRevoke` | POST | 200 |
| `testSyncPermissionsInvalidKey` | POST | 422 |

## 7.4 UserGroups API (6 test classes)

### UserGroupsIndexTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testIndexSuccess` | GET /api/v1/users/{id}/groups | 200 |
| `testIndexUserNotFound` | GET | 404 |

### UserGroupAddTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testAddSuccess` | POST /api/v1/users/{id}/groups | 201 |
| `testAddDuplicate` | POST | 409 |
| `testAddUserNotFound` | POST | 404 |

### UserGroupRemoveTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testRemoveSuccess` | DELETE /api/v1/users/{id}/groups/{gid} | 204 |
| `testRemoveUserNotFound` | DELETE | 404 |

### UserPermissionsIndexTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testIndexSuccess` | GET /api/v1/users/{id}/permissions | 200, has groups/permissions/overrides |

### UserPermissionGrantTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testGrantSuccess` | POST /api/v1/users/{id}/permissions | 200 |
| `testGrantInvalidKey` | POST | 422 |

### UserPermissionRevokeTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testRevokeSuccess` | DELETE /api/v1/users/{id}/permissions/{key} | 204 |
| `testRevokeNotFound` | DELETE | 404 |

## 7.5 Web Admin (3 test classes)

### AdminMiddlewareTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testNonAdminRedirectsToLogin` | GET /admin/users | 302 → login |
| `testNonAdminGroupAborts403` | GET /admin/users | 403 |

### AdminUsersTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testIndexSuccess` | GET /admin/users | 200 |
| `testShowUserSuccess` | GET /admin/users/{id} | 200 |
| `testStoreUserSuccess` | POST /admin/users | 302 |
| `testUpdateStatusSuccess` | POST /admin/users/{id}/status | 302 |
| `testSelfDisableForbidden` | POST | Error flash |

### AdminGroupsTest
| Test | HTTP | Asserts |
|------|------|---------|
| `testGroupsIndex` | GET /admin/groups | 200 |
| `testGroupsShow` | GET /admin/groups/{id} | 200 |
| `testGroupsStore` | POST /admin/groups | 302 |
| `testGroupsPermissions` | POST /admin/groups/{id}/permissions | 302 |
| `testGroupsMembersAdd` | POST /admin/groups/{id}/members | 302 |
| `testGroupsMembersRemove` | POST /admin/groups/{id}/members/{uid}/remove | 302 |

---

# 8. Test Traits

## 8.1 RefreshJwtSecret

Sets `JWT_SECRET` in config before each test to ensure consistent token generation.

```php
trait RefreshJwtSecret
{
    protected function setJwtSecret(string $secret = null): void
    {
        config(['jwt.secret' => $secret ?? 'test-secret-key-for-testing-only-32chars']);
    }
}
```

---

# 9. Execution Order

1. Run unit tests first: `vendor/bin/phpunit --testsuite=Unit`
2. Run feature tests second: `vendor/bin/phpunit --testsuite=Feature`
3. Full suite: `vendor/bin/phpunit`

---

# 10. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Testing implementation details | Brittle, breaks on refactor | Test behavior and output |
| Using real database | Slow, stateful | SQLite in-memory |
| Skipping teardown | Test pollution | `RefreshDatabase` trait |
| Testing framework code | Wastes time | Test our code only |
| One giant test class | Unmaintainable | One class per service/controller |
