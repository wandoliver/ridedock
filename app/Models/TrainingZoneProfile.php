<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $effective_at
 * @property int|null $ftp_watts
 * @property int|null $max_heart_rate
 */
#[Fillable(['user_id', 'effective_at', 'ftp_watts', 'max_heart_rate'])]
class TrainingZoneProfile extends Model
{
    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'ftp_watts' => 'integer', 'max_heart_rate' => 'integer'];
    }
}
