<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $segments = $request->boolean('include_route')
            ? array_map(
                app(\App\Modules\Heatmap\Services\RoutePrivacy::class)->encode(...),
                app(\App\Modules\Heatmap\Services\RoutePrivacy::class)->activitySegments($this->resource)
            )
            : [];
        return [
            'id' => $this->id,
            'started_at' => $this->started_at->toIso8601String(),
            'timezone' => $this->timezone,
            'name' => $this->name,
            'type' => $this->type,
            'distance_km' => $this->rounded($this->distance_m === null ? null : $this->distance_m / 1000, 3),
            'moving_time_min' => $this->rounded($this->moving_time_s === null ? null : $this->moving_time_s / 60, 2),
            'elapsed_time_min' => $this->rounded($this->elapsed_time_s === null ? null : $this->elapsed_time_s / 60, 2),
            'elevation_gain_m' => $this->rounded($this->elevation_gain_m, 1),
            'average_speed_kmh' => $this->rounded($this->average_speed_mps === null ? null : $this->average_speed_mps * 3.6, 2),
            'max_speed_kmh' => $this->rounded($this->max_speed_mps === null ? null : $this->max_speed_mps * 3.6, 2),
            'heart_rate' => [
                'average_bpm' => $this->average_heart_rate,
                'max_bpm' => $this->max_heart_rate,
            ],
            'cadence' => [
                'average_rpm' => $this->rounded($this->average_cadence, 1),
                'max_rpm' => $this->rounded($this->max_cadence, 1),
            ],
            'power' => [
                'average_watts' => $this->rounded($this->average_power, 1),
                'weighted_average_watts' => $this->rounded($this->weighted_average_power, 1),
                'max_watts' => $this->rounded($this->max_power, 1),
                'energy_kj' => $this->rounded($this->energy_kj, 1),
            ],
            'rpe' => $this->rpe,
            'notes' => $this->notes,
            'has_route' => filled($this->summary_polyline),
            'summary_polyline' => $this->when($request->boolean('include_route'), count($segments) === 1 ? $segments[0] : null),
            'route_segments' => $this->when($request->boolean('include_route'), $segments),
            'sources' => $this->whenLoaded('sources', fn () => $this->sources->map(fn ($source) => [
                'provider' => $source->connection->provider->value,
                'external_id' => $source->external_id,
                'imported_at' => $source->imported_at->toIso8601String(),
                'has_source_file' => filled($source->source_file_path),
            ])->values()),
        ];
    }

    private function rounded(mixed $value, int $precision): ?float
    {
        return $value === null ? null : round((float) $value, $precision);
    }
}
