<?php

namespace App\Http\Controllers;

use App\Domain\Activities\Enums\ActivityPeriod;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Models\Activity;
use App\Modules\Heatmap\Contracts\RouteGeometryProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RouteGeometryProvider $routeGeometry): View
    {
        $now = CarbonImmutable::now();
        $period = ActivityPeriod::resolve($request->query('period'));
        $range = $period->range($now);
        $user = $request->user();

        if (! $user) {
            return view('dashboard');
        }

        $activityQuery = $user->activities()
            ->when($range['from'], fn ($query, $from) => $query->where('started_at', '>=', $from))
            ->when($range['to'], fn ($query, $to) => $query->where('started_at', '<=', $to));

        $totals = (clone $activityQuery)
            ->selectRaw('COUNT(*) as activity_count')
            ->selectRaw('COALESCE(SUM(distance_m), 0) as distance_m')
            ->selectRaw('COALESCE(SUM(elevation_gain_m), 0) as elevation_gain_m')
            ->selectRaw('COALESCE(SUM(moving_time_s), 0) as moving_time_s')
            ->firstOrFail();

        /** @var Collection<int, Activity> $activities */
        $activities = (clone $activityQuery)
            ->with('latestAiAnalysis')
            ->latest('started_at')
            ->limit(5)
            ->get([
                'id', 'user_id', 'name', 'started_at', 'distance_m', 'moving_time_s',
                'elevation_gain_m', 'average_power', 'rpe',
            ]);

        $heatmapRoutes = iterator_to_array($routeGeometry->routes($user, $range['from'], $range['to']));

        return view('dashboard', [
            'activityCount' => (int) $totals->getAttribute('activity_count'),
            'distanceKilometres' => (float) $totals->getAttribute('distance_m') / 1000,
            'elevationMetres' => (float) $totals->getAttribute('elevation_gain_m'),
            'movingHours' => (float) $totals->getAttribute('moving_time_s') / 3600,
            'heatmapRoutes' => $heatmapRoutes,
            'privacyTrimMetres' => (float) config('heatmap.privacy_trim_m'),
            'activities' => $activities,
            'connectionErrorCount' => $user->providerConnections()
                ->where('status', ConnectionStatus::Error)
                ->count(),
            'period' => $period,
            'periods' => ActivityPeriod::cases(),
            'periodLabel' => $period->label($now),
            'now' => $now,
        ]);
    }
}
