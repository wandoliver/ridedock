<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Services\NextPlannedSessionResolver;
use App\Domain\Training\Services\PlanActivityMatcher;
use App\Domain\Training\Services\SensorZoneDistribution;
use App\Domain\Training\Services\ComparableEfficiency;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityDetailController extends Controller
{
    public function __invoke(
        Request $request,
        string $activity,
        TrainingAdvisor $trainingAdvisor,
        NextPlannedSessionResolver $nextPlannedSessionResolver,
    ): View {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $model = Activity::query()
            ->whereBelongsTo($user)
            ->with([
                'sources.connection',
                'streams' => fn ($query) => $query->orderBy('type'),
                'events' => fn ($query) => $query
                    ->where('type', 'shift')
                    ->orderBy('elapsed_seconds'),
            ])
            ->findOrFail($activity);
        $activityStreams = [];

        foreach ($model->streams as $stream) {
            $activityStreams[$stream->type] = array_values($stream->data);
        }
        $segments = app(\App\Modules\Heatmap\Services\RoutePrivacy::class)->activitySegments($model);
        unset($activityStreams['latlng']);
        if ($segments !== []) {
            $activityStreams['latlng'] = [];
            foreach ($segments as $segment) {
                if ($activityStreams['latlng'] !== []) {
                    $activityStreams['latlng'][] = null;
                }
                foreach ($segment as $point) {
                    $activityStreams['latlng'][] = ['value' => $point];
                }
            }
        }

        $today = CarbonImmutable::today(config('app.timezone'));
        $activityDate = $model->localStartedAt();
        $nextPlanDate = $activityDate->isSameDay($today) ? $today->addDay() : $today;

        $weeklyPlan = $user->aiAnalyses()->where('type', 'weekly_plan')
            ->where('status', 'completed')->latest()->first();
        $days = app(PlanActivityMatcher::class)->days($weeklyPlan);
        $matches = app(PlanActivityMatcher::class)->match($days, collect([$model]));
        $matchedDay = $days->first(fn ($day) => $day['type'] === 'ride' && $matches->get($day['date'], collect())->isNotEmpty());

        return view('activities.show', [
            'returnUrl' => \App\Support\ActivityNavigation::returnTo($request) ?? route('activities.index'),
            'efficiency' => app(ComparableEfficiency::class)->report($model),
            'sensorZones' => app(SensorZoneDistribution::class)->activity($model),
            'matchedDay' => $matchedDay,
            'matchedPlan' => $weeklyPlan,
            'activity' => $model,
            'activityStreams' => $activityStreams,
            'latestAiAnalysis' => $model->aiAnalyses()->latest()->first(),
            'aiConfigured' => $trainingAdvisor->isConfigured(),
            'nextPlannedSession' => $nextPlannedSessionResolver->resolve($user, $nextPlanDate),
        ]);
    }
}
