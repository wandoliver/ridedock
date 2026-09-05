<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\PlanActivityMatcher;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CoachController extends Controller
{
    public function __invoke(Request $request, TrainingAdvisor $trainingAdvisor): View
    {
        $request->validate(['week' => ['nullable', 'date_format:Y-m-d'], 'plan' => ['nullable', 'uuid']]);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');

        /** @var Collection<int, Activity> $activities */
        $activities = $user->activities()
            ->with('latestAiAnalysis')
            ->latest('started_at')
            ->limit(10)
            ->get([
                'id', 'user_id', 'name', 'type', 'started_at', 'distance_m',
                'moving_time_s', 'elevation_gain_m', 'average_power', 'rpe',
            ]);
        $weeklyPlans = $user->aiAnalyses()->where('type', 'weekly_plan')
            ->where('status', AiAnalysisStatus::Completed)->latest()->get();
        $latestWeeklyPlan = $request->filled('plan')
            ? $weeklyPlans->firstWhere('id', $request->query('plan'))
            : $weeklyPlans->first();
        abort_if($request->filled('plan') && ! $latestWeeklyPlan, 404);
        $requestedWeek = $request->query('week');
        if (is_string($requestedWeek) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedWeek)) {
            $latestWeeklyPlan = $weeklyPlans->first(fn ($plan) => app(PlanActivityMatcher::class)->days($plan)
                ->contains(fn ($day) => ($day['date'] ?? '') === $requestedWeek));
        }
        $calendarActivities = $user->activities()->orderBy('started_at')->get([
            'id', 'user_id', 'name', 'type', 'started_at', 'timezone', 'moving_time_s',
        ]);
        $latestGoalPlan = $user->aiAnalyses()
            ->where('type', 'goal_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();
        $latestTrainingBlock = $user->aiAnalyses()
            ->where('type', 'training_block')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();

        if (($latestTrainingBlock?->context['goal_plan_analysis_id'] ?? null) !== $latestGoalPlan?->id) {
            $latestTrainingBlock = null;
        }

        return view('coach.index', [
            'weeklyPlans' => $weeklyPlans,
            'calendarActivities' => $calendarActivities,
            'profile' => $user->athleteProfile,
            'activities' => $activities,
            'aiConfigured' => $trainingAdvisor->isConfigured(),
            'latestGoalPlan' => $latestGoalPlan,
            'latestTrainingBlock' => $latestTrainingBlock,
            'latestWeeklyPlan' => $latestWeeklyPlan,
        ]);
    }
}
