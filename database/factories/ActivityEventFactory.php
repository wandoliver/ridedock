<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityEvent> */
class ActivityEventFactory extends Factory
{
    protected $model = ActivityEvent::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'type' => 'shift',
            'elapsed_seconds' => fake()->numberBetween(0, 7200),
            'data' => ['front_gear' => 2, 'rear_gear' => 8],
        ];
    }
}
