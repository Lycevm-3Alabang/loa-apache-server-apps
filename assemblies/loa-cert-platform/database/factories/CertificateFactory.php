<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'recipient_name' => $this->faker->name(),
            'recipient_email' => $this->faker->unique()->safeEmail(),
            'certificate_number' => 'CERT-' . $this->faker->unique()->numberBetween(1000, 99999),
        ];
    }
}
