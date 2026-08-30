<?php

namespace Tests\Unit\Domain\Training\Services;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\NextPlannedSessionResolver;
use App\Models\AiAnalysis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NextPlannedSessionResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_the_next_day_from_the_latest_completed_weekly_plan(): void
    {
        $user = User::factory()->create();
        AiAnalysis::factory()->for($user)->create([
            'activity_id' => null,
            'type' => 'weekly_plan',
            'status' => AiAnalysisStatus::Completed,
            'output' => [
                'days' => [
                    $this->day('2026-08-30', 'Vergangener Tag', 'ride'),
                    $this->day('2026-08-31', 'Vollständige Erholung', 'rest'),
                    $this->day('2026-09-01', 'Lockere Ausfahrt', 'ride'),
                ],
            ],
        ]);

        $session = app(NextPlannedSessionResolver::class)->resolve(
            $user,
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame('2026-08-31', $session['date']);
        $this->assertSame('Vollständige Erholung', $session['title']);
        $this->assertSame('rest', $session['type']);
    }

    /** @return array{date: string, title: string, type: string, duration_minutes: int|null, intensity: string, details: string, rationale: string} */
    private function day(string $date, string $title, string $type): array
    {
        return [
            'date' => $date,
            'title' => $title,
            'type' => $type,
            'duration_minutes' => $type === 'ride' ? 45 : null,
            'intensity' => $type === 'ride' ? 'Leicht' : 'Keine Trainingsbelastung',
            'details' => 'Nach Tagesform gestalten.',
            'rationale' => 'Erholung und Belastung ausgleichen.',
        ];
    }
}
