<?php

namespace Database\Factories;

use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderConnection> */
class ProviderConnectionFactory extends Factory
{
    protected $model = ProviderConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => ProviderName::Hammerhead,
            'external_user_id' => (string) fake()->unique()->numberBetween(1000, 9999999),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHours(6),
            'scopes' => ['activity:read'],
            'capabilities' => ['activities', 'activity_fit'],
            'status' => ConnectionStatus::Active,
        ];
    }

    public function hammerhead(): static
    {
        return $this->state(fn (): array => [
            'provider' => ProviderName::Hammerhead,
            'scopes' => ['activity:read'],
            'capabilities' => ['activities', 'activity_fit'],
        ]);
    }
}
