<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityIndexRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;

class AnalysisExportController extends Controller
{
    public function __invoke(ActivityIndexRequest $request): JsonResponse
    {
        $activities = Activity::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('sources', fn ($query) => $query->whereNull('deleted_at_provider'))
            ->with('sources.connection')
            ->when($request->boolean('include_route'), fn ($query) => $query->with(['streams' => fn ($query) => $query->where('type', 'latlng'), 'user.privacyZones']))
            ->when($request->date('from'), fn ($query, $from) => $query->where('started_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($query, $to) => $query->where('started_at', '<=', $to->endOfDay()))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(5000)
            ->get();

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
}
