<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property int $user_id
 * @property string $deduplication_key
 * @property string $name
 * @property string $type
 * @property CarbonImmutable $started_at
 * @property string|null $timezone
 * @property float|null $distance_m
 * @property int|null $moving_time_s
 * @property int|null $elapsed_time_s
 * @property float|null $elevation_gain_m
 * @property float|null $average_speed_mps
 * @property float|null $max_speed_mps
 * @property int|null $average_heart_rate
 * @property int|null $max_heart_rate
 * @property float|null $average_cadence
 * @property float|null $max_cadence
 * @property float|null $average_power
 * @property float|null $weighted_average_power
 * @property float|null $max_power
 * @property float|null $energy_kj
 * @property int|null $rpe
 * @property string|null $notes
 * @property string|null $summary_polyline
 * @property array<string, mixed>|null $metadata
 * @property-read User $user
 * @property-read Collection<int, ActivitySource> $sources
 * @property-read Collection<int, ActivityStream> $streams
 * @property-read Collection<int, ActivityEvent> $events
 * @property-read Collection<int, AiAnalysis> $aiAnalyses
 * @property-read AiAnalysis|null $latestAiAnalysis
 */
#[Fillable([
    'user_id', 'deduplication_key', 'name', 'type', 'started_at', 'timezone',
    'distance_m', 'moving_time_s', 'elapsed_time_s', 'elevation_gain_m',
    'average_speed_mps', 'max_speed_mps', 'average_heart_rate', 'max_heart_rate',
    'average_cadence', 'max_cadence', 'average_power', 'weighted_average_power',
    'max_power', 'energy_kj', 'rpe', 'notes', 'summary_polyline', 'metadata',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'distance_m' => 'float',
            'elevation_gain_m' => 'float',
            'average_speed_mps' => 'float',
            'max_speed_mps' => 'float',
            'average_cadence' => 'float',
            'max_cadence' => 'float',
            'average_power' => 'float',
            'weighted_average_power' => 'float',
            'max_power' => 'float',
            'energy_kj' => 'float',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ActivitySource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(ActivitySource::class);
    }

    /** @return HasMany<ActivityStream, $this> */
    public function streams(): HasMany
    {
        return $this->hasMany(ActivityStream::class);
    }

    /** @return HasMany<ActivityEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    /** @return HasMany<AiAnalysis, $this> */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /** @return HasOne<AiAnalysis, $this> */
    public function latestAiAnalysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class)->latestOfMany();
    }
}
