<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Jobs\GenerateWeeklyTrainingPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class WeeklyTrainingPlanController extends Controller
{
    public function __invoke(
        Request $request,
        WeeklyTrainingContextBuilder $contextBuilder,
        WeeklyTrainingPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', 'Die KI-Analyse ist noch nicht konfiguriert.');
        }

        if (($profile->available_days ?? []) === []) {
            return redirect()
                ->route('settings.training')
                ->with('analysis_error', 'Bitte wähle zuerst deine verfügbaren Trainingstage.');
        }

        $analysis = $user->aiAnalyses()->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => WeeklyTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'weekly-plan-v2',
            'context' => $contextBuilder->build($user),
        ]);

        try {
            GenerateWeeklyTrainingPlan::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => 'Der Wochenplan konnte nicht erstellt werden.',
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#weekly-plan')
                ->with('analysis_error', 'Der Wochenplan konnte nicht erstellt werden. Bitte versuche es erneut.');
        }

        return redirect()
            ->to(route('coach.index').'#weekly-plan')
            ->with('status', 'Dein Sieben-Tage-Plan ist fertig.');
    }
}
