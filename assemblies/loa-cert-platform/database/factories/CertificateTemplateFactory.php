<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'css_content' => null,
            'visibility' => CertificateTemplate::VISIBILITY_PUBLIC,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);
    }

    public function ownedBy(string $sub): static
    {
        return $this->state(fn () => [
            'created_by' => $sub,
            'updated_by' => $sub,
        ]);
    }
}
