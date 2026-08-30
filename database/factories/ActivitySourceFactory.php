<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ActivitySource> */
class ActivitySourceFactory extends Factory
{
    protected $model = ActivitySource::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'provider_connection_id' => ProviderConnection::factory(),
            'external_id' => (string) Str::uuid(),
            'raw_payload' => [],
            'imported_at' => now(),
        ];
    }
}
