<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityIndexRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisExportController extends Controller
{
    public function __invoke(ActivityIndexRequest $request): JsonResponse|StreamedResponse
    {
        $activities = Activity::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('sources', fn ($query) => $query->whereNull('deleted_at_provider'))
            ->with('sources.connection')
            ->when($request->date('from'), fn ($query, $from) => $query->where('started_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($query, $to) => $query->where('started_at', '<=', $to->endOfDay()))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(5000)
            ->get();

        if ($request->query('format') === 'csv') {
            return $this->csv($activities);
        }

        return response()->json([
            'schema_version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'units' => [
                'distance' => 'km', 'duration' => 'minutes', 'speed' => 'km/h',
                'elevation' => 'm', 'heart_rate' => 'bpm', 'cadence' => 'rpm', 'power' => 'W',
            ],
            'activity_count' => $activities->count(),
            'activities' => ActivityResource::collection($activities)->resolve($request),
        ]);
    }

    /** @param Collection<int, Activity> $activities */
    private function csv(Collection $activities): StreamedResponse
    {
        return response()->streamDownload(function () use ($activities): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }

            fputcsv($stream, [
                'id', 'started_at', 'name', 'type', 'distance_km', 'moving_time_min',
                'elevation_gain_m', 'average_speed_kmh', 'average_hr_bpm', 'max_hr_bpm',
                'average_cadence_rpm', 'average_power_watts', 'weighted_average_power_watts', 'rpe',
            ]);

            foreach ($activities as $activity) {
                fputcsv($stream, [
                    $activity->id,
                    $activity->started_at->toIso8601String(),
                    $activity->name,
                    $activity->type,
                    $activity->distance_m === null ? null : round($activity->distance_m / 1000, 3),
                    $activity->moving_time_s === null ? null : round($activity->moving_time_s / 60, 2),
                    $activity->elevation_gain_m,
                    $activity->average_speed_mps === null ? null : round($activity->average_speed_mps * 3.6, 2),
                    $activity->average_heart_rate,
                    $activity->max_heart_rate,
                    $activity->average_cadence,
                    $activity->average_power,
                    $activity->weighted_average_power,
                    $activity->rpe,
                ]);
            }

            fclose($stream);
        }, 'activities.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
