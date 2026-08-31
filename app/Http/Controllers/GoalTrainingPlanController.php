<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\GoalTrainingContextBuilder;
use App\Jobs\GenerateGoalTrainingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoalTrainingPlanController extends Controller
{
    public function __invoke(
        Request $request,
        GoalTrainingContextBuilder $contextBuilder,
        GoalTrainingPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;
        $tomorrow = CarbonImmutable::today(config('app.timezone'))->addDay();

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', 'Die KI-Analyse ist noch nicht konfiguriert.');
        }

        if ($profile?->goal === null || trim($profile->goal) === ''
            || $profile->goal_date === null || $profile->goal_date->isBefore($tomorrow)) {
            return redirect()
                ->route('settings.training')
                ->with('analysis_error', 'Bitte hinterlege zuerst ein Ziel mit zukünftigem Zieldatum.');
        }

        $analysis = $user->aiAnalyses()->create([
            'activity_id' => null,
            'type' => 'goal_plan',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => GoalTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'goal-plan-v1',
            'context' => $contextBuilder->build($user, $tomorrow),
        ]);

        try {
            GenerateGoalTrainingPlan::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => 'Die Ziel-Roadmap konnte nicht erstellt werden.',
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#goal-plan')
                ->with('analysis_error', 'Die Ziel-Roadmap konnte nicht erstellt werden. Bitte versuche es erneut.');
        }

        return redirect()
            ->to(route('coach.index').'#goal-plan')
            ->with('status', 'Deine Ziel-Roadmap ist fertig.');
    }
}
