<?php

namespace App\Models;

use App\Domain\Training\Enums\AiAnalysisStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AiAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $activity_id
 * @property string $type
 * @property AiAnalysisStatus $status
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $provider_response_id
 * @property int $context_schema_version
 * @property string $prompt_version
 * @property array<string, mixed> $context
 * @property array<string, mixed>|null $output
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property-read User $user
 * @property-read Activity|null $activity
 */
#[Fillable([
    'user_id', 'activity_id', 'type', 'status', 'provider', 'model',
    'provider_response_id', 'context_schema_version', 'prompt_version', 'context',
    'output', 'input_tokens', 'output_tokens', 'error', 'started_at', 'completed_at',
])]
class AiAnalysis extends Model
{
    /** @use HasFactory<AiAnalysisFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => AiAnalysisStatus::class,
            'context' => 'array',
            'output' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
