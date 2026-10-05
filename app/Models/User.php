<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $locale
 * @property int|null $activity_retention_days
 * @property int|null $source_retention_days
 * @property-read AthleteProfile|null $athleteProfile
 */
#[Fillable(['name', 'email', 'locale', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
