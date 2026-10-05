<?php

namespace Tests\Feature;

use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Domain\Activities\Services\RecordedGearShifts;
use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GearShiftingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function fitEncodings(): array
    {
        return ['native packed data' => [false], 'described developer data' => [true]];
    }

    #[DataProvider('fitEncodings')]
    public function test_generated_fit_is_decoded_stored_and_displayed_idempotently(bool $developerFields): void
    {
        $source = $this->syntheticSource($developerFields);
        $user = $source->activity->user;
        $activity = $source->activity;
        $url = route('activities.fit.reprocess', $activity);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($user)->post($url, ['return_to' => '/activities?page=2'])
                ->assertRedirect(route('activities.show', ['activity' => $activity->id, 'return_to' => '/activities?page=2']).'#measurements')
                ->assertSessionHasNoErrors()->assertSessionHas('status');
        }

        $activity->refresh();
        $this->assertSame(1000.0, $activity->distance_m);
        $this->assertSame(120, $activity->moving_time_s);
        $this->assertSame('Synthetic notes', $activity->notes);
        $this->assertSame(6, $activity->rpe);
        $this->assertSame('0.5.0', $source->fresh()->metadata['fit_parser']['normalizer_version']);
        $this->assertSame(0, $activity->metadata['fit_parser']['shift_events_ignored']);
        $this->assertSame(4, $activity->events()->where('type', 'shift')->count());
        $this->assertSame([180, 220], array_column($activity->streams()->where('type', 'power')->firstOrFail()->data, 'value'));
        $event = $activity->events()->where('type', 'shift')->orderBy('elapsed_seconds')->firstOrFail();
        $this->assertSame(10, $event->elapsed_seconds);
        $this->assertSame('rear', $event->data['direction']);
        $this->assertSame(48, $event->data['front_gear_teeth']);
        $this->assertSame(7, $event->data['rear_gear_number']);
        $this->assertSame(21, $event->data['rear_gear_teeth']);

        $this->get(route('activities.show', $activity))->assertOk()
            ->assertViewHas('gearShifts', fn (array $shifts): bool => $shifts['total'] === 4 && $shifts['front'] === 1 && $shifts['rear'] === 3 && $shifts['rows'][0]['ratio'] === 2.29)
            ->assertSee('Schaltinformationen')->assertSee('Gangwechsel im Verlauf')
            ->assertSee('0:00:10')->assertSee('48 Zähne')->assertSee('21 Zähne')->assertSee('2,29')
            ->assertSee('FIT-Datei erneut auswerten')->assertSee($url, false);
        Http::assertNothingSent();
    }

    public function test_reprocessing_requires_authentication_and_activity_ownership(): void
    {
        $source = $this->syntheticSource();
        $this->mock(ActivitySourceEnricher::class)->shouldNotReceive('enrich');
        $url = route('activities.fit.reprocess', $source->activity);
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
        $this->assertSame(0, $source->activity->events()->count());
    }

    public function test_purged_sources_cannot_be_reprocessed_or_redownloaded(): void
    {
        $source = $this->syntheticSource();
        $source->forceFill(['purged_at' => now(), 'source_file_path' => null, 'source_file_checksum' => null])->save();
        $this->mock(ActivitySourceEnricher::class)->shouldNotReceive('enrich');
        $this->actingAs($source->activity->user)->post(route('activities.fit.reprocess', $source->activity))
            ->assertSessionHasErrors('fit');
        $this->get(route('activities.show', $source->activity))->assertOk()
            ->assertViewHas('canReprocessFit', false)->assertDontSee('FIT-Datei erneut auswerten');
        Http::assertNothingSent();
    }

    public function test_source_connection_must_belong_to_the_activity_owner(): void
    {
        $source = $this->syntheticSource();
        $source->connection->update(['user_id' => User::factory()->create()->id]);
        $this->mock(ActivitySourceEnricher::class)->shouldNotReceive('enrich');
        $this->actingAs($source->activity->user)->post(route('activities.fit.reprocess', $source->activity))
            ->assertSessionHasErrors('fit');
    }

    public function test_corrupt_original_preserves_previously_saved_data_and_feedback(): void
    {
        $source = $this->syntheticSource();
        $this->actingAs($source->activity->user)->post(route('activities.fit.reprocess', $source->activity))->assertSessionHasNoErrors();
        $previous = $source->activity->events()->orderBy('id')->get()->toArray();
        Storage::disk('activity_sources')->put($source->source_file_path, 'synthetic broken FIT');
        // The checksum matches the changed bytes: this exercises parser failure, not just checksum rejection.
        $source->update(['source_file_checksum' => hash('sha256', 'synthetic broken FIT')]);

        $this->post(route('activities.fit.reprocess', $source->activity), ['return_to' => 'https://example.test'])
            ->assertRedirect(route('activities.show', $source->activity).'#measurements')
            ->assertSessionHasErrors(['fit' => __('shifts.failed')]);
        $this->assertSame($previous, $source->activity->events()->orderBy('id')->get()->toArray());
        $this->assertSame('Synthetic notes', $source->activity->fresh()->notes);
        $this->assertSame(6, $source->activity->fresh()->rpe);
    }

    public function test_missing_gears_stay_unknown_and_timeline_is_bounded_without_losing_counts(): void
    {
        $activity = Activity::factory()->create(['started_at' => '2026-08-30T07:00:00Z']);
        ActivityEvent::factory()->for($activity)->create([
            'elapsed_seconds' => null, 'recorded_at' => '2026-08-30T07:00:30Z',
            'data' => ['direction' => 'rear', 'rear_gear_number' => 0, 'rear_gear_teeth' => '21'],
        ]);
        ActivityEvent::factory()->for($activity)->create([
            'elapsed_seconds' => 10, 'recorded_at' => null, 'data' => ['direction' => 'front', 'front_gear_number' => 1],
        ]);
        ActivityEvent::factory()->count(199)->for($activity)->create(['elapsed_seconds' => 60, 'data' => []]);
        $report = app(RecordedGearShifts::class)->report($activity);
        $this->assertSame(201, $report['total']);
        $this->assertSame(199, $report['unknown']);
        $this->assertCount(200, $report['rows']);
        $this->assertTrue($report['truncated']);
        $this->assertSame(1, $report['rows'][0]['front_gear_number']);
        $this->assertNull($report['rows'][0]['ratio']);
        $this->assertNull($report['rows'][1]['rear_gear_number']);
        $this->assertNull($report['rows'][1]['rear_gear_teeth']);
        $this->actingAs($activity->user)->get(route('activities.show', $activity))->assertOk()
            ->assertSee('ersten 200 aufgezeichneten Gangwechsel');
    }

    public function test_english_empty_state_does_not_claim_missing_gears_are_available(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $activity = Activity::factory()->for($user)->create();
        $this->actingAs($user)->get(route('activities.show', $activity))->assertOk()
            ->assertSee('Gear shifting')->assertSee('No usable gear shifting data recorded.')
            ->assertDontSee('Reprocess FIT file');
    }

    private function syntheticSource(bool $developerFields = false): ActivitySource
    {
        Storage::fake('activity_sources');
        config()->set('activity-providers.sync.source_file_disk', 'activity_sources');
        $command = ['node', base_path('tests/Fixtures/shifting-fit.js')];
        if ($developerFields) {
            $command[] = '--developer-fields';
        }
        $process = new Process($command);
        $process->mustRun();
        $contents = $process->getOutput();
        $user = User::factory()->create(['locale' => 'de']);
        $activity = Activity::factory()->for($user)->create(['notes' => 'Synthetic notes', 'rpe' => 6]);
        $connection = ProviderConnection::factory()->for($user)->create();
        $path = "{$user->id}/synthetic/shifting.fit";
        Storage::disk('activity_sources')->put($path, $contents);

        return ActivitySource::factory()->for($activity)->for($connection, 'connection')->create([
            'source_file_path' => $path, 'source_file_checksum' => hash('sha256', $contents),
        ]);
    }
}
