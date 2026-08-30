<?php

namespace App\Models;

use Database\Factories\ActivityStreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $activity_id
 * @property string $type
 * @property string $series_type
 * @property string|null $resolution
 * @property array<int, mixed> $data
 * @property array<string, mixed>|null $metadata
 * @property-read Activity $activity
 */
#[Fillable(['activity_id', 'type', 'series_type', 'resolution', 'data', 'metadata'])]
class ActivityStream extends Model
{
    /** @use HasFactory<ActivityStreamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['data' => 'array', 'metadata' => 'array'];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
