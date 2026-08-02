<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\RoutePolicy;
use Illuminate\Console\Command;

class ImportPermissions extends Command
{
    protected $signature = 'permissions:import {app} {--dry-run : Preview changes without saving}';

    protected $description = 'Import route policies from a permissions.json file for an app';

    public function handle(): int
    {
        $app = $this->argument('app');
        $dryRun = $this->option('dry-run');

        $filePath = app_path("Permissions/{$app}/permissions.json");

        if (!file_exists($filePath)) {
            $this->error("permissions.json not found for app: {$app}");
            return 1;
        }

        $data = json_decode(file_get_contents($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in permissions.json for app: {$app}");
            return 1;
        }

        if (!isset($data['app'], $data['version'], $data['routes'])) {
            $this->error("permissions.json must contain app, version, and routes keys");
            return 1;
        }

        if ($data['app'] !== $app) {
            $this->error("App mismatch: expected {$app}, got {$data['app']}");
            return 1;
        }

        $this->info("Importing permissions for app: {$app} (version: {$data['version']})");

        $existing = RoutePolicy::where('app', $app)->count();
        $this->line("Existing route policies for {$app}: {$existing}");

        $imported = 0;
        $skipped = 0;

        foreach ($data['routes'] as $route) {
            if (!isset($route['method'], $route['path'], $route['claims'])) {
                $this->warn("Skipping route with missing fields: " . json_encode($route));
                $skipped++;
                continue;
            }

            foreach ($route['claims'] as $claim) {
                if (!isset($claim['key'])) {
                    $this->warn("Skipping claim with missing key: " . json_encode($claim));
                    $skipped++;
                    continue;
                }

                $filter = $claim['filter'] ?? 'all';
                $precedence = $claim['precedence'] ?? 0;

                if (!$dryRun) {
                    Claim::firstOrCreate(
                        ['key' => $claim['key']],
                        ['description' => $claim['description'] ?? null]
                    );

                    RoutePolicy::updateOrCreate(
                        [
                            'app' => $app,
                            'method' => strtoupper($route['method']),
                            'path' => $route['path'],
                            'claim_key' => $claim['key'],
                        ],
                        [
                            'filter' => $filter,
                        ]
                    );
                }

                $imported++;
            }
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would import {$imported} claim-policy entries, skip {$skipped}");
        } else {
            $this->info("Imported {$imported} claim-policy entries, skipped {$skipped}");
        }

        return 0;
    }
}