<?php

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Jobs\ImportProviderActivity;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HammerheadWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_signature_queues_activity_import(): void
    {
        config()->set('activity-providers.hammerhead.webhook_secret', 'webhook-secret');
        $connection = ProviderConnection::factory()
            ->for(User::factory())
            ->hammerhead()
            ->create(['external_user_id' => '1000']);
        Queue::fake([ImportProviderActivity::class]);
        $content = json_encode(['activityId' => '1000.activity.abcd', 'userId' => '1000'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $content, 'webhook-secret');

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/hammerhead',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HMAC_SIGNATURE' => $signature],
            content: $content,
        );

        $response->assertOk();
        Queue::assertPushed(ImportProviderActivity::class, function (ImportProviderActivity $job) use ($connection): bool {
            return $job->providerConnection->is($connection) && $job->externalId === '1000.activity.abcd';
        });
    }

    public function test_returns_401_when_signature_is_invalid(): void
    {
        config()->set('activity-providers.hammerhead.webhook_secret', 'webhook-secret');
        Queue::fake([ImportProviderActivity::class]);

        $response = $this->postJson('/api/v1/webhooks/hammerhead', [
            'activityId' => '1000.activity.abcd',
            'userId' => '1000',
        ], ['X-Hmac-Signature' => 'invalid']);

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }
}
