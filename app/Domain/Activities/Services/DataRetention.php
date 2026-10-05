<?php

namespace App\Domain\Activities\Services;

use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DataRetention
{
    public function activityExpired(User $user, CarbonImmutable $startedAt): bool
    {
        return $user->activity_retention_days !== null
            && $startedAt->lt(CarbonImmutable::now()->startOfSecond()->subDays($user->activity_retention_days));
    }

    public function sourceExpired(User $user, CarbonImmutable $startedAt): bool
    {
        return $user->source_retention_days !== null
            && $startedAt->lt(CarbonImmutable::now()->startOfSecond()->subDays($user->source_retention_days));
    }

    /** @return array{activities: int, sources: int, analyses: int} */
    public function preview(User $user): array
    {
        $activities = $this->expiredActivities($user)->count();
        $sources = ActivitySource::query()->whereHas('activity', fn ($query) => $query->where('user_id', $user->id))
            ->whereNull('purged_at')
            ->whereHas('activity', function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->whereRaw('1 = 0');
                    foreach ([$user->activity_retention_days, $user->source_retention_days] as $days) {
                        if ($days !== null) {
                            $query->orWhere('started_at', '<', CarbonImmutable::now()->startOfSecond()->subDays($days));
                        }
                    }
                });
            })->count();

        return [
            'activities' => $activities,
            'sources' => $sources,
            // Plans can contain summaries of removed rides, so all user analyses are invalidated.
            'analyses' => $activities > 0 ? $user->aiAnalyses()->count() : 0,
        ];
    }

    /** @return array{activities: int, sources: int, analyses: int} */
    public function purge(User $user, string $snapshot): array
    {
        return DB::transaction(function () use ($user, $snapshot): array {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->snapshot($user), $snapshot)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['confirm_cleanup' => __('privacy.preview_changed')]);
            }
            $counts = $this->preview($user);
            $activities = Activity::query()->where('user_id', $user->id)->orderBy('id')->cursor();
            foreach ($activities as $activity) {
                $expired = $this->activityExpired($user, $activity->started_at);
                if (! $expired && ! $this->sourceExpired($user, $activity->started_at)) {
                    continue;
                }
                foreach ($activity->sources as $source) {
                    $this->removeSource($source);
                }
                if ($expired) {
                    $activity->delete();
                }
            }
            if ($counts['activities'] > 0) {
                $user->aiAnalyses()->delete();
            }

            return $counts;
        });
    }

    public function snapshot(User $user): string
    {
        return hash('sha256', json_encode([$user->id, $user->activity_retention_days, $user->source_retention_days, $this->preview($user)], JSON_THROW_ON_ERROR));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Activity> */
    private function expiredActivities(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return Activity::query()->where('user_id', $user->id)
            ->when(
                $user->activity_retention_days !== null,
                fn ($query) => $query->where('started_at', '<', CarbonImmutable::now()->startOfSecond()->subDays($user->activity_retention_days)),
                fn ($query) => $query->whereRaw('1 = 0')
            );
    }

    private function removeSource(ActivitySource $source): void
    {
        if ($source->source_file_path) {
            $disk = Storage::disk(config('activity-providers.sync.source_file_disk'));
            if ($disk->exists($source->source_file_path) && ! $disk->delete($source->source_file_path)) {
                throw new RuntimeException('Unable to remove a private source file. Retry the cleanup.');
            }
        }
        $source->forceFill([
            'raw_payload' => null, 'source_file_path' => null, 'source_file_checksum' => null,
            'metadata' => null, 'purged_at' => now(),
        ])->save();
    }
}
