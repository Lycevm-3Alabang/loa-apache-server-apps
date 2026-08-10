<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventAttendeeFactory extends Factory
{
    protected $model = EventAttendee::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'organization_id' => Organization::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'attended' => false,
            'completed' => false,
        ];
    }
}
