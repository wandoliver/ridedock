<?php

namespace App\Models;

use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use Carbon\CarbonImmutable;
use Database\Factories\ProviderConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $user_id
 * @property ProviderName $provider
 * @property string|null $external_user_id
 * @property string $access_token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 * @property list<string>|null $scopes
 * @property list<string>|null $capabilities
 * @property ConnectionStatus $status
 * @property string|null $sync_cursor
 * @property CarbonImmutable|null $last_synced_at
 * @property string|null $last_error
 * @property array<string, mixed>|null $metadata
 * @property-read User $user
 * @property-read Collection<int, ActivitySource> $sources
 */
#[Fillable([
    'user_id', 'provider', 'external_user_id', 'access_token', 'refresh_token',
    'token_expires_at', 'scopes', 'capabilities', 'status', 'sync_cursor',
    'last_synced_at', 'last_error', 'metadata',
])]
class ProviderConnection extends Model
{
    /** @use HasFactory<ProviderConnectionFactory> */
    use HasFactory;

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'provider' => ProviderName::class,
            'status' => ConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'scopes' => 'array',
            'capabilities' => 'array',
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
}
