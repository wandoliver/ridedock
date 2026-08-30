<?php

namespace App\Modules\Heatmap\Services;

use App\Models\ActivityStream;
use App\Models\User;
use App\Modules\Heatmap\Contracts\RouteGeometryProvider;
use Carbon\CarbonImmutable;

final class StreamRouteGeometryProvider implements RouteGeometryProvider
{
    private const EARTH_RADIUS_M = 6_371_000;

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

        foreach ($streams as $stream) {
            $points = $this->extractPoints($stream);
            $points = $this->trimPath($points, (float) config('heatmap.privacy_trim_m'));
            $points = $this->samplePath($points, max(1, (int) config('heatmap.sample_every')));

            if (count($points) < 2) {
                continue;
            }

            yield [
                'activity_id' => $stream->activity_id,
                'started_at' => CarbonImmutable::parse(
                    (string) $stream->getAttribute('activity_started_at'),
                )->toIso8601String(),
                'point_count' => count($points),
                'polyline' => $this->encodePolyline($points),
            ];
        }
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function extractPoints(ActivityStream $stream): array
    {
        $points = [];

        foreach ($stream->data as $sample) {
            $value = is_array($sample) && array_key_exists('value', $sample)
                ? $sample['value']
                : $sample;

            if (! is_array($value)
                || ! is_numeric($value[0] ?? null)
                || ! is_numeric($value[1] ?? null)) {
                continue;
            }

            $latitude = (float) $value[0];
            $longitude = (float) $value[1];

            if (! is_finite($latitude)
                || ! is_finite($longitude)
                || $latitude < -90
                || $latitude > 90
                || $longitude < -180
                || $longitude > 180) {
                continue;
            }

            $points[] = [$latitude, $longitude];
        }

        return $points;
    }

    /**
     * Ported from `opens/cycling_heatmap/geo.py`.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    private function trimPath(array $points, float $trimMetres): array
    {
        if ($trimMetres <= 0 || count($points) < 3) {
            return $points;
        }

        $startIndex = 0;
        $travelled = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $travelled += $this->haversineMetres($points[$index - 1], $points[$index]);

            if ($travelled >= $trimMetres) {
                $startIndex = $index;
                break;
            }
        }

        $endIndex = count($points);
        $travelled = 0.0;

        for ($index = count($points) - 1; $index > 0; $index--) {
            $travelled += $this->haversineMetres($points[$index], $points[$index - 1]);

            if ($travelled >= $trimMetres) {
                $endIndex = $index;
                break;
            }
        }

        if ($endIndex - $startIndex < 2) {
            return [];
        }

        return array_slice($points, $startIndex, $endIndex - $startIndex);
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

    /**
     * @param  array{0: float, 1: float}  $first
     * @param  array{0: float, 1: float}  $second
     */
    private function haversineMetres(array $first, array $second): float
    {
        $latitude1 = deg2rad($first[0]);
        $latitude2 = deg2rad($second[0]);
        $latitudeDelta = deg2rad($second[0] - $first[0]);
        $longitudeDelta = deg2rad($second[1] - $first[1]);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($latitude1) * cos($latitude2) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function encodePolyline(array $points): string
    {
        $encoded = '';
        $previousLatitude = 0;
        $previousLongitude = 0;

        foreach ($points as [$latitude, $longitude]) {
            $scaledLatitude = (int) round($latitude * 100_000);
            $scaledLongitude = (int) round($longitude * 100_000);
            $encoded .= $this->encodeSignedValue($scaledLatitude - $previousLatitude);
            $encoded .= $this->encodeSignedValue($scaledLongitude - $previousLongitude);
            $previousLatitude = $scaledLatitude;
            $previousLongitude = $scaledLongitude;
        }

        return $encoded;
    }

    private function encodeSignedValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : $value << 1;
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        return $encoded.chr($value + 63);
    }
}
