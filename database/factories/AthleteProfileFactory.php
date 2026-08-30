<?php

namespace Database\Factories;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AthleteProfile>
 */
class AthleteProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'goal' => 'Ausdauer verbessern',
            'goal_date' => now()->addMonths(3)->toDateString(),
            'experience_level' => 'intermediate',
            'weekly_hours' => 7.5,
            'available_days' => ['monday', 'wednesday', 'saturday', 'sunday'],
            'ftp_watts' => 240,
            'max_heart_rate' => 185,
            'constraints' => null,
        ];
    }
}
