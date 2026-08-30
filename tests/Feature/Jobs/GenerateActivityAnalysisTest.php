<?php

namespace Tests\Feature\Jobs;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Jobs\GenerateActivityAnalysis;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GenerateActivityAnalysisTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_persists_structured_advice_and_usage(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create([
            'context' => ['schema_version' => 1, 'purpose' => 'activity_analysis'],
        ]);
        $advisor = new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                return new TrainingAdvice(
                    output: [
                        'summary' => 'Solide Ausdauerfahrt.',
                        'training_effect' => 'Aerober Reiz.',
                        'observations' => [],
                        'next_session' => [
                            'title' => 'Locker rollen',
                            'duration_minutes' => 45,
                            'intensity' => 'Leicht',
                            'details' => 'Ruhig fahren.',
                            'rationale' => 'Erholung nach Belastung.',
                        ],
                        'data_gaps' => [],
                        'cautions' => [],
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    responseId: 'response-123',
                    inputTokens: 321,
                    outputTokens: 123,
                );
            }
        };

        (new GenerateActivityAnalysis($analysis->id))->handle($advisor);

        $analysis->refresh();
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->status);
        $this->assertSame('Solide Ausdauerfahrt.', $analysis->output['summary']);
        $this->assertSame('openai', $analysis->provider);
        $this->assertSame('test-model', $analysis->model);
        $this->assertSame(321, $analysis->input_tokens);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_failed_job_records_only_a_safe_error(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Processing,
        ]);

        (new GenerateActivityAnalysis($analysis->id))->failed(null);

        $analysis->refresh();
        $this->assertSame(AiAnalysisStatus::Failed, $analysis->status);
        $this->assertSame('Die Analyse konnte nicht abgeschlossen werden.', $analysis->error);
    }
}
