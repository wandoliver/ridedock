<?php

namespace Tests\Feature;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Activities\Contracts\OAuthActivityProvider;
use App\Domain\Activities\Data\ProviderToken;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Domain\Activities\Services\ProviderTokenManager;
use App\Domain\Activities\Services\SyncProviderActivities;
use App\Domain\Operations\Exceptions\WorkDeferred;
use App\Domain\Operations\Services\AiUsage;
use App\Domain\Operations\Services\OperationsOverview;
use App\Domain\Operations\Services\ProviderOperations;
use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Data\TrainingAdvice;
use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Domain\Training\Services\ReserveAiAnalysis;
use App\Infrastructure\Ai\OpenAiTrainingAdvisor;
use App\Infrastructure\Providers\Wahoo\WahooProvider;
use App\Jobs\GenerateActivityAnalysis;
use App\Jobs\SyncProviderConnection;
use App\Models\Activity;
use App\Models\AiAnalysis;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalControlsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reservations_enforce_quotas_without_charging_duplicates_and_survive_analysis_cleanup(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        config(['operations.ai.monthly_activity_quota' => 1]);
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $reserve = app(ReserveAiAnalysis::class);
        $attributes = ['activity_id' => $activity->id, 'type' => 'activity', 'status' => AiAnalysisStatus::Pending,
            'context_schema_version' => 1, 'prompt_version' => 'test-v1', 'context' => []];
        $first = $reserve->reserve($user, $attributes);
        $this->assertSame($first->id, $reserve->reserve($user, $attributes)->id);
        $this->assertDatabaseCount('ai_usage_requests', 1);
        $first->delete();
        try {
            $reserve->reserve($user, $attributes);
            $this->fail('Cleanup must not reset the quota.');
        } catch (ValidationException $error) {
            $this->assertSame(__('operations.quota_exhausted'), $error->errors()['ai'][0]);
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-01T00:00:00Z'));
        $this->assertTrue($reserve->reserve($user, $attributes)->wasRecentlyCreated);
    }

    public function test_all_plan_features_share_one_quota_and_pending_capacity_is_enforced(): void
    {
        config(['operations.ai.monthly_plan_quota' => 1, 'operations.ai.pending_limit' => 1]);
        $user = User::factory()->create();
        $reserve = app(ReserveAiAnalysis::class);
        $attributes = ['type' => 'goal_plan', 'status' => AiAnalysisStatus::Pending, 'context_schema_version' => 1, 'prompt_version' => 'test-v1', 'context' => []];
        $analysis = $reserve->reserve($user, $attributes);
        foreach (['weekly_plan', 'training_block'] as $feature) {
            try {
                $reserve->reserve($user, array_replace($attributes, ['type' => $feature]));
                $this->fail('Every planning level must use the plan quota.');
            } catch (ValidationException $error) {
                $this->assertSame(__('operations.quota_exhausted'), $error->errors()['ai'][0]);
            }
        }
        $other = User::factory()->create();
        try {
            $reserve->reserve($other, $attributes);
            $this->fail('Shared pending capacity must be bounded.');
        } catch (ValidationException $error) {
            $this->assertSame(__('operations.capacity_full'), $error->errors()['ai'][0]);
        }
        $analysis->update(['status' => AiAnalysisStatus::Completed]);
        $this->assertTrue($reserve->reserve($other, $attributes)->wasRecentlyCreated);
    }

    public function test_quota_errors_are_localized_and_do_not_enqueue_work(): void
    {
        Queue::fake();
        config(['operations.ai.monthly_activity_quota' => 0]);
        $this->mock(TrainingAdvisor::class)->shouldReceive('isConfigured')->andReturn(true);
        $user = User::factory()->create(['locale' => 'de']);
        $activity = Activity::factory()->for($user)->create();
        $this->actingAs($user)->from(route('activities.show', $activity))->post(route('activities.ai-analysis.store', $activity))
            ->assertRedirect()->assertSessionHasErrors(['ai' => 'Deine monatliche KI-Quote ist ausgeschöpft. Im nächsten Monat kannst du neue Ergebnisse anfordern.']);
        Queue::assertNothingPushed();
        $this->assertDatabaseEmpty('ai_analyses');
    }

    public function test_real_worker_deferrals_do_not_spend_provider_failure_attempts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        config(['ai.queue_connection' => 'ai', 'operations.ai.requests_per_minute' => 1]);
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create();
        DB::table('ai_usage_attempts')->insert($this->attempt($user, ['status' => 'completed']));
        $advisor = $this->mock(TrainingAdvisor::class);
        $advisor->shouldReceive('analyzeActivity')->once()->andReturn($this->advice());
        GenerateActivityAnalysis::dispatch($analysis->id)->onConnection('ai')->onQueue('ai');
        for ($i = 0; $i < 4; $i++) {
            DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
            $this->artisan('queue:work', ['connection' => 'ai', '--queue' => 'ai', '--once' => true])->assertExitCode(0);
            $this->assertDatabaseCount('jobs', 1);
            $this->assertDatabaseEmpty('failed_jobs');
        }
        $this->travel(61)->seconds();
        DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
        $this->artisan('queue:work', ['connection' => 'ai', '--queue' => 'ai', '--once' => true])->assertExitCode(0);
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->refresh()->status);
        $this->assertDatabaseEmpty('jobs');
        $this->assertDatabaseCount('ai_usage_attempts', 2);
    }

    public function test_invalid_response_usage_is_costed_before_validation_and_monthly_spend_survives_deletion(): void
    {
        $this->prices();
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create();
        Http::fake(['https://api.openai.test/v1/responses' => Http::response([
            'id' => 'test-response', 'model' => 'test-model', 'output' => [],
            'usage' => ['input_tokens' => 100, 'input_tokens_details' => ['cached_tokens' => 40], 'output_tokens' => 50],
        ])]);
        $advisor = new OpenAiTrainingAdvisor(app(Factory::class), 'synthetic-key', 'test-model', 'https://api.openai.test/v1', 10);
        try {
            (new GenerateActivityAnalysis($analysis->id))->handle($advisor);
            $this->fail('Invalid output must fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('The AI provider returned no structured output.', $error->getMessage());
        }
        $attempt = DB::table('ai_usage_attempts')->first();
        $this->assertSame(100, $attempt->input_tokens);
        $this->assertSame(40, $attempt->cached_input_tokens);
        $this->assertSame(440, $attempt->cost_microusd);
        $this->assertSame('failed', $attempt->status);
        Http::assertSent(fn ($request): bool => $request['max_output_tokens'] === 12000);
        $analysis->delete();
        $user->delete();
        $this->assertDatabaseEmpty('ai_usage_attempts');
        $this->assertDatabaseEmpty('operation_events');
        $this->assertSame(0.00044, app(OperationsOverview::class)->usage()['cost_usd']);
        $this->assertSame(0, app(OperationsOverview::class)->usage()['unknown_costs']);
    }

    public function test_missing_usage_or_prices_remain_unknown_and_completed_redelivery_is_free(): void
    {
        $user = User::factory()->create();
        $analysis = AiAnalysis::factory()->for($user)->create();
        $advisor = $this->mock(TrainingAdvisor::class);
        $advisor->shouldReceive('analyzeActivity')->once()->andReturn($this->advice());
        $job = new GenerateActivityAnalysis($analysis->id);
        $job->handle($advisor);
        $job->handle($advisor);
        $this->assertDatabaseCount('ai_usage_attempts', 1);
        $this->assertNull(DB::table('ai_usage_attempts')->value('cost_microusd'));
        $this->assertSame(1, app(OperationsOverview::class)->usage($user->id)['unknown_costs']);
    }

    public function test_concurrent_analysis_is_deferred_before_an_external_call(): void
    {
        config(['operations.ai.concurrency' => 1]);
        $user = User::factory()->create();
        $analysis = AiAnalysis::factory()->for($user)->create();
        DB::table('ai_usage_attempts')->insert($this->attempt($user));
        $advisor = $this->mock(TrainingAdvisor::class);
        $advisor->shouldNotReceive('analyzeActivity');
        $this->expectException(WorkDeferred::class);
        (new GenerateActivityAnalysis($analysis->id))->handle($advisor);
    }

    public function test_provider_http_limits_are_shared_across_users_and_reset(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        config(['activity-providers.wahoo.rate_limits' => [300 => 1]]);
        Http::fake(['*' => Http::response(['workouts' => [], 'total' => 0])]);
        $provider = app(WahooProvider::class);
        $first = ProviderConnection::factory()->wahoo()->create();
        $second = ProviderConnection::factory()->wahoo()->create();
        $provider->activities($first);
        try {
            $provider->activities($second);
            $this->fail('Limits must be shared by all accounts.');
        } catch (WorkDeferred $error) {
            $this->assertSame(300, $error->retryAfter);
        }
        Http::assertSentCount(1);
        $this->travel(301)->seconds();
        $provider->activities($second);
        Http::assertSentCount(2);
    }

    public function test_wahoo_cooldown_obeys_reset_header_without_immediate_retry(): void
    {
        Http::fake(['*' => Http::response(['private' => 'DO_NOT_LOG_PROVIDER_BODY'], 429, [
            'Retry-After' => '120', 'X-RateLimit-Reset' => '300',
        ])]);
        $connection = ProviderConnection::factory()->wahoo()->create();
        try {
            app(SyncProviderActivities::class)->handle($connection);
            $this->fail('The provider must defer.');
        } catch (WorkDeferred $error) {
            $this->assertSame(300, $error->retryAfter);
        }
        $this->assertSame('waiting', $connection->refresh()->status->value);
        $this->assertNotNull($connection->sync_retry_at);
        $this->assertNull($connection->last_error);
        Http::assertSentCount(1);
        $this->assertSame(300, app(ProviderOperations::class)->cooldown('wahoo'));
        $this->assertDatabaseEmpty('operation_events');
    }

    public function test_sync_lock_blocks_overlapping_execution_and_token_refresh_rereads_rotated_tokens(): void
    {
        $user = User::factory()->create();
        $connection = ProviderConnection::factory()->for($user)->wahoo()->create(['token_expires_at' => now()->subMinute()]);
        $lock = app(ProviderOperations::class)->lock('provider-sync:'.$connection->id, 360);
        $this->assertTrue($lock->get());
        try {
            app(SyncProviderActivities::class)->handle($connection);
            $this->fail('A concurrent sync must not run.');
        } catch (WorkDeferred $error) {
            $this->assertSame(5, $error->retryAfter);
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
        $provider = \Mockery::mock(OAuthActivityProvider::class);
        $provider->shouldReceive('key')->andReturn(ProviderName::Wahoo);
        $provider->shouldReceive('refreshAccessToken')->once()->andReturn(new ProviderToken('rotated', 'new-refresh', CarbonImmutable::now()->addHour(), null));
        $manager = new ProviderTokenManager(new ProviderRegistry([$provider]));
        $fresh = $manager->ensureFresh($connection);
        $stale = $manager->ensureFresh($connection);
        $this->assertSame('rotated', $fresh->access_token);
        $this->assertSame('rotated', $stale->access_token);
    }

    public function test_sync_checkpoint_resumes_without_repeating_completed_hammerhead_details(): void
    {
        Queue::fake();
        config(['activity-providers.sync.store_source_files' => false, 'activity-providers.sync.max_pages' => 1]);
        Http::fake([
            '*activities?*' => Http::response(['data' => [['id' => 'synthetic-a'], ['id' => 'synthetic-b']], 'totalPages' => 1]),
            '*activities/synthetic-a' => Http::response(['id' => 'synthetic-a', 'name' => 'First', 'startedAt' => '2026-10-01T08:00:00Z']),
            '*activities/synthetic-b' => Http::response(['id' => 'synthetic-b', 'name' => 'Second', 'startedAt' => '2026-10-02T08:00:00Z']),
        ]);
        $connection = ProviderConnection::factory()->hammerhead()->create(['last_synced_at' => '2026-10-04T08:00:00Z']);
        try {
            app(SyncProviderActivities::class)->handle($connection);
            $this->fail('The bounded sync slice must yield.');
        } catch (WorkDeferred) {
            $this->assertSame('1:1', $connection->refresh()->sync_cursor);
        }
        app(SyncProviderActivities::class)->handle($connection);
        $this->assertNull($connection->refresh()->sync_cursor);
        $this->assertDatabaseCount('activities', 2);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'startDate=2026-10-02'));
        $lists = Http::recorded(fn ($request): bool => str_contains($request->url(), 'activities?'));
        $this->assertCount(2, $lists);
        foreach ($lists as [$request]) {
            $this->assertStringContainsString('startDate=2026-10-02', $request->url());
        }
    }

    public function test_provider_queue_releases_and_resumes_the_requested_sync(): void
    {
        config(['queue.default' => 'database']);
        Http::fake(['*' => Http::sequence()->push([], 429, ['Retry-After' => '60'])->push(['workouts' => [], 'total' => 0])]);
        $connection = ProviderConnection::factory()->wahoo()->create();
        SyncProviderConnection::dispatch($connection);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseEmpty('failed_jobs');
        $this->travel(61)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertExitCode(0);
        $this->assertSame('active', $connection->refresh()->status->value);
        $this->assertDatabaseEmpty('jobs');
    }

    public function test_hosted_approval_gate_blocks_network_and_requires_a_shared_cache(): void
    {
        config(['operations.hosted' => true]);
        try {
            app(WahooProvider::class)->activities(ProviderConnection::factory()->wahoo()->create());
            $this->fail('A node-local cache is unsuitable for hosted operation.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('shared lock-capable cache', $error->getMessage());
        }
        config(['operations.cache_store' => 'database']);
        try {
            app(ProviderOperations::class)->assertAllowed('wahoo');
            $this->fail('External approvals must be confirmed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('approval and terms review', $error->getMessage());
        }
        config(['activity-providers.wahoo.hosted_approved' => true, 'activity-providers.wahoo.terms_reviewed_at' => '2026-10-06']);
        app(ProviderOperations::class)->assertAllowed('wahoo');
        Http::assertNothingSent();
    }

    public function test_operations_are_admin_only_and_personal_usage_is_owner_scoped(): void
    {
        $owner = User::factory()->create(['locale' => 'de']);
        $other = User::factory()->create();
        DB::table('ai_usage_attempts')->insert($this->attempt($other, ['model' => 'OTHER_USERS_MODEL', 'status' => 'completed']));
        $this->get(route('admin.operations'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('admin.operations'))->assertForbidden();
        $this->get(route('settings.ai-usage'))->assertOk()->assertSee('KI-Nutzung')->assertDontSee('OTHER_USERS_MODEL')->assertHeader('Cache-Control', 'no-store, private');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('admin.operations'))->assertOk()->assertSee('OTHER_USERS_MODEL')->assertDontSee('access_token')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_budget_monitor_deduplicates_warnings_and_exports_no_private_context(): void
    {
        $this->prices();
        config(['operations.ai.cached_input_usd_per_million' => 2]);
        config(['operations.ai.monthly_budget_usd' => 0.0005, 'operations.ai.budget_warning_percent' => 80]);
        $user = User::factory()->create();
        $analysis = AiAnalysis::factory()->for($user)->create(['context' => ['private' => 'PRIVATE_CONTEXT']]);
        app(AiUsage::class)->run($analysis, fn (): TrainingAdvice => $this->advice());
        $this->assertTrue(app(OperationsOverview::class)->usage()['budget_warning']);
        $this->artisan('app:monitor-operations', ['--json' => true])->assertExitCode(1);
        $this->assertDatabaseEmpty('operation_events');
        $this->artisan('app:monitor-operations', ['--record' => true])->assertExitCode(1);
        $this->artisan('app:monitor-operations', ['--record' => true])->assertExitCode(1);
        $this->assertSame(1, DB::table('operation_events')->where('code', 'ai_budget_warning')->count());
        $this->assertStringNotContainsString('PRIVATE_CONTEXT', json_encode(app(OperationsOverview::class)->snapshot()));
    }

    private function prices(): void
    {
        config(['operations.ai.pricing_model' => 'test-model', 'operations.ai.input_usd_per_million' => 2,
            'operations.ai.cached_input_usd_per_million' => 0.5, 'operations.ai.output_usd_per_million' => 6]);
    }

    public function test_only_admins_can_set_per_user_quotas_and_blank_restores_the_default(): void
    {
        $member = User::factory()->create();
        $values = ['ai_activity_quota' => 0, 'ai_plan_quota' => 7];
        $this->actingAs($member)->patch(route('admin.users.quotas', $member), $values)->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->patch(route('admin.users.quotas', $member), $values)->assertRedirect(route('admin.users.index'));
        $this->assertSame(0, $member->refresh()->aiQuota(true));
        $this->assertSame(7, $member->aiQuota(false));
        $this->assertDatabaseHas('account_audit_events', ['event' => 'ai_quotas_updated', 'subject_user_id' => $member->id]);
        $this->patch(route('admin.users.quotas', $member), ['ai_activity_quota' => -1, 'ai_plan_quota' => 7])->assertSessionHasErrors('ai_activity_quota');
        $this->patch(route('admin.users.quotas', $member), ['ai_activity_quota' => '', 'ai_plan_quota' => ''])->assertRedirect();
        $this->assertSame(100, $member->refresh()->aiQuota(true));
        $this->assertSame(30, $member->aiQuota(false));
    }

    private function advice(): TrainingAdvice
    {
        return new TrainingAdvice(['summary' => 'Synthetic result'], 'openai', 'test-model', 'synthetic-response', 100, 50);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function attempt(User $user, array $overrides = []): array
    {
        return array_replace(['user_id' => $user->id, 'feature' => 'activity', 'prompt_version' => 'test-v1',
            'provider' => 'openai', 'model' => 'test-model', 'status' => 'running', 'created_at' => now()], $overrides);
    }
}
