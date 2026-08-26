<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * unified-auth-flow.md §0 D7 / §12 P3: AUTH_ALLOWED_REDIRECTS is retired, so
 * every legacy allowlisted origin must be reachable as an active tenant row.
 *
 * Provisioning philosophy (decision 2026-08-06): tenant data is provisioned
 * manually per environment, never baked into test runs — hence the testing
 * guard keeps feature tests hermetic (they create their own tenants).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard is deliberately redundant: host APP_ENV can leak into the
        // test process when docker-compose exports APP_ENV=local, so also
        // treat the RefreshDatabase sqlite/:memory: connection as "testing".
        if ($this->inTestingContext()) {
            return;
        }

        $legacy = [
            ['slug' => 'aces-api', 'name' => 'ACES Platform', 'origin' => 'https://aces-api.lyceumalabang.edu.ph'],
            ['slug' => 'e-cert', 'name' => 'E-Cert Platform', 'origin' => 'https://e-cert.vercel.app'],
        ];

        foreach ($legacy as $entry) {
            if ($this->originCovered($entry['origin'])) {
                continue;
            }

            $slug = $this->freeSlug($entry['slug']);

            Tenant::create([
                'slug' => $slug,
                'name' => $entry['name'],
                'status' => 'active',
                'app_url' => $entry['origin'],
                'redirect_origins' => [$entry['origin']],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->inTestingContext()) {
            return;
        }

        // Only remove rows this migration plausibly created (exact slugs or
        // collision-suffixed variants); never touch pre-existing tenants.
        Tenant::where('slug', 'aces-api')->delete();
        Tenant::where('slug', 'e-cert')->delete();
        Tenant::where('slug', 'like', 'aces-api-%')
            ->where('app_url', 'https://aces-api.lyceumalabang.edu.ph')
            ->delete();
        Tenant::where('slug', 'like', 'e-cert-%')
            ->where('app_url', 'https://e-cert.vercel.app')
            ->delete();
    }

    private function inTestingContext(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $connection = Schema::getConnection();

        return $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';
    }

    private function originCovered(string $origin): bool
    {
        return Tenant::where('status', 'active')
            ->get()
            ->contains(fn (Tenant $tenant) => in_array(
                $origin,
                $tenant->effectiveRedirectOrigins(),
                true,
            ));
    }

    private function freeSlug(string $base): string
    {
        $slug = $base;
        $attempt = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$attempt);
        }

        return Str::lower($slug);
    }
};
