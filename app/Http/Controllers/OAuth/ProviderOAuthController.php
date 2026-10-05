<?php

namespace App\Http\Controllers\OAuth;

use App\Domain\Activities\Contracts\OAuthActivityProvider;
use App\Domain\Activities\Enums\ConnectionStatus;
use App\Domain\Activities\Enums\ProviderName;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderConnection;
use App\Models\ProviderConnection;
use App\Models\User;
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
            'session_hash' => $request->hasSession() ? hash('sha256', $request->session()->getId()) : null,
        ], now()->addMinutes(10));

        return redirect()->away($adapter->authorizationUrl($state));
    }

    public function callback(Request $request, string $provider, ProviderRegistry $providers): RedirectResponse
    {
        if ($request->filled('error')) {
            throw new BadRequestHttpException('Provider authorization was denied.');
        }

        $providerName = ProviderName::tryFrom($provider) ?? throw new BadRequestHttpException('Unknown provider.');

        $code = $request->input('code');
        $stateValue = $request->input('state');
        if (! is_string($code) || $code === '' || strlen($code) > 4096
            || ! is_string($stateValue) || $stateValue === '' || strlen($stateValue) > 128) {
            throw new BadRequestHttpException('Invalid or expired OAuth state.');
        }

        $stateKey = 'provider-oauth-state:'.$stateValue;
        $state = Cache::lock('provider-oauth-state-lock:'.hash('sha256', $stateValue), 10)
            ->get(function () use ($request, $stateKey, $provider): mixed {
                $pending = Cache::get($stateKey);
                if (! is_array($pending)
                    || ($pending['provider'] ?? null) !== $provider
                    || ($pending['user_id'] ?? null) !== $request->user()?->id
                    || (isset($pending['session_hash'])
                        && ! hash_equals($pending['session_hash'], hash('sha256', $request->session()->getId())))) {
                    throw new BadRequestHttpException('Invalid or expired OAuth state.');
                }

                return Cache::pull($stateKey);
            });

        if (! is_array($state)) {
            throw new BadRequestHttpException('Invalid or expired OAuth state.');
        }

        $adapter = $providers->get($providerName);

        if (! $adapter instanceof OAuthActivityProvider) {
            throw new BadRequestHttpException('This provider does not support OAuth.');
        }

        try {
            $token = $adapter->exchangeAuthorizationCode($code);
        } catch (\Throwable) {
            throw new \RuntimeException('Provider authorization could not be completed. Please try again.');
        }
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

        $locale = User::query()->whereKey($state['user_id'])->value('locale');

        if (is_string($locale)) {
            app()->setLocale($locale);
        }

        return redirect()
            ->route('settings.connections')
            ->with('status', __('ui.messages.provider_connected', ['provider' => ucfirst($providerName->value)]));
    }
}
