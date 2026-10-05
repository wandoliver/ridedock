<?php

namespace App\Modules\Heatmap\Services;

use App\Models\Activity;
use App\Models\User;

final class RoutePrivacy
{
    private const EARTH_RADIUS_M = 6_371_000;

    /** @return list<list<array{0: float, 1: float}>> */
    public function activitySegments(Activity $activity): array
    {
        $stream = $activity->streams->firstWhere('type', 'latlng');
        $samples = $stream ? $stream->data : $this->decode($activity->summary_polyline ?? '');

        return $this->segments($activity->user, $samples);
    }

    /**
     * @param array<int, mixed> $samples
     * @return list<list<array{0: float, 1: float}>>
     */
    public function segments(User $user, array $samples, float $trimMetres = 0): array
    {
        $zones = $user->privacyZones;
        $paths = [];
        $path = [];
        foreach ($samples as $sample) {
            $point = is_array($sample) && array_key_exists('value', $sample) ? $sample['value'] : $sample;
            if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)
                || ! is_finite((float) $point[0]) || ! is_finite((float) $point[1])
                || abs((float) $point[0]) > 90 || abs((float) $point[1]) > 180) {
                if ($path !== []) {
                    $paths[] = $path;
                    $path = [];
                }
                continue;
            }
            $path[] = [(float) $point[0], (float) $point[1]];
        }
        if ($path !== []) {
            $paths[] = $path;
        }
        $segments = [];
        foreach ($paths as $path) {
            $path = $this->trim($path, $trimMetres);
            $segment = [];
            foreach ($path as $point) {
                $hidden = false;
                $crossing = false;
                foreach ($zones as $zone) {
                    $center = [$zone->latitude, $zone->longitude];
                    // Conservative buffer covers polyline rounding and floating-point boundaries.
                    $radius = $zone->radius_m + 10;
                    if ($this->distance($center, $point) <= $radius) {
                        $hidden = true;
                    }
                    if ($segment !== [] && ($this->edgeDistance($center, $segment[count($segment) - 1], $point) <= $radius
                        || $this->mapEdgeIntersects($center, $segment[count($segment) - 1], $point, $radius))) {
                        $crossing = true;
                    }
                }
                if ($hidden || $crossing) {
                    if (count($segment) >= 2) {
                        $segments[] = $segment;
                    }
                    $segment = [];
                }
                if (! $hidden) {
                    $segment[] = $point;
                }
            }
            if (count($segment) >= 2) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /** @param list<array{0: float, 1: float}> $points */
    public function encode(array $points): string
    {
        $encoded = '';
        $previous = [0, 0];
        foreach ($points as $point) {
            foreach ([0, 1] as $axis) {
                $scaled = (int) round($point[$axis] * 100_000);
                $value = $scaled - $previous[$axis];
                $previous[$axis] = $scaled;
                $value = $value < 0 ? ~($value << 1) : $value << 1;
                while ($value >= 0x20) {
                    $encoded .= chr((0x20 | ($value & 0x1F)) + 63);
                    $value >>= 5;
                }
                $encoded .= chr($value + 63);
            }
        }

        return $encoded;
    }

    /** @return list<array{0: float, 1: float}> */
    public function decode(string $encoded): array
    {
        $points = [];
        $position = 0;
        $previous = [0, 0];
        while ($position < strlen($encoded)) {
            foreach ([0, 1] as $axis) {
                $value = 0;
                $shift = 0;
                do {
                    if ($position >= strlen($encoded) || $shift > 30) {
                        return [];
                    }
                    $byte = ord($encoded[$position++]) - 63;
                    if ($byte < 0 || $byte > 63) {
                        return [];
                    }
                    $value |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20);
                $previous[$axis] += ($value & 1) ? ~($value >> 1) : ($value >> 1);
            }
            $points[] = [(float) ($previous[0] / 100_000), (float) ($previous[1] / 100_000)];
        }

        return $points;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<array{0: float, 1: float}>
     */
    private function trim(array $points, float $metres): array
    {
        if ($metres <= 0) {
            return $points;
        }
        $start = count($points);
        $end = 0;
        $distance = 0.0;
        for ($index = 1; $index < count($points); $index++) {
            $distance += $this->distance($points[$index - 1], $points[$index]);
            if ($distance >= $metres) {
                $start = $index;
                break;
            }
        }
        $distance = 0.0;
        for ($index = count($points) - 1; $index > 0; $index--) {
            $distance += $this->distance($points[$index], $points[$index - 1]);
            if ($distance >= $metres) {
                $end = $index;
                break;
            }
        }

        return $end - $start >= 2 ? array_slice($points, $start, $end - $start) : [];
    }

    /**
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private function distance(array $a, array $b): float
    {
        $value = sin(deg2rad($b[0] - $a[0]) / 2) ** 2
            + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin(deg2rad($b[1] - $a[1]) / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(sqrt(max(0, min(1, $value))));
    }

    /**
     * Minimum distance to the shorter great-circle arc, including its endpoints.
     * @param array{0: float, 1: float} $center
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private function edgeDistance(array $center, array $a, array $b): float
    {
        $length = $this->distance($a, $b) / self::EARTH_RADIUS_M;
        $distance = $this->distance($a, $center) / self::EARTH_RADIUS_M;
        if ($length < 1e-12) {
            return $distance * self::EARTH_RADIUS_M;
        }
        // Antipodal edges have no unique arc; suppress rather than expose them.
        if ($length > M_PI - 1e-6) {
            return 0;
        }
        $angle = $this->bearing($a, $center) - $this->bearing($a, $b);
        $along = atan2(sin($distance) * cos($angle), cos($distance));
        if ($along >= 0 && $along <= $length) {
            return abs(asin(max(-1, min(1, sin($distance) * sin($angle))))) * self::EARTH_RADIUS_M;
        }

        return min($this->distance($a, $center), $this->distance($b, $center));
    }

    /**
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private function bearing(array $a, array $b): float
    {
        $delta = deg2rad($b[1] - $a[1]);

        return atan2(
            sin($delta) * cos(deg2rad($b[0])),
            cos(deg2rad($a[0])) * sin(deg2rad($b[0])) - sin(deg2rad($a[0])) * cos(deg2rad($b[0])) * cos($delta)
        );
    }

    /**
     * Leaflet draws straight Web Mercator segments. Also check a conservative
     * projected bounding box so a displayed shortcut cannot cross a zone.
     * @param array{0: float, 1: float} $center
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private function mapEdgeIntersects(array $center, array $a, array $b, float $radius): bool
    {
        $angular = $radius / self::EARTH_RADIUS_M;
        $latitude = deg2rad($center[0]);
        $longitudeRadius = abs($latitude) + $angular >= M_PI / 2
            ? M_PI : asin(min(1, sin($angular) / cos($latitude)));
        $project = static fn (float $lat): float => log(tan(M_PI / 4 + max(-1.48442223, min(1.48442223, $lat)) / 2));
        $yMin = $project($latitude - $angular);
        $yMax = $project($latitude + $angular);
        $normalize = static fn (float $longitude): float => atan2(sin($longitude), cos($longitude));
        $xA = $normalize(deg2rad($a[1] - $center[1]));
        $xB = $normalize(deg2rad($b[1] - $center[1]));
        if (abs($xB - $xA) > M_PI) {
            $xB += $xB < $xA ? 2 * M_PI : -2 * M_PI;
        }
        $yA = $project(deg2rad($a[0]));
        $yB = $project(deg2rad($b[0]));
        foreach ([-2 * M_PI, 0.0, 2 * M_PI] as $offset) {
            $low = 0.0;
            $high = 1.0;
            foreach ([[$xA, $xB, $offset - $longitudeRadius, $offset + $longitudeRadius], [$yA, $yB, $yMin, $yMax]] as [$start, $end, $min, $max]) {
                $delta = $end - $start;
                if (abs($delta) < 1e-12) {
                    if ($start < $min || $start > $max) {
                        $high = -1;
                        break;
                    }
                    continue;
                }
                $enter = ($min - $start) / $delta;
                $exit = ($max - $start) / $delta;
                $low = max($low, min($enter, $exit));
                $high = min($high, max($enter, $exit));
            }
            if ($low <= $high) {
                return true;
            }
        }

        return false;
    }
}
