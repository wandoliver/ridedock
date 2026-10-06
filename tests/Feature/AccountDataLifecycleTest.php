<?php

namespace Tests\Feature;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Services\ActivityImporter;
use App\Domain\Privacy\Services\AccountQueueData;
use App\Domain\Privacy\Services\DeleteAccount;
use App\Jobs\EnrichActivitySource;
use App\Jobs\GenerateActivityAnalysis;
use App\Jobs\ImportProviderActivity;
use App\Jobs\SyncProviderConnection;
use App\Models\Activity;
use App\Models\ActivitySource;
use App\Models\ActivityStream;
use App\Models\AiAnalysis;
use App\Models\AthleteProfile;
use App\Models\ProviderConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class AccountDataLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_personal_archive_contains_full_owned_data_and_originals_without_credentials(): void
    {
        Storage::fake('activity_sources');
        $user = User::factory()->create();
        $foreign = User::factory()->create(['name' => 'Foreign account sentinel']);
        $activity = Activity::factory()->for($user)->create(['notes' => 'Personal note sentinel']);
        ActivityStream::factory()->for($activity)->create(['type' => 'latlng', 'data' => [[0, 0], [0.1, 0.1]]]);
        $connection = ProviderConnection::factory()->for($user)->create(['access_token' => 'oauth-secret-sentinel']);
        $source = ActivitySource::factory()->for($activity)->for($connection, 'connection')->create(['source_file_path' => $user->id.'/hammerhead/synthetic.fit', 'raw_payload' => ['synthetic' => true]]);
        Storage::disk('activity_sources')->put($source->source_file_path, 'Synthetic original sentinel');
        AthleteProfile::factory()->for($user)->create();
        $user->privacyZones()->create(['name' => 'Private zone sentinel', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        AiAnalysis::factory()->for($user)->create(['activity_id' => null, 'type' => 'weekly_plan', 'output' => ['personal' => 'Plan sentinel']]);
        $token = $user->createToken('Synthetic API key');
        $this->actingAs($user)->postJson(route('settings.privacy.export'), ['export_password' => 'wrong', 'include_sensitive_data' => 1])->assertUnprocessable();
        $this->postJson(route('settings.privacy.export'), ['export_password' => 'password'])->assertUnprocessable();
        $response = $this->post(route('settings.privacy.export'), ['export_password' => 'password', 'include_sensitive_data' => 1])->assertOk()->assertDownload('ridedock-personal-data.zip');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $file = tmpfile();
        fwrite($file, $response->streamedContent());
        fflush($file);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(stream_get_meta_data($file)['uri']));
        $all = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $all .= $zip->getFromIndex($index);
        }
        $this->assertSame('Synthetic original sentinel', $zip->getFromName('files/'.$source->id.'.fit'), $all);
        foreach (['Personal note sentinel', 'Private zone sentinel', 'Plan sentinel'] as $value) {
            $this->assertStringContainsString($value, $all);
        }
        foreach (['Foreign account sentinel', 'oauth-secret-sentinel', $user->password, $token->plainTextToken, $token->accessToken->token] as $value) {
            $this->assertStringNotContainsString($value, $all);
        }
        $streams = json_decode(trim($zip->getFromName('streams.ndjson')), true);
        $this->assertSame([[0, 0], [0.1, 0.1]], $streams['data']);
        $zip->close();
        fclose($file);
        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
    }

    public function test_confirmed_deletion_removes_all_owned_rows_files_tokens_sessions_and_jobs(): void
    {
        Storage::fake('activity_sources');
        config(['queue.default' => 'database', 'ai.queue_connection' => 'ai']);
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $connection = ProviderConnection::factory()->for($user)->create();
        $source = ActivitySource::factory()->for($activity)->for($connection, 'connection')->create(['source_file_path' => $user->id.'/synthetic.fit']);
        ActivityStream::factory()->for($activity)->create();
        AthleteProfile::factory()->for($user)->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create();
        $user->privacyZones()->create(['name' => 'Synthetic', 'latitude' => 0, 'longitude' => 0, 'radius_m' => 400]);
        $token = $user->createToken('Synthetic');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'synthetic', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'synthetic-session', 'user_id' => $user->id, 'payload' => 'synthetic', 'last_activity' => time()]);
        DB::table('account_audit_events')->insert(['subject_user_id' => $user->id, 'actor_user_id' => $foreign->id, 'event' => 'synthetic', 'created_at' => now()]);
        DB::table('user_oauth_states')->insert(['state_hash' => hash('sha256', 'synthetic'), 'user_id' => $user->id, 'cache_key' => Crypt::encryptString('synthetic-cache-state'), 'expires_at' => now()->addMinutes(10)]);
        Cache::put('synthetic-cache-state', ['user_id' => $user->id], 600);
        Storage::disk('activity_sources')->put($source->source_file_path, 'synthetic');
        Storage::disk('activity_sources')->put($user->id.'/orphan.fit', 'synthetic orphan');
        Storage::disk('activity_sources')->put($foreign->id.'/keep.fit', 'foreign');
        foreach ([new SyncProviderConnection($connection), new ImportProviderActivity($connection, 'synthetic'), new EnrichActivitySource($source), new GenerateActivityAnalysis($analysis->id)] as $job) {
            Queue::connection('database')->push($job);
        }
        $foreignConnection = ProviderConnection::factory()->for($foreign)->create();
        Queue::connection('database')->push(new ImportProviderActivity($foreignConnection, 'foreign'));
        $payload = DB::table('jobs')->first()->payload;
        $this->assertSame($user->id, json_decode($payload, true)['lifecycle_user_id']);
        app('queue.failer')->log('database', 'default', $payload, new \RuntimeException('Synthetic failure'));
        $this->actingAs($user)->deleteJson(route('settings.privacy.account.destroy'), ['delete_password' => 'wrong', 'confirm_delete_account' => 1])->assertUnprocessable();
        $this->deleteJson(route('settings.privacy.account.destroy'), ['delete_password' => 'password'])->assertUnprocessable();
        $this->delete(route('settings.privacy.account.destroy'), ['delete_password' => 'password', 'confirm_delete_account' => 1])->assertRedirect(route('login'));
        $this->assertGuest();
        foreach (['users', 'activities', 'provider_connections', 'athlete_profiles', 'privacy_zones', 'ai_analyses'] as $table) {
            $this->assertDatabaseMissing($table, [$table === 'users' ? 'id' : 'user_id' => $user->id]);
        }
        foreach (['activity_sources', 'activity_streams', 'sessions', 'password_reset_tokens', 'account_audit_events', 'user_oauth_states', 'failed_jobs', 'personal_access_tokens'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('jobs', 1);
        Storage::disk('activity_sources')->assertMissing($source->source_file_path);
        Storage::disk('activity_sources')->assertMissing($user->id.'/orphan.fit');
        Storage::disk('activity_sources')->assertExists($foreign->id.'/keep.fit');
        $this->assertNull(Cache::get('synthetic-cache-state'));
        $this->assertNull(app('queue.failer')->log('database', 'default', $payload, new \RuntimeException('Late failure')));
        Queue::connection('database')->push(new ImportProviderActivity($connection, 'late-synthetic'));
        Queue::connection('database')->push(new GenerateActivityAnalysis($analysis->id));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNull(app(ActivityImporter::class)->import($connection, new ProviderActivity('late', 'Synthetic', 'ride', CarbonImmutable::now())));
        $this->withToken($token->plainTextToken)->getJson('/api/v1/activities')->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
    }

    public function test_legacy_job_ownership_is_resolved_without_unserializing_payloads(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $connection = ProviderConnection::factory()->for($user)->create();
        $activity = Activity::factory()->for($user)->create();
        $source = ActivitySource::factory()->for($activity)->for($connection, 'connection')->create();
        $analysis = AiAnalysis::factory()->for($user)->for($activity)->create();
        foreach ([new SyncProviderConnection($connection), new EnrichActivitySource($source), new GenerateActivityAnalysis($analysis->id)] as $job) {
            Queue::connection('database')->push($job);
        }
        foreach (DB::table('jobs')->get() as $job) {
            $payload = json_decode($job->payload, true);
            unset($payload['lifecycle_user_id']);
            $this->assertSame($user->id, app(AccountQueueData::class)->owner($payload));
            DB::table('jobs')->where('id', $job->id)->update(['payload' => json_encode($payload)]);
        }
        app(AccountQueueData::class)->remove($user);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_file_failure_preserves_references_for_retry(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();
        $connection = ProviderConnection::factory()->for($user)->create();
        $source = ActivitySource::factory()->for($activity)->for($connection, 'connection')->create(['source_file_path' => $user->id.'/retry.fit']);
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('allFiles')->andReturn([]);
        $disk->shouldReceive('exists')->andReturnTrue();
        $disk->shouldReceive('delete')->andReturnFalse();
        Storage::shouldReceive('disk')->andReturn($disk);
        try {
            app(DeleteAccount::class)->handle($user);
            $this->fail('Deletion must fail if a file cannot be removed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retry', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('activity_sources', ['id' => $source->id, 'source_file_path' => $source->source_file_path]);
    }

    public function test_retention_preview_and_execution_respect_boundaries_activity_and_other_data(): void
    {
        Storage::fake('activity_sources');
        Storage::fake('lifecycle_backups');
        Storage::fake('lifecycle_logs');
        config(['data-lifecycle.log_directory' => Storage::disk('lifecycle_logs')->path('')]);
        Storage::disk('lifecycle_logs')->put('laravel-synthetic-old.log', 'Synthetic old log');
        Storage::disk('lifecycle_logs')->put('laravel-synthetic-current.log', 'Synthetic current log');
        touch(Storage::disk('lifecycle_logs')->path('laravel-synthetic-old.log'), now()->subDays(15)->timestamp);
        $this->freezeTime();
        $old = User::factory()->create(['last_active_at' => now()->subDays(731)]);
        $boundary = User::factory()->create(['last_active_at' => now()->subDays(730)]);
        $admin = User::factory()->create(['last_active_at' => now()->subYears(5), 'role' => UserRole::Admin]);
        $pending = User::factory()->create(['status' => UserStatus::Pending, 'created_at' => now()->subDays(31)]);
        $recent = User::factory()->create(['last_active_at' => now()]);
        DB::table('account_audit_events')->insert(['event' => 'synthetic-expired', 'created_at' => now()->subDays(91)]);
        DB::table('failed_jobs')->insert(['uuid' => 'synthetic-expired', 'connection' => 'database', 'queue' => 'ai', 'payload' => '{}', 'exception' => 'synthetic', 'failed_at' => now()->subDays(8)]);
        Storage::disk('lifecycle_backups')->put('expired.zip', 'synthetic encrypted-backup placeholder');
        Storage::disk('lifecycle_backups')->put('current.zip', 'synthetic');
        touch(Storage::disk('lifecycle_backups')->path('expired.zip'), now()->subDays(31)->timestamp);
        $this->artisan('app:prune-data')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $old->id]);
        Storage::disk('lifecycle_backups')->assertExists('expired.zip');
        $this->artisan('app:prune-data --execute')->assertSuccessful();
        foreach ([$old, $pending] as $user) {
            $this->assertDatabaseMissing('users', ['id' => $user->id]);
        }
        foreach ([$boundary, $admin, $recent] as $user) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('account_audit_events', 0);
        Storage::disk('lifecycle_backups')->assertMissing('expired.zip');
        Storage::disk('lifecycle_backups')->assertExists('current.zip');
        Storage::disk('lifecycle_logs')->assertMissing('laravel-synthetic-old.log');
        Storage::disk('lifecycle_logs')->assertExists('laravel-synthetic-current.log');
    }

    public function test_authenticated_activity_updates_inactivity_timestamp_and_passwords_are_not_flashed(): void
    {
        $user = User::factory()->create(['last_active_at' => now()->subYears(3)]);
        $this->actingAs($user)->get(route('settings.privacy'))->assertOk()->assertSee('Persönlichen Gesamtexport');
        $this->assertTrue($user->refresh()->last_active_at->greaterThan(now()->subMinute()));
        $this->from(route('settings.privacy'))->post(route('settings.privacy.export'), ['export_password' => 'secret-invalid-sentinel'])
            ->assertRedirect()->assertSessionMissing('_old_input.export_password');
        $this->from(route('settings.privacy'))->delete(route('settings.privacy.account.destroy'), ['delete_password' => 'secret-invalid-sentinel'])
            ->assertRedirect()->assertSessionMissing('_old_input.delete_password');
    }
}
