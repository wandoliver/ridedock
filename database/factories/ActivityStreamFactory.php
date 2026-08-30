<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityStream;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityStream> */
class ActivityStreamFactory extends Factory
{
    protected $model = ActivityStream::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'type' => 'heart_rate',
            'series_type' => 'time',
            'resolution' => 'high',
            'data' => [120, 123, 125],
            'metadata' => ['interval_seconds' => 1],
        ];
    }
}
