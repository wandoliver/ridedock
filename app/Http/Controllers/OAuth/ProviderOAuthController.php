<?php

namespace App\Http\Controllers\OAuth;

use App\Domain\Activities\Contracts\OAuthActivityProvider;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderConnection;
use App\Models\ProviderConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProviderOAuthController extends Controller
{
    public function connect(Request $request, string $provider, ProviderRegistry $providers): RedirectResponse
    {
        $providerName = ProviderName::tryFrom($provider) ?? throw new BadRequestHttpException('Unknown provider.');
        $adapter = $providers->get($providerName);

        if (! $adapter instanceof OAuthActivityProvider) {
            throw new BadRequestHttpException('This provider does not support OAuth.');
        }

        $state = Str::random(64);
        Cache::put("provider-oauth-state:{$state}", [
            'user_id' => $request->user()->id,
            'provider' => $providerName->value,
        ], now()->addMinutes(10));

        return redirect()->away($adapter->authorizationUrl($state));
    }

    public function callback(Request $request, string $provider, ProviderRegistry $providers): RedirectResponse
    {
        if ($request->filled('error')) {
            throw new BadRequestHttpException('Provider authorization was denied.');
        }

        $providerName = ProviderName::tryFrom($provider) ?? throw new BadRequestHttpException('Unknown provider.');

        if (! $request->filled(['code', 'state'])) {
            throw new BadRequestHttpException('Invalid or expired OAuth state.');
        }

        $state = Cache::pull('provider-oauth-state:'.$request->string('state'));

        if (! $state || $state['provider'] !== $provider) {
            throw new BadRequestHttpException('Invalid or expired OAuth state.');
        }

        $adapter = $providers->get($providerName);

        if (! $adapter instanceof OAuthActivityProvider) {
            throw new BadRequestHttpException('This provider does not support OAuth.');
        }

        $token = $adapter->exchangeAuthorizationCode($request->string('code')->toString());
        $connection = ProviderConnection::query()->updateOrCreate(
            ['user_id' => $state['user_id'], 'provider' => $providerName],
            [
                'external_user_id' => $token->externalUserId,
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'token_expires_at' => $token->expiresAt,
                'scopes' => $token->scopes,
                'capabilities' => $adapter->capabilities(),
                'status' => ConnectionStatus::Active,
                'last_error' => null,
            ],
        );

        SyncProviderConnection::dispatch($connection);

        return redirect()
            ->route('settings.connections')
            ->with('status', ucfirst($providerName->value).' ist verbunden. Die erste Synchronisierung wurde gestartet.');
    }
}
