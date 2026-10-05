<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_updates_rpe_and_notes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['activities:write']);
        $activity = $this->createActivityFor($user);

        $response = $this->patchJson("/api/v1/activities/{$activity->id}", [
            'rpe' => 3,
            'notes' => 'Easy',
        ]);

        $response->assertOk()->assertJsonPath('data.rpe', 3);
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'rpe' => 3,
            'notes' => 'Easy',
        ]);
    }

    public function test_analysis_export_returns_normalized_json(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['activities:read']);
        $this->createActivityFor($user, ['rpe' => 3]);

        $response = $this->getJson('/api/v1/analysis/export?from=2026-08-01&to=2026-08-31');

        $response->assertOk()
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('activity_count', 1)
            ->assertJsonPath('activities.0.distance_km', 40)
            ->assertJsonPath('activities.0.rpe', 3)
            ->assertJsonMissingPath('activities.0.summary_polyline');
    }

    public function test_returns_401_when_activity_token_is_missing(): void
    {
        $this->getJson('/api/v1/activities')->assertUnauthorized();
    }

    public function test_csv_export_is_disabled_without_a_download_or_json_fallback(): void
    {
        $user = User::factory()->create();
        $this->createActivityFor($user, ['name' => 'PRIVATE_DISABLED_EXPORT']);
        Sanctum::actingAs($user, ['activities:read']);
        $response = $this->getJson('/api/v1/analysis/export?format=csv')->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertStringNotContainsString('PRIVATE_DISABLED_EXPORT', $response->getContent());
    }

    public function test_returns_403_when_token_lacks_read_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['activities:write']);

        $this->getJson('/api/v1/activities')->assertForbidden();
    }

    public function test_returns_404_for_another_users_activity(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $activity = $this->createActivityFor($owner);
        Sanctum::actingAs($otherUser, ['activities:read']);

        $this->getJson("/api/v1/activities/{$activity->id}")->assertNotFound();
    }

    public function test_returns_422_and_preserves_activity_when_rpe_is_invalid(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['activities:write']);
        $activity = $this->createActivityFor($user, ['rpe' => 4]);

        $response = $this->patchJson("/api/v1/activities/{$activity->id}", ['rpe' => 11]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['rpe']);
        $this->assertSame(4, $activity->fresh()->rpe);
    }

    public function test_ignores_unexpected_activity_attributes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['activities:write']);
        $activity = $this->createActivityFor($user, ['name' => 'Original']);

        $response = $this->patchJson("/api/v1/activities/{$activity->id}", [
            'rpe' => 5,
            'name' => 'Injected name',
        ]);

        $response->assertOk();
        $this->assertSame('Original', $activity->fresh()->name);
        $this->assertSame(5, $activity->fresh()->rpe);
    }

    /** @param array<string, mixed> $attributes */
    private function createActivityFor(User $user, array $attributes = []): Activity
    {
        $connection = ProviderConnection::factory()->for($user)->create();
        $activity = Activity::factory()->for($user)->create([
            'name' => 'Endurance Ride',
            'started_at' => '2026-08-30T06:00:00Z',
            'distance_m' => 40000,
            'moving_time_s' => 5400,
            'average_speed_mps' => 7.4074,
            'average_heart_rate' => 138,
            ...$attributes,
        ]);
        ActivitySource::factory()
            ->for($activity)
            ->for($connection, 'connection')
            ->create(['external_id' => '99']);

        return $activity;
    }
}
