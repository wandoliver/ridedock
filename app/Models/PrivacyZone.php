<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_m
 */
#[Fillable(['name', 'latitude', 'longitude', 'radius_m'])]
class PrivacyZone extends Model
{
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius_m' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
