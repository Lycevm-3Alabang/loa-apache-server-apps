<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Organization::updateOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'LOA E-Cert Platform', 'slug' => 'loa-e-cert']
        );
    }
}
