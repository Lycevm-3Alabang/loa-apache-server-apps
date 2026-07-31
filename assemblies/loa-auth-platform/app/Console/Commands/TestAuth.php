<?php

namespace App\Console\Commands;

use App\Services\IdentityService;
use App\Services\JWTService;
use Illuminate\Console\Command;

class TestAuth extends Command
{
    protected $signature = 'auth:test';
    protected $description = 'Run full auth flow test';

    public function handle(): int
    {
        $identity = app(IdentityService::class);
        $jwt = app(JWTService::class);

        // 1. Register
        $this->line("=== REGISTER ===");
        try {
            $user = $identity->register("test2@loa.edu.ph", "Test1234", "Test User 2");
            $this->info("OK: {$user->id} {$user->email}");
        } catch (\Exception $e) {
            $this->error("FAIL: {$e->getMessage()}");
            return 1;
        }

        // 2. Login
        $this->line("\n=== LOGIN ===");
        try {
            $tokens = $identity->login("test2@loa.edu.ph", "Test1234", "127.0.0.1");
            $this->info("OK: access=" . substr($tokens["access_token"], 0, 30) . "...");
        } catch (\Exception $e) {
            $this->error("FAIL: {$e->getMessage()}");
            return 1;
        }

        // 3. Verify token
        $this->line("\n=== VERIFY ===");
        $claims = $jwt->validate($tokens["access_token"]);
        $this->info("OK: sub={$claims['sub']} email={$claims['email']}");

        // 4. Refresh
        $this->line("\n=== REFRESH ===");
        try {
            $new = $identity->refresh($tokens["refresh_token"]);
            $this->info("OK: new_access=" . substr($new["access_token"], 0, 30) . "...");
        } catch (\Exception $e) {
            $this->error("FAIL: {$e->getMessage()}");
            return 1;
        }

        // 5. Old refresh token should fail
        $this->line("\n=== OLD REFRESH (should fail) ===");
        try {
            $identity->refresh($tokens["refresh_token"]);
            $this->error("FAIL: old token still works!");
            return 1;
        } catch (\Exception $e) {
            $this->info("OK: {$e->getMessage()}");
        }

        // 6. Disable user
        $this->line("\n=== DISABLE ===");
        $identity->setUserStatus($user->id, "disabled");
        $this->info("OK: user disabled");

        // 7. Login after disable (should fail)
        $this->line("\n=== LOGIN DISABLED (should fail) ===");
        try {
            $identity->login("test2@loa.edu.ph", "Test1234", "127.0.0.1");
            $this->error("FAIL: disabled user logged in!");
            return 1;
        } catch (\Exception $e) {
            $this->info("OK: {$e->getMessage()}");
        }

        // 8. Re-enable
        $identity->setUserStatus($user->id, "active");
        $this->info("\nOK: user re-enabled");

        // 9. Login after re-enable
        $this->line("\n=== LOGIN RE-ENABLED ===");
        try {
            $tokens2 = $identity->login("test2@loa.edu.ph", "Test1234", "127.0.0.1");
            $this->info("OK: " . substr($tokens2["access_token"], 0, 30) . "...");
        } catch (\Exception $e) {
            $this->error("FAIL: {$e->getMessage()}");
            return 1;
        }

        // 10. Logout
        $this->line("\n=== LOGOUT ===");
        $identity->logout($tokens2["refresh_token"]);
        $this->info("OK: logged out");

        // 11. Refresh after logout (should fail)
        $this->line("\n=== REFRESH AFTER LOGOUT (should fail) ===");
        try {
            $identity->refresh($tokens2["refresh_token"]);
            $this->error("FAIL: refreshed after logout!");
            return 1;
        } catch (\Exception $e) {
            $this->info("OK: {$e->getMessage()}");
        }

        // 12. Password change
        $this->line("\n=== PASSWORD CHANGE ===");
        $tokens3 = $identity->login("test2@loa.edu.ph", "Test1234", "127.0.0.1");
        $identity->updatePassword($user->id, "Test1234", "NewPass123");
        try {
            $identity->refresh($tokens3["refresh_token"]);
            $this->error("FAIL: refresh after password change!");
            return 1;
        } catch (\Exception $e) {
            $this->info("OK: {$e->getMessage()}");
        }

        // 13. Login with new password
        $this->line("\n=== LOGIN NEW PASSWORD ===");
        try {
            $identity->login("test2@loa.edu.ph", "NewPass123", "127.0.0.1");
            $this->info("OK: login with new password");
        } catch (\Exception $e) {
            $this->error("FAIL: {$e->getMessage()}");
            return 1;
        }

        $this->line("\n========== ALL TESTS PASSED ==========");
        return 0;
    }
}
