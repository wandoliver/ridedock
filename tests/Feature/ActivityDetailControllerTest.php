<?php

namespace Tests\Feature;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\AiAnalysis;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityDetailControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_owner_sees_activity_metrics_streams_sources_and_events(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Passo Mendola',
            'started_at' => '2026-08-30T06:00:00Z',
            'distance_m' => 42500,
            'moving_time_s' => 5400,
            'elevation_gain_m' => 620,
            'average_power' => 220,
            'rpe' => 6,
            'notes' => 'Kontrolliert gefahren.',
        ]);
        $connection = ProviderConnection::factory()->for($user)->create();
        ActivitySource::factory()
            ->for($activity)
            ->for($connection, 'connection')
            ->create();
        ActivityStream::factory()->for($activity)->create([
            'type' => 'latlng',
            'data' => [
                ['elapsed_seconds' => 0, 'value' => [0.01, 0.02]],
                ['elapsed_seconds' => 10, 'value' => [0.011, 0.021]],
            ],
        ]);
        ActivityStream::factory()->for($activity)->create([
            'type' => 'power',
            'data' => [
                ['elapsed_seconds' => 0, 'value' => 205],
                ['elapsed_seconds' => 10, 'value' => 235],
            ],
        ]);
        ActivityEvent::factory()->for($activity)->create([
            'type' => 'shift',
            'elapsed_seconds' => 10,
        ]);

        $response = $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity));

        $response->assertOk()
            ->assertViewHas('activity', fn (Activity $viewActivity): bool => $viewActivity->is($activity))
            ->assertViewHas('activityStreams', fn (array $streams): bool => count($streams['latlng']) === 2
                && count($streams['power']) === 2)
            ->assertSee('Passo Mendola')
            ->assertSee('42,5 km')
            ->assertSee('220 W')
            ->assertSee('RPE 6')
            ->assertSee('Kontrolliert gefahren.')
            ->assertSee(route('activities.feedback.update', $activity), false)
            ->assertSee('Notizen speichern')->assertSee('Bewertung speichern')
            ->assertSee('Hammerhead')
            ->assertSee('Schaltinformationen')
            ->assertViewHas('gearShifts', fn (array $shifts): bool => $shifts['total'] === 1)
            ->assertSee('id="ride-route"', false)
            ->assertSee('data-stream-chart="power"', false);
    }

    public function test_owner_updates_or_clears_rpe_and_notes_from_the_detail_page(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('activities.feedback.update', $activity), [
                'rpe' => 7,
                'notes' => 'Die Beine waren am Ende schwer.',
            ])
            ->assertRedirect(route('activities.show', $activity))
            ->assertSessionHas('status', 'Gefühl und Notizen wurden gespeichert.');

        $this->assertSame(7, $activity->fresh()->rpe);
        $this->assertSame('Die Beine waren am Ende schwer.', $activity->fresh()->notes);

        $this->actingAs($user)
            ->patch(route('activities.feedback.update', $activity), [
                'rpe' => '',
                'notes' => '',
            ])
            ->assertRedirect(route('activities.show', $activity));

        $this->assertNull($activity->fresh()->rpe);
        $this->assertNull($activity->fresh()->notes);
    }

    public function test_another_user_cannot_update_activity_feedback(): void
    {
        $activity = Activity::factory()->create(['rpe' => 4, 'notes' => 'Privat']);

        $this->actingAs(User::factory()->create())
            ->patch(route('activities.feedback.update', $activity), [
                'rpe' => 9,
                'notes' => 'Überschrieben',
            ])
            ->assertNotFound();

        $this->assertSame(4, $activity->fresh()->rpe);
        $this->assertSame('Privat', $activity->fresh()->notes);
    }

    public function test_guest_and_another_user_cannot_see_an_activity(): void
    {
        $activity = Activity::factory()->create();

        $this->get(route('activities.show', $activity))->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('activities.show', $activity))
            ->assertNotFound();
    }

    public function test_dashboard_links_to_the_activity_detail(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create(['name' => 'Linked ride']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('activities.show', $activity), false);
    }

    public function test_owner_sees_a_completed_structured_ai_analysis(): void
    {
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new \LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'summary' => 'Solide kontrollierte Ausdauerfahrt.',
                'training_effect' => 'Aerober Trainingsreiz.',
                'observations' => [[
                    'title' => 'Gleichmäßige Belastung',
                    'detail' => 'Die Belastung war kontrolliert.',
                    'evidence' => ['activity.rpe', 'activity.duration_minutes'],
                ]],
                'next_session' => [
                    'title' => 'Locker rollen',
                    'duration_minutes' => 45,
                    'intensity' => 'Leicht',
                    'details' => 'Ruhig fahren.',
                    'rationale' => 'Erholung nach der Belastung.',
                ],
                'data_gaps' => ['FTP fehlt'],
                'cautions' => [],
            ],
            'completed_at' => now(),
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('RideDock Coach')
            ->assertSee('Solide kontrollierte Ausdauerfahrt.')
            ->assertSee('Gleichmäßige Belastung')
            ->assertSee('activity.rpe, activity.duration_minutes')
            ->assertSee('Locker rollen')
            ->assertSee('Neu analysieren');
    }

    public function test_owner_sees_an_indeterminate_progress_bar_for_a_queued_analysis(): void
    {
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new \LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Pending,
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('Wartet auf Verarbeitung')
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuetext="Wartet auf Verarbeitung"', false)
            ->assertSee('data-ai-status-url', false)
            ->assertSee('<noscript><meta http-equiv="refresh" content="10"></noscript>', false)
            ->assertDontSee('aria-valuenow=', false);
    }

    public function test_weekly_plan_takes_precedence_over_an_activity_next_session(): void
    {
        CarbonImmutable::setTestNow('2026-09-02T12:00:00Z');
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new \LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create(['started_at' => '2026-09-01T06:00:00Z', 'timezone' => 'Europe/Rome']);
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'summary' => 'Solide Fahrt.',
                'training_effect' => 'Aerober Reiz.',
                'observations' => [],
                'next_session' => [
                    'title' => 'Lockere Regenerationsfahrt',
                    'duration_minutes' => 40,
                    'intensity' => 'Sehr locker',
                    'details' => 'Locker fahren.',
                    'rationale' => 'Aktivitätsempfehlung.',
                ],
                'data_gaps' => [],
                'cautions' => [],
            ],
            'completed_at' => now(),
        ]);
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'days' => [[
                    'date' => now()->toDateString(),
                    'title' => 'Vollständige Erholung',
                    'type' => 'rest',
                    'duration_minutes' => null,
                    'intensity' => 'Keine Trainingsbelastung',
                    'details' => 'Radfrei und ausreichend schlafen.',
                    'rationale' => 'Erholung nach der Vorwoche.',
                ]],
            ],
            'completed_at' => now(),
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('Laut Sieben-Tage-Plan')
            ->assertSee('Vollständige Erholung')
            ->assertSee('Radfrei und ausreichend schlafen.')
            ->assertDontSee('Lockere Regenerationsfahrt');
    }

    public function test_todays_activity_skips_todays_completed_plan_day(): void
    {
        CarbonImmutable::setTestNow('2026-09-02T12:00:00Z');
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new \LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'started_at' => '2026-09-02T06:00:00Z',
            'timezone' => 'Europe/Rome',
        ]);
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'summary' => 'Heutige Fahrt abgeschlossen.',
                'training_effect' => 'Aerober Reiz.',
                'observations' => [],
                'next_session' => [
                    'title' => 'Fallback-Einheit',
                    'duration_minutes' => 30,
                    'intensity' => 'Locker',
                    'details' => 'Locker fahren.',
                    'rationale' => 'Erholung.',
                ],
                'data_gaps' => [],
                'cautions' => [],
            ],
            'completed_at' => now(),
        ]);
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'days' => [
                    [
                        'date' => '2026-09-02',
                        'title' => 'Heutige Grundlagenfahrt',
                        'type' => 'ride',
                        'duration_minutes' => 45,
                        'intensity' => 'Locker',
                        'details' => 'Heute locker fahren.',
                        'rationale' => 'Grundlage.',
                    ],
                    [
                        'date' => '2026-09-03',
                        'title' => 'Morgige Tempo-Intervalle',
                        'type' => 'ride',
                        'duration_minutes' => 50,
                        'intensity' => 'Zügig',
                        'details' => 'Morgen kontrollierte Intervalle fahren.',
                        'rationale' => 'Tempo entwickeln.',
                    ],
                ],
            ],
            'completed_at' => now(),
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertViewHas('nextPlannedSession', fn (array $session): bool => $session['date'] === '2026-09-03')
            ->assertSee('Morgige Tempo-Intervalle')
            ->assertSee('Heutige Grundlagenfahrt')
            ->assertSee('Zugeordnet ✓')
            ->assertDontSee('Fallback-Einheit');
    }

    public function test_owner_sees_that_an_analysis_is_being_processed(): void
    {
        $this->app->instance(TrainingAdvisor::class, new class implements TrainingAdvisor
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyzeActivity(array $context): TrainingAdvice
            {
                throw new \LogicException('No provider request expected.');
            }
        });
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Processing,
            'started_at' => now(),
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('Analyse wird erstellt')
            ->assertSee('Der Coach wertet deine Trainingsdaten aus.')
            ->assertSee('aria-valuetext="Analyse wird erstellt"', false);
    }
}
