<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'event_date' => $this->faker->date(),
            'location' => $this->faker->city(),
            'organizer' => $this->faker->company(),
            'certificate_title' => 'Certificate of Participation',
            'certificate_number_pattern' => 'CERT-####',
            'valid_until' => null,
            'status' => 'draft',
        ];
    }
}
