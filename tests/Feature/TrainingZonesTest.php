<?php

namespace Tests\Feature;

use App\Domain\Training\Services\TrainingZones;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TrainingZonesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dated_references_preserve_old_rides_and_current_values_when_backdating(): void
    {
        $user = User::factory()->create();
        $zones = app(TrainingZones::class);
        $zones->record($user, CarbonImmutable::parse('2026-01-01'), 200, 180);
        $zones->record($user, CarbonImmutable::parse('2026-07-01'), 250, 190);
        $old = Activity::factory()->for($user)->create(['started_at' => '2026-06-01']);
        $new = Activity::factory()->for($user)->create(['started_at' => '2026-08-01']);
        $unknown = Activity::factory()->for($user)->create(['started_at' => '2025-12-31']);
        $this->assertSame(200, $zones->forActivity($old)->ftp_watts);
        $this->assertSame(250, $zones->forActivity($new)->ftp_watts);
        $this->assertNull($zones->forActivity($unknown));
        $this->actingAs($user)->post(route('settings.training.zones.store'), [
            'effective_at' => '2026-03-01T12:00', 'ftp_watts' => 220, 'max_heart_rate' => 185,
            'user_id' => User::factory()->create()->id,
        ])->assertRedirect();
        $this->assertSame(250, $user->athleteProfile()->first()->ftp_watts);
        $this->assertSame(220, $zones->forActivity($old)->ftp_watts);
        $this->assertSame(3, $user->trainingZoneProfiles()->count());
        $this->assertSame(250, $zones->forActivity($new)->ftp_watts);
    }

    public function test_ranges_have_no_gaps_and_future_entries_are_rejected(): void
    {
        $user = User::factory()->create();
        $zones = app(TrainingZones::class);
        $snapshot = $zones->record($user, CarbonImmutable::parse('2026-01-01'), 251, 191);
        foreach (['power', 'heart_rate'] as $sensor) {
            $ranges = $zones->ranges($snapshot, $sensor);
            foreach (array_slice($ranges, 1) as $index => $range) {
                $this->assertSame($ranges[$index]['upper'] + 1, $range['lower']);
            }
            $this->assertNull($ranges[array_key_last($ranges)]['upper']);
        }
        $this->actingAs($user)->post(route('settings.training.zones.store'), [
            'effective_at' => now()->addYear()->format('Y-m-d\TH:i'), 'ftp_watts' => 250,
        ])->assertSessionHasErrors('effective_at');
        $this->assertSame(1, $user->trainingZoneProfiles()->count());
        $this->actingAs(User::factory()->create())->withoutVite()->get(route('settings.training'))
            ->assertOk()->assertDontSee('251–');
    }

    public function test_ordinary_edits_only_append_when_references_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('settings.training.update'), ['ftp_watts' => '200', 'max_heart_rate' => '180'])->assertRedirect();
        $this->patch(route('settings.training.update'), ['ftp_watts' => '200', 'max_heart_rate' => '180', 'goal' => 'New goal'])->assertRedirect();
        $this->assertSame(1, $user->trainingZoneProfiles()->count());
        $this->patch(route('settings.training.update'), ['ftp_watts' => 250, 'max_heart_rate' => null])->assertRedirect();
        $this->assertSame(2, $user->trainingZoneProfiles()->count());
    }
}
