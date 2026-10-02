<?php

namespace App\Domain\Training\Services;

use App\Models\Activity;
use App\Models\TrainingZoneProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TrainingZones
{
    public function at(User $user, CarbonImmutable $at): ?TrainingZoneProfile
    {
        return $user->trainingZoneProfiles()->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')->orderByDesc('id')->first();
    }

    public function forActivity(Activity $activity): ?TrainingZoneProfile
    {
        return TrainingZoneProfile::query()->where('user_id', $activity->user_id)
            ->where('effective_at', '<=', $activity->started_at)->orderByDesc('effective_at')->orderByDesc('id')->first();
    }

    public function record(User $user, CarbonImmutable $effectiveAt, ?int $ftp, ?int $maxHr): TrainingZoneProfile
    {
        return DB::transaction(function () use ($user, $effectiveAt, $ftp, $maxHr): TrainingZoneProfile {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $snapshot = $user->trainingZoneProfiles()->create([
                'effective_at' => $effectiveAt, 'ftp_watts' => $ftp, 'max_heart_rate' => $maxHr,
            ]);
            $current = $this->at($user, CarbonImmutable::now());
            $user->athleteProfile()->updateOrCreate(['user_id' => $user->id], [
                'ftp_watts' => $current?->ftp_watts, 'max_heart_rate' => $current?->max_heart_rate,
            ]);
            return $snapshot;
        });
    }

    /** @return list<array{label: string, lower: int, upper: int|null}> */
    public function ranges(?TrainingZoneProfile $profile, string $sensor): array
    {
        $reference = $sensor === 'power' ? $profile?->ftp_watts : $profile?->max_heart_rate;
        if ($reference === null || $reference <= 0) {
            return [];
        }
        // Continuous boundaries: no gaps at rounded percentage transitions.
        $boundaries = $sensor === 'power' ? [0, .55, .75, .90, 1.05, 1.20] : [0, .50, .60, .70, .80, .90, 1.00];
        $ranges = [];
        foreach ($boundaries as $index => $ratio) {
            $ranges[] = [
                'label' => $sensor === 'power' ? 'P'.($index + 1) : ($index === 0 ? 'HR<50' : ($index === 6 ? 'HR>100' : 'H'.$index)),
                'lower' => $index === 0 ? 0 : (int) ceil($reference * $ratio),
                'upper' => isset($boundaries[$index + 1]) ? (int) ceil($reference * $boundaries[$index + 1]) - 1 : null,
            ];
        }
        return $ranges;
    }

    /** @return array<string, mixed> */
    public function context(?TrainingZoneProfile $profile): array
    {
        return [
            'effective_at' => $profile?->effective_at->toIso8601String(),
            'ftp_watts' => $profile?->ftp_watts, 'max_heart_rate' => $profile?->max_heart_rate,
            'power_ranges' => $this->ranges($profile, 'power'), 'heart_rate_ranges' => $this->ranges($profile, 'heart_rate'),
            'method' => 'six FTP bands (<55/75/90/105/120%, >=120%); five HRmax bands plus below/above range',
        ];
    }
}
