<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ActivityEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $activity_id
 * @property string $type
 * @property int|null $elapsed_seconds
 * @property CarbonImmutable|null $recorded_at
 * @property array<string, mixed> $data
 * @property-read Activity $activity
 */
#[Fillable(['activity_id', 'type', 'elapsed_seconds', 'recorded_at', 'data'])]
class ActivityEvent extends Model
{
    /** @use HasFactory<ActivityEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime', 'data' => 'array'];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
