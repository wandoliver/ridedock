<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Support\ActivityNavigation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActivityNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_back_link_retains_archive_filter_and_pagination(): void
    {
        $user = User::factory()->create();
        $ride = Activity::factory()->for($user)->create();
        $returnTo = '/activities?period=30d&page=2';
        $this->withoutVite()->actingAs($user)
            ->get(route('activities.show', ['activity' => $ride->id, 'return_to' => $returnTo]))
            ->assertOk()->assertViewHas('returnUrl', $returnTo)
            ->assertSee('data-context-back', false);
    }

    public function test_independent_feedback_forms_preserve_other_feedback_and_navigation(): void
    {
        $user = User::factory()->create();
        $ride = Activity::factory()->for($user)->create(['rpe' => 5, 'notes' => 'Existing note']);
        $returnTo = '/coach?day=2026-10-05#week';
        $parameters = ['activity' => $ride->id, 'return_to' => $returnTo];
        $this->actingAs($user)->patch(route('activities.feedback.update', $parameters), ['rpe' => 7, 'section' => 'overview'])
            ->assertRedirect(route('activities.show', $parameters).'#overview');
        $this->assertSame('Existing note', $ride->fresh()->notes);
        $this->patch(route('activities.feedback.update', $parameters), ['notes' => 'Updated note', 'section' => 'notes'])
            ->assertRedirect(route('activities.show', $parameters).'#notes');
        $this->assertSame(7, $ride->fresh()->rpe);
        $this->assertSame('Updated note', $ride->fresh()->notes);
        $this->patch(route('activities.comparison.update', $parameters), ['comparison_group' => 'Endurance', 'section' => 'measurements'])
            ->assertRedirect(route('activities.show', $parameters).'#measurements');
    }

    public function test_return_destination_and_section_reject_external_or_unapproved_urls(): void
    {
        foreach (['https://example.com', '//example.com', '/login', '/activities/other', '/activities\\evil', "/activities\nLocation:evil", ['/activities']] as $value) {
            $this->assertNull(ActivityNavigation::returnTo(Request::create('/', 'GET', ['return_to' => $value])));
        }
        foreach (['/', '/activities?period=30d&page=2', '/coach?day=2026-10-05#week', '/insights#routes'] as $value) {
            $this->assertSame($value, ActivityNavigation::returnTo(Request::create('/', 'GET', ['return_to' => $value])));
        }
        $this->assertSame(route('activities.show', 'ride'), ActivityNavigation::detailUrl(Request::create('/', 'GET', ['section' => 'unknown']), 'ride'));
    }

    public function test_profile_and_zone_saves_return_to_the_current_settings_section(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('settings.training.update'), [
            'weekly_hours' => 5, 'section' => 'availability',
        ])->assertRedirect(route('settings.training').'#availability');
        $this->post(route('settings.training.zones.store'), [
            'effective_at' => now()->subDay()->format('Y-m-d\\TH:i'), 'ftp_watts' => 200, 'section' => 'thresholds',
        ])->assertRedirect(route('settings.training').'#thresholds');
    }

    public function test_insights_require_authentication(): void
    {
        $this->get(route('analytics'))->assertRedirect(route('login'));
    }
}
