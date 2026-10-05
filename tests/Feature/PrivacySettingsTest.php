<?php

namespace Tests\Feature;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Services\ActivityFileArchiver;
use App\Domain\Activities\Services\ActivityImporter;
use App\Domain\Activities\Services\DataRetention;
use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\AiAnalysis;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacySettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_settings_require_login_and_default_to_unlimited_in_both_languages(): void
    {
        $this->get(route('settings.privacy'))->assertRedirect(route('login'));
        foreach (['de' => 'Unbegrenzt', 'en' => 'Unlimited'] as $locale => $label) {
            $user = User::factory()->create(['locale' => $locale]);
            $this->actingAs($user)->get(route('settings.privacy'))->assertOk()->assertSee($label)->assertSee('privacy-zone-map');
            $this->assertNull($user->activity_retention_days);
            $this->assertNull($user->source_retention_days);
        }
    }

    public function test_saving_limits_does_not_delete_data_and_rejects_invalid_input(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create(['started_at' => now()->subYears(3)]);
        $this->actingAs($user)->patch(route('settings.privacy.update'), ['activity_retention_days' => 90, 'source_retention_days' => 30])
            ->assertRedirect(route('settings.privacy'));
        $this->assertSame(90, $user->refresh()->activity_retention_days);
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->patchJson(route('settings.privacy.update'), ['activity_retention_days' => 0, 'source_retention_days' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['activity_retention_days', 'source_retention_days']);
        $this->patch(route('settings.privacy.update'), ['activity_retention_days' => '', 'source_retention_days' => ''])->assertRedirect();
        $this->assertNull($user->refresh()->activity_retention_days);
    }

    public function test_source_cleanup_preserves_measurements_and_cannot_be_undone_by_reimport(): void
    {
        Storage::fake('activity_sources');
        $user = User::factory()->create();
        $user->forceFill(['source_retention_days' => 30])->save();
        $connection = ProviderConnection::factory()->for($user)->create();
        $payload = new ProviderActivity('synthetic-source', 'Synthetic ride', 'ride', CarbonImmutable::now()->subDays(60), raw: ['fake' => 'raw']);
        // Initially import with unlimited settings, then apply the user's new limit.
        $user->forceFill(['source_retention_days' => null])->save();
        $source = app(ActivityImporter::class)->import($connection, $payload);
        $source->forceFill(['source_file_path' => 'synthetic/old.fit', 'source_file_checksum' => hash('sha256', 'synthetic')])->save();
        Storage::disk('activity_sources')->put('synthetic/old.fit', 'synthetic');
        ActivityStream::factory()->for($source->activity)->create(['type' => 'power', 'data' => [100, 120]]);
        $analysis = AiAnalysis::factory()->for($user)->for($source->activity)->create();
        $user->forceFill(['source_retention_days' => 30])->save();
        $preview = app(DataRetention::class)->preview($user);
        $this->assertSame(['activities' => 0, 'sources' => 1, 'analyses' => 0], $preview);
        $this->actingAs($user)->post(route('settings.privacy.cleanup'), $this->confirmation($user))->assertRedirect();
        Storage::disk('activity_sources')->assertMissing('synthetic/old.fit');
        $this->assertNull($source->refresh()->raw_payload);
        $this->assertNotNull($source->purged_at);
        $this->assertDatabaseCount('activity_streams', 1);
        $this->assertDatabaseHas('ai_analyses', ['id' => $analysis->id]);
        // Even expanding the limit later must not silently restore explicitly removed originals.
        $user->forceFill(['source_retention_days' => null])->save();
        $imported = app(ActivityImporter::class)->import($connection, $payload);
        $this->assertNull($imported->raw_payload);
        $provider = $this->mock(ActivityFileProvider::class);
        $provider->shouldNotReceive('downloadActivityFile');
        app(ActivityFileArchiver::class)->archive($provider, $connection, $imported);
        $this->assertNull($imported->refresh()->source_file_path);
    }

    public function test_ride_cleanup_removes_dependents_and_user_plans_but_preserves_other_users_and_boundary(): void
    {
        Storage::fake('activity_sources');
        $this->freezeTime();
        $user = User::factory()->create();
        $user->forceFill(['activity_retention_days' => 30])->save();
        $old = Activity::factory()->for($user)->create(['started_at' => now()->subDays(31)]);
        $boundary = Activity::factory()->for($user)->create(['started_at' => now()->subDays(30)]);
        $foreign = Activity::factory()->create(['started_at' => now()->subYears(3)]);
        $source = ActivitySource::factory()->for($old)->create(['source_file_path' => 'synthetic/full.fit']);
        Storage::disk('activity_sources')->put($source->source_file_path, 'synthetic');
        ActivityStream::factory()->for($old)->create();
        AiAnalysis::factory()->for($user)->for($old)->create();
        AiAnalysis::factory()->for($user)->create(['activity_id' => null, 'type' => 'weekly_plan']);
        $foreignAnalysis = AiAnalysis::factory()->for($foreign->user)->for($foreign)->create();
        $this->actingAs($user)->postJson(route('settings.privacy.cleanup'), [])->assertUnprocessable();
        $this->assertDatabaseHas('activities', ['id' => $old->id]);
        $this->post(route('settings.privacy.cleanup'), $this->confirmation($user))->assertRedirect();
        $this->assertDatabaseMissing('activities', ['id' => $old->id]);
        $this->assertDatabaseMissing('activity_sources', ['id' => $source->id]);
        $this->assertDatabaseCount('activity_streams', 0);
        $this->assertDatabaseHas('activities', ['id' => $boundary->id]);
        $this->assertDatabaseHas('activities', ['id' => $foreign->id]);
        $this->assertSame(0, $user->aiAnalyses()->count());
        $this->assertDatabaseHas('ai_analyses', ['id' => $foreignAnalysis->id]);
        Storage::disk('activity_sources')->assertMissing('synthetic/full.fit');
        $connection = ProviderConnection::factory()->for($user)->create();
        $this->assertNull(app(ActivityImporter::class)->import($connection, new ProviderActivity('expired', 'Old', 'ride', CarbonImmutable::now()->subDays(31))));
    }

    public function test_cleanup_rejects_a_stale_preview(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['activity_retention_days' => 30])->save();
        $confirmation = $this->confirmation($user);
        $old = Activity::factory()->for($user)->create(['started_at' => now()->subDays(60)]);
        $this->actingAs($user)->postJson(route('settings.privacy.cleanup'), $confirmation)->assertUnprocessable()->assertJsonValidationErrors('confirm_cleanup');
        $this->assertDatabaseHas('activities', ['id' => $old->id]);
    }

    public function test_private_zone_validation_and_ownership(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('settings.privacy.zones.store'), ['name' => 'Synthetic home', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400])->assertRedirect();
        $zone = $owner->privacyZones()->firstOrFail();
        $this->postJson(route('settings.privacy.zones.store'), ['name' => 'Invalid', 'latitude' => 91, 'longitude' => 181, 'radius_m' => 99])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude', 'radius_m']);
        $this->actingAs(User::factory()->create())->delete(route('settings.privacy.zones.destroy', $zone->id))->assertNotFound();
        $this->assertDatabaseHas('privacy_zones', ['id' => $zone->id]);
        $this->actingAs($owner)->delete(route('settings.privacy.zones.destroy', $zone->id))->assertRedirect();
        $this->assertDatabaseMissing('privacy_zones', ['id' => $zone->id]);
    }

    public function test_file_removal_failure_keeps_the_ride_and_source_references_for_retry(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['activity_retention_days' => 30])->save();
        $activity = Activity::factory()->for($user)->create(['started_at' => now()->subDays(60)]);
        $source = ActivitySource::factory()->for($activity)->create(['source_file_path' => 'synthetic/retry.fit']);
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->once()->with('synthetic/retry.fit')->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with('synthetic/retry.fit')->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('activity_sources')->andReturn($disk);
        try {
            app(DataRetention::class)->purge($user, app(DataRetention::class)->snapshot($user));
            $this->fail('Cleanup must fail when a private file could not be removed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retry', $exception->getMessage());
        }
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertSame('synthetic/retry.fit', $source->refresh()->source_file_path);
        $this->assertNull($source->purged_at);
    }

    public function test_deleted_ai_and_enrichment_work_is_skipped(): void
    {
        $advisor = $this->mock(\App\Domain\Training\Contracts\TrainingAdvisor::class);
        $advisor->shouldNotReceive('analyzeActivity');
        (new \App\Jobs\GenerateActivityAnalysis('removed-analysis'))->handle($advisor);
        $weekly = $this->mock(\App\Domain\Training\Contracts\WeeklyTrainingPlanner::class);
        $weekly->shouldNotReceive('generateWeeklyPlan');
        (new \App\Jobs\GenerateWeeklyTrainingPlan('removed-plan'))->handle($weekly);
        $goal = $this->mock(\App\Domain\Training\Contracts\GoalTrainingPlanner::class);
        $goal->shouldNotReceive('generateGoalPlan');
        (new \App\Jobs\GenerateGoalTrainingPlan('removed-goal'))->handle($goal);
        $block = $this->mock(\App\Domain\Training\Contracts\TrainingBlockPlanner::class);
        $block->shouldNotReceive('generateTrainingBlock');
        (new \App\Jobs\GenerateTrainingBlock('removed-block'))->handle($block);
        $source = ActivitySource::factory()->create();
        $source->forceFill(['purged_at' => now(), 'source_file_path' => null])->save();
        $enricher = $this->mock(\App\Domain\Activities\Contracts\ActivitySourceEnricher::class);
        $enricher->shouldNotReceive('enrich');
        (new \App\Jobs\EnrichActivitySource($source))->handle($enricher);
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    private function confirmation(User $user): array
    {
        return ['confirm_cleanup' => '1', 'cleanup_snapshot' => app(DataRetention::class)->snapshot($user->refresh())];
    }
}
