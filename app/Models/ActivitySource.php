<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ActivitySourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $activity_id
 * @property int $provider_connection_id
 * @property string $external_id
 * @property array<string, mixed>|null $raw_payload
 * @property string|null $source_file_path
 * @property string|null $source_file_checksum
 * @property CarbonImmutable|null $external_updated_at
 * @property CarbonImmutable $imported_at
 * @property CarbonImmutable|null $deleted_at_provider
 * @property CarbonImmutable|null $purged_at
 * @property array<string, mixed>|null $metadata
 * @property-read Activity $activity
 * @property-read ProviderConnection $connection
 */
#[Fillable([
    'activity_id', 'provider_connection_id', 'external_id', 'raw_payload',
    'source_file_path', 'source_file_checksum', 'external_updated_at',
    'imported_at', 'deleted_at_provider', 'metadata',
])]
class ActivitySource extends Model
{
    /** @use HasFactory<ActivitySourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'purged_at' => 'immutable_datetime',
            'raw_payload' => 'array',
            'metadata' => 'array',
            'external_updated_at' => 'immutable_datetime',
            'imported_at' => 'immutable_datetime',
            'deleted_at_provider' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<ProviderConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }
}
