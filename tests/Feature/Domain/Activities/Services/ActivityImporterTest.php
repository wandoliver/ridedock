<?php

namespace Tests\Feature\Domain\Activities\Services;

use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ActivityImporter;
use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActivityImporterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_import_is_idempotent_and_merges_sources_from_different_providers(): void
    {
        $user = User::factory()->create();
        $fileImport = $this->connection($user, ProviderName::File);
        $hammerhead = $this->connection($user, ProviderName::Hammerhead);
        $importer = app(ActivityImporter::class);
        $startedAt = CarbonImmutable::parse('2026-08-30T06:00:00Z');

        $filePayload = new ProviderActivity(
            externalId: 'import.fit',
            name: 'Morning Ride',
            type: 'ride',
            startedAt: $startedAt,
            distanceM: 50020,
            movingTimeS: 7205,
            averageHeartRate: 142,
            raw: ['filename' => 'import.fit'],
        );

        $source = $importer->import($fileImport, $filePayload);
        $source->activity->update(['rpe' => 4, 'notes' => 'Felt easy']);
        $importer->import($fileImport, $filePayload);

        $hammerheadPayload = new ProviderActivity(
            externalId: 'user.activity.abc',
            name: 'Primary Karoo title',
            type: 'ride',
            startedAt: $startedAt->addSeconds(10),
            distanceM: 50000,
            movingTimeS: 7200,
            raw: ['id' => 'user.activity.abc'],
        );
        $importer->import($hammerhead, $hammerheadPayload);

        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('activity_sources', 2);

        $activity = Activity::firstOrFail();
        $this->assertSame('Primary Karoo title', $activity->name);
        $this->assertSame(142, $activity->average_heart_rate);
        $this->assertSame(4, $activity->rpe);
        $this->assertSame('Felt easy', $activity->notes);
        $this->assertSame(2, ActivitySource::where('activity_id', $activity->id)->count());
    }

    private function connection(User $user, ProviderName $provider): ProviderConnection
    {
        return ProviderConnection::factory()->for($user)->create([
            'provider' => $provider,
            'external_user_id' => $provider->value.'-user',
        ]);
    }
}
