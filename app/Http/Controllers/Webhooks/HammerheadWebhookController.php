<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use App\Http\Controllers\Controller;
use App\Jobs\ImportProviderActivity;
use App\Models\ProviderConnection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HammerheadWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('activity-providers.hammerhead.webhook_secret');
        $provided = preg_replace('/^sha256=/i', '', (string) $request->header('X-Hmac-Signature'));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless($secret !== '' && hash_equals($expected, $provided), 401);

        $payload = $request->validate(['activityId' => ['required', 'string'], 'userId' => ['required', 'string']]);
        $connection = ProviderConnection::query()
            ->where('provider', ProviderName::Hammerhead)
            ->where('status', '!=', ConnectionStatus::Revoked)
            ->where('external_user_id', $payload['userId'])
            ->first();

        if ($connection) {
            ImportProviderActivity::dispatch($connection, $payload['activityId']);
        }

        return response()->noContent(200);
    }
}
