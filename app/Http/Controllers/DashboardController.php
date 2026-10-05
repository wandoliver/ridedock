<?php

namespace App\Http\Controllers;

use App\Domain\Activities\Enums\ActivityPeriod;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Training\Services\PlanActivityMatcher;
use App\Domain\Training\Services\TrainingStatistics;
use App\Domain\Training\Services\RecordedLoadBalance;
use App\Domain\Training\Services\SensorZoneDistribution;
use App\Domain\Training\Services\ComparableEfficiency;
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

        $isAnalytics = $request->routeIs('analytics');
        $activityQuery = $user->activities()
            ->when($isAnalytics && $range['from'], fn ($query) => $query->where('started_at', '>=', $range['from']))
            ->when($isAnalytics && $range['to'], fn ($query) => $query->where('started_at', '<=', $range['to']));

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
            ->limit($isAnalytics ? 5 : 3)
            ->get([
                'id', 'user_id', 'name', 'started_at', 'distance_m', 'moving_time_s',
                'elevation_gain_m', 'average_power', 'rpe',
            ]);

        $heatmapRoutes = $isAnalytics ? iterator_to_array($routeGeometry->routes($user, $range['from'], $range['to'])) : [];

        $weeklyPlan = $user->aiAnalyses()->where('type', 'weekly_plan')
            ->where('status', 'completed')->latest()->first();
        $planDays = app(PlanActivityMatcher::class)->days($weeklyPlan);
        $today = $now->toDateString();
        $todayPlan = $planDays->firstWhere('date', $today)
            ?? $planDays->first(fn ($day) => $day['date'] >= $today && $day['type'] === 'ride');
        $weeklyVolume = collect();
        if ($isAnalytics) {
            $weeklyVolume = (clone $activityQuery)->get(['started_at', 'moving_time_s'])
                ->groupBy(fn ($activity) => $activity->started_at->startOfWeek()->toDateString())
                ->map(fn ($rides) => $rides->sum('moving_time_s') / 3600)->sortKeys();
        }
        $planActivities = $user->activities()->whereBetween('started_at', [
            CarbonImmutable::parse($planDays->min('date') ?? $today)->subDay(),
            CarbonImmutable::parse($planDays->max('date') ?? $today)->addDays(2),
        ])->get(['id', 'name', 'started_at', 'timezone', 'moving_time_s']);
        $planMatches = app(PlanActivityMatcher::class)->match($planDays, $planActivities);
        $todayRides = $todayPlan ? $planMatches->get($todayPlan['date'], collect()) : collect();
        $planRiddenMinutes = $planActivities->filter(fn ($ride) => $ride->localStartedAt()->toDateString() >= ($planDays->min('date') ?? $today)
            && $ride->localStartedAt()->toDateString() <= ($planDays->max('date') ?? $today))->sum('moving_time_s') / 60;
        $block = $user->aiAnalyses()->where('type', 'training_block')->where('status', 'completed')->latest()->first();
        $goal = $user->aiAnalyses()->where('type', 'goal_plan')->where('status', 'completed')->latest()->first();
        $plannedVolume = collect();
        if ($block && ($block->context['goal_plan_analysis_id'] ?? null) === $goal?->id) {
            foreach (is_array($block->output['weeks'] ?? null) ? $block->output['weeks'] : [] as $week) {
                if (is_array($week) && isset($week['week_start'], $week['target_duration_minutes'])) {
                    $plannedVolume->put(CarbonImmutable::parse($week['week_start'])->startOfWeek()->toDateString(), $week['target_duration_minutes'] / 60);
                }
            }
        }
        $recentCount = $user->activities()->where('started_at', '>=', $now->subDays(7))->count();

        return view($isAnalytics ? 'analytics' : 'dashboard', [
            'efficiencyTrends' => $isAnalytics ? app(ComparableEfficiency::class)->history($user, $now) : [],
            'sensorZones' => $isAnalytics ? app(SensorZoneDistribution::class)->history($user, $now) : [],
            'loadBalance' => $isAnalytics ? app(RecordedLoadBalance::class)->report($user, $now) : [],
            'trainingStatistics' => app(TrainingStatistics::class)->report($user, $now),
            'todayRides' => $todayRides,
            'planRiddenMinutes' => $planRiddenMinutes,
            'plannedVolume' => $plannedVolume,
            'weeklyPlan' => $weeklyPlan,
            'todayPlan' => $todayPlan,
            'weeklyVolume' => $weeklyVolume,
            'recentCount' => $recentCount,
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
