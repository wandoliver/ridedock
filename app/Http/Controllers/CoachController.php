<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CoachController extends Controller
{
    public function __invoke(Request $request, TrainingAdvisor $trainingAdvisor): View
    {
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
        $latestWeeklyPlan = $user->aiAnalyses()
            ->where('type', 'weekly_plan')
            ->latest()
            ->first();

        return view('coach.index', [
            'profile' => $user->athleteProfile,
            'activities' => $activities,
            'aiConfigured' => $trainingAdvisor->isConfigured(),
            'latestWeeklyPlan' => $latestWeeklyPlan,
        ]);
    }
}
