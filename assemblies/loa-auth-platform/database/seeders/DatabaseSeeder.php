<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (!app()->environment('production')) {
            $this->call(LocalCertReadinessSeeder::class);
        }
    }
}