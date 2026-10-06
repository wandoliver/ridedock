<?php

namespace App\Models;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property UserStatus $status
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property-read UserInvitation|null $latestInvitation
 * @property Carbon|null $last_active_at
 * @property string $locale
 * @property int|null $activity_retention_days
 * @property int|null $source_retention_days
 * @property int|null $ai_activity_quota
 * @property int|null $ai_plan_quota
 * @property-read AthleteProfile|null $athleteProfile
 */
#[Fillable(['name', 'email', 'locale', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function aiQuota(bool $activity): int
    {
        return max(0, $activity
            ? ($this->ai_activity_quota ?? (int) config('operations.ai.monthly_activity_quota'))
            : ($this->ai_plan_quota ?? (int) config('operations.ai.monthly_plan_quota')));
    }
    /** @var array<string, mixed> */
    protected $attributes = ['role' => 'member', 'status' => 'active'];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @return HasOne<UserInvitation, $this> */
    public function latestInvitation(): HasOne
    {
        return $this->hasOne(UserInvitation::class)->latestOfMany();
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<PrivacyZone, $this> */
    public function privacyZones(): HasMany
    {
        return $this->hasMany(PrivacyZone::class);
    }

    /** @return HasMany<ProviderConnection, $this> */
    public function providerConnections(): HasMany
    {
        return $this->hasMany(ProviderConnection::class);
    }

    /** @return HasOne<AthleteProfile, $this> */
    public function athleteProfile(): HasOne
    {
        return $this->hasOne(AthleteProfile::class);
    }

    /** @return HasMany<TrainingZoneProfile, $this> */
    public function trainingZoneProfiles(): HasMany
    {
        return $this->hasMany(TrainingZoneProfile::class);
    }

    /** @return HasMany<AiAnalysis, $this> */
    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_retention_days' => 'integer',
            'source_retention_days' => 'integer',
            'ai_activity_quota' => 'integer',
            'ai_plan_quota' => 'integer',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }
}
