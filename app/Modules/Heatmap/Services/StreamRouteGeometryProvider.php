<?php

namespace App\Modules\Heatmap\Services;

use App\Models\ActivityStream;
use App\Models\User;
use App\Modules\Heatmap\Contracts\RouteGeometryProvider;
use Carbon\CarbonImmutable;

final class StreamRouteGeometryProvider implements RouteGeometryProvider
{
    public function routes(
        User $user,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): iterable {
        $streams = ActivityStream::query()
            ->join('activities', 'activities.id', '=', 'activity_streams.activity_id')
            ->where('activities.user_id', $user->id)
            ->where('activity_streams.type', 'latlng')
            ->when($from, fn ($query) => $query->where('activities.started_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('activities.started_at', '<=', $to))
            ->orderBy('activities.started_at')
            ->orderBy('activity_streams.id')
            ->select([
                'activity_streams.id',
                'activity_streams.activity_id',
                'activity_streams.data',
                'activities.started_at as activity_started_at',
            ])
            ->cursor();

        $privacy = app(RoutePrivacy::class);
        foreach ($streams as $stream) {
            foreach ($privacy->segments($user, $stream->data, (float) config('heatmap.privacy_trim_m')) as $points) {
                if ($user->privacyZones->isEmpty()) {
                    $points = $this->samplePath($points, max(1, (int) config('heatmap.sample_every')));
                }
                yield [
                    'activity_id' => $stream->activity_id,
                    'started_at' => CarbonImmutable::parse((string) $stream->getAttribute('activity_started_at'))->toIso8601String(),
                    'point_count' => count($points),
                    'polyline' => $privacy->encode($points),
                ];
            }
        }
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    private function samplePath(array $points, int $sampleEvery): array
    {
        if ($sampleEvery === 1 || count($points) < 3) {
            return $points;
        }

        $sampled = [];

        foreach ($points as $index => $point) {
            if ($index % $sampleEvery === 0) {
                $sampled[] = $point;
            }
        }

        $lastPoint = $points[count($points) - 1];

        if ($sampled === [] || $sampled[count($sampled) - 1] !== $lastPoint) {
            $sampled[] = $lastPoint;
        }

        return $sampled;
    }
}
