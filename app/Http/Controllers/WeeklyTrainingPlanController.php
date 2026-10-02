<?php

namespace App\Http\Controllers;

use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\WeeklyTrainingContextBuilder;
use App\Domain\Training\Services\ReserveAiAnalysis;
use App\Jobs\GenerateWeeklyTrainingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $data = $request->validate(['plan_start' => ['nullable', 'date_format:Y-m-d']]);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');
        $profile = $user->athleteProfile;

        if (! $trainingPlanner->isConfigured()) {
            return back()->with('analysis_error', __('ui.messages.ai_not_configured'));
        }

        if (($profile->available_days ?? []) === []) {
            return redirect()
                ->route('settings.training')
                ->with('analysis_error', __('ui.messages.choose_days'));
        }

        $analysis = app(ReserveAiAnalysis::class)->reserve($user, [
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => WeeklyTrainingContextBuilder::SCHEMA_VERSION,
            'prompt_version' => 'weekly-plan-v6',
            'context' => $contextBuilder->build($user, isset($data['plan_start']) ? CarbonImmutable::parse($data['plan_start'], config('app.timezone')) : null),
        ]);

        try {
            if ($analysis->wasRecentlyCreated) {
                GenerateWeeklyTrainingPlan::dispatch($analysis->id)
                    ->onConnection(config('ai.queue_connection'))->onQueue('ai');
            }
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => AiAnalysisStatus::Failed,
                'error' => __('ui.messages.week_failed_stored'),
                'completed_at' => now(),
            ]);

            return redirect()
                ->to(route('coach.index').'#weekly-plan')
                ->with('analysis_error', __('ui.messages.week_failed'));
        }

        return redirect()
            ->to(route('coach.index').'#weekly-plan')
            ->with('status', __($analysis->refresh()->status === AiAnalysisStatus::Completed
            ? 'ui.messages.week_complete' : 'ui.messages.ai_queued'));
    }
}
