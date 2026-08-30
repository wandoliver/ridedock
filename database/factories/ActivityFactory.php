<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'deduplication_key' => hash('sha256', (string) Str::uuid()),
            'name' => fake()->words(3, true),
            'type' => 'ride',
            'started_at' => fake()->dateTimeBetween('-3 months'),
            'timezone' => 'Europe/Rome',
            'distance_m' => fake()->randomFloat(3, 10000, 150000),
            'moving_time_s' => fake()->numberBetween(1800, 18000),
            'elapsed_time_s' => fake()->numberBetween(18000, 20000),
            'elevation_gain_m' => fake()->randomFloat(2, 0, 2500),
            'average_heart_rate' => fake()->numberBetween(100, 170),
            'rpe' => fake()->numberBetween(1, 10),
        ];
    }
}
