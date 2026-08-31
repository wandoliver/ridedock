<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\TrainingBlockContextBuilder;
use App\Jobs\GenerateTrainingBlock;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TrainingBlockController extends Controller
{
    public function __invoke(
        Request $request,
        TrainingBlockContextBuilder $contextBuilder,
        TrainingBlockPlanner $trainingPlanner,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', 'Die KI-Analyse ist noch nicht konfiguriert.');
        }

        $goalPlan = $user->aiAnalyses()
            ->where('type', 'goal_plan')
            ->where('status', AiAnalysisStatus::Completed)
            ->latest()
            ->first();

        if ($goalPlan === null) {
            return redirect()
                ->to(route('coach.index').'#goal-plan')
                ->with('analysis_error', 'Erstelle zuerst deine Ziel-Roadmap.');
        }

        $analysis = $user->aiAnalyses()->create([
            'activity_id' => null,
            'type' => 'training_block',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => TrainingBlockContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'training-block-v1',
            'context' => $contextBuilder->build($user),
        ]);

        try {
            GenerateTrainingBlock::dispatchSync($analysis->id);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => 'Der Trainingsblock konnte nicht erstellt werden.',
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#training-block')
                ->with('analysis_error', 'Der Trainingsblock konnte nicht erstellt werden. Bitte versuche es erneut.');
        }

        return redirect()
            ->to(route('coach.index').'#training-block')
            ->with('status', 'Dein Vier-Wochen-Block ist fertig.');
    }
}
