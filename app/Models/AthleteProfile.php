<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AthleteProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $goal
 * @property CarbonImmutable|null $goal_date
 * @property string|null $experience_level
 * @property float|null $weekly_hours
 * @property list<string>|null $available_days
 * @property int|null $ftp_watts
 * @property int|null $max_heart_rate
 * @property string|null $constraints
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'goal', 'goal_date', 'experience_level', 'weekly_hours',
    'available_days', 'ftp_watts', 'max_heart_rate', 'constraints',
])]
class AthleteProfile extends Model
{
    /** @use HasFactory<AthleteProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'goal_date' => 'immutable_date',
            'weekly_hours' => 'float',
            'available_days' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
