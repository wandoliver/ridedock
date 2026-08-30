<?php

namespace Database\Factories;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAnalysis>
 */
class AiAnalysisFactory extends Factory
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
            'activity_id' => fn (array $attributes): string => Activity::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'type' => 'activity',
            'status' => AiAnalysisStatus::Pending,
            'provider' => null,
            'model' => null,
            'provider_response_id' => null,
            'context_schema_version' => 2,
            'prompt_version' => 'activity-v2',
            'context' => ['schema_version' => 2],
            'output' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
