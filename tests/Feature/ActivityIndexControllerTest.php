<?php

namespace Tests\Feature;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityIndexControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('activities.index'))
            ->assertRedirect(route('login'));
    }

    public function test_owner_sees_paginated_activities_without_another_users_rides(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->create();
        Activity::factory()->for($user)->count(26)->sequence(
            fn ($sequence): array => [
                'name' => 'Ride '.($sequence->index + 1),
                'started_at' => Carbon::parse('2026-08-30')->subDays($sequence->index),
            ],
        )->create();
        Activity::factory()->create(['name' => 'Another users ride']);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.index'))
            ->assertOk()
            ->assertSee('26 Aktivitäten')
            ->assertSee('Ride 1')
            ->assertDontSee('Ride 26')
            ->assertDontSee('Another users ride')
            ->assertSee(route('activities.index', ['page' => 2]), false);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Ride 26');
    }

    public function test_period_filter_limits_the_activity_list(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = User::factory()->create();
        Activity::factory()->for($user)->create([
            'name' => 'Recent ride',
            'started_at' => '2026-08-20T10:00:00Z',
        ]);
        Activity::factory()->for($user)->create([
            'name' => 'Old ride',
            'started_at' => '2026-01-15T10:00:00Z',
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.index', ['period' => '30d']))
            ->assertOk()
            ->assertSee('Letzte 30 Tage')
            ->assertSee('Recent ride')
            ->assertDontSee('Old ride');
    }

    public function test_analysis_status_is_visible_in_the_archive(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Analysierte Fahrt',
        ]);
        AiAnalysis::factory()->for($user)->for($activity)->create([
            'status' => AiAnalysisStatus::Completed,
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('activities.index'))
            ->assertOk()
            ->assertSee('Analysierte Fahrt')
            ->assertSee('Analysiert');
    }
}
