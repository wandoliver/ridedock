<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Services\NextPlannedSessionResolver;
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

        $today = CarbonImmutable::today(config('app.timezone'));
        $activityDate = $model->started_at->setTimezone($model->timezone ?: config('app.timezone'));
        $nextPlanDate = $activityDate->isSameDay($today) ? $today->addDay() : $today;

        return view('activities.show', [
            'activity' => $model,
            'activityStreams' => $activityStreams,
            'latestAiAnalysis' => $model->aiAnalyses()->latest()->first(),
            'aiConfigured' => $trainingAdvisor->isConfigured(),
            'nextPlannedSession' => $nextPlannedSessionResolver->resolve($user, $nextPlanDate),
        ]);
    }
}
