<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\ActivityStream;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class ActivityAiAnalysisControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_directly_run_a_private_activity_analysis(): void
    {
        $this->app->instance(TrainingAdvisor::class, $this->advisor(true));
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'notes' => 'PRIVATE_RIDE_NOTE',
            'started_at' => '2026-08-30T08:00:00Z',
            'average_speed_mps' => 8.333,
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng',
            'data' => [['elapsed_seconds' => 0, 'value' => [46.4983, 11.3548]]],
        ]);

        $this->actingAs($user)
            ->post(route('activities.ai-analysis.store', $activity))
            ->assertRedirect()
            ->assertSessionHas('status', 'Die Fahrtanalyse ist abgeschlossen.');

        $analysis = $activity->aiAnalyses()->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->status);
        $this->assertSame('Direkt erstellte Analyse.', $analysis->output['summary']);
        $this->assertSame(7, $analysis->context_schema_version);
        $this->assertSame('activity-v7', $analysis->prompt_version);
        $this->assertSame(30, $analysis->context['activity']['average_speed_kmh']);
        $encodedContext = json_encode($analysis->context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PRIVATE_RIDE_NOTE', $encodedContext);
        $this->assertStringNotContainsString('46.4983', $encodedContext);
        $this->assertFalse($analysis->context['privacy']['gps_coordinates_included']);
    }

    public function test_analysis_requires_configuration_and_activity_ownership(): void
    {
        $this->app->instance(TrainingAdvisor::class, $this->advisor(false));
        $owner = User::factory()->create();
        $activity = Activity::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('activities.ai-analysis.store', $activity))
            ->assertRedirect()
            ->assertSessionHas('analysis_error');

        $this->assertDatabaseEmpty('ai_analyses');

        $this->app->instance(TrainingAdvisor::class, $this->advisor(true));
        $this->actingAs(User::factory()->create())
            ->post(route('activities.ai-analysis.store', $activity))
            ->assertNotFound();
    }

    public function test_provider_failure_returns_to_the_activity_and_marks_the_analysis_failed(): void
    {
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new LogicException('PRIVATE_PROVIDER_ERROR');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('activities.ai-analysis.store', $activity))
            ->assertRedirect()
            ->assertSessionHas('analysis_error', 'Die Fahrtanalyse konnte nicht abgeschlossen werden. Bitte versuche es erneut.');

        $analysis = $activity->aiAnalyses()->firstOrFail();
        $this->assertSame(AiAnalysisStatus::Failed, $analysis->status);
        $this->assertSame('Die Analyse konnte nicht abgeschlossen werden.', $analysis->error);
        $this->assertStringNotContainsString('PRIVATE_PROVIDER_ERROR', $analysis->error);
    }

    private function advisor(bool $configured): TrainingAdvisor
    {
        return new class($configured) implements TrainingAdvisor
        {
            public function __construct(private readonly bool $configured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                return new TrainingAdvice(
                    output: [
                        'summary' => 'Direkt erstellte Analyse.',
                        'training_effect' => 'Aerober Reiz.',
                        'observations' => [],
                        'next_session' => [
                            'title' => 'Locker rollen',
                            'duration_minutes' => 45,
                            'intensity' => 'Leicht',
                            'details' => 'Ruhig fahren.',
                            'rationale' => 'Erholung.',
                        ],
                        'data_gaps' => [],
                        'cautions' => [],
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    responseId: 'response-123',
                    inputTokens: 100,
                    outputTokens: 50,
                );
            }
        };
    }
}
