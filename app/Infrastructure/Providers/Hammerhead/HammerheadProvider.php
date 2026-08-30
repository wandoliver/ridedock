<?php

namespace App\Infrastructure\Providers\Hammerhead;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Domain\Activities\Contracts\OAuthActivityProvider;
use App\Domain\Activities\Data\ProviderActivity;
use App\Domain\Activities\Data\ProviderPage;
use App\Domain\Activities\Data\ProviderToken;
use App\Domain\Activities\Enums\ProviderName;
use App\Models\ProviderConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HammerheadProvider implements ActivityFileProvider, OAuthActivityProvider
{
    public function key(): ProviderName
    {
        return ProviderName::Hammerhead;
    }

    public function capabilities(): array
    {
        return ['oauth', 'activities', 'activity_fit', 'webhooks', 'route_polyline'];
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return rtrim(config('activity-providers.hammerhead.auth_url'), '/').'/oauth/authorize?'.http_build_query([
            'client_id' => config('activity-providers.hammerhead.client_id'),
            'redirect_uri' => config('activity-providers.hammerhead.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('activity-providers.hammerhead.scopes')),
            'state' => $state,
        ]);
    }

    public function exchangeAuthorizationCode(string $code): ProviderToken
    {
        $this->assertConfigured();

        return $this->tokenFromResponse(Http::asForm()->post($this->tokenUrl(), [
            'client_id' => config('activity-providers.hammerhead.client_id'),
            'client_secret' => config('activity-providers.hammerhead.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('activity-providers.hammerhead.redirect_uri'),
        ])->throw()->json());
    }

    public function refreshAccessToken(ProviderConnection $connection): ProviderToken
    {
        $this->assertConfigured();

        return $this->tokenFromResponse(Http::asForm()->post($this->tokenUrl(), [
            'client_id' => config('activity-providers.hammerhead.client_id'),
            'client_secret' => config('activity-providers.hammerhead.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ])->throw()->json(), $connection->external_user_id);
    }

    public function activities(
        ProviderConnection $connection,
        ?CarbonImmutable $since = null,
        ?string $cursor = null,
    ): ProviderPage {
        $page = max(1, (int) ($cursor ?: 1));
        $pageSize = config('activity-providers.sync.page_size');
        $payload = $this->client($connection)->get('/activities', array_filter([
            'page' => $page,
            'perPage' => $pageSize,
            'startDate' => $since?->toDateString(),
        ], fn ($value) => $value !== null))->throw()->json();

        $activities = array_values(array_map(function (array $summary) use ($connection): ProviderActivity {
            return $this->activity($connection, (string) $summary['id']);
        }, $payload['data'] ?? []));
        $totalPages = (int) ($payload['totalPages'] ?? $page);

        return new ProviderPage($activities, $page < $totalPages ? (string) ($page + 1) : null);
    }

    public function activity(ProviderConnection $connection, string $externalId): ProviderActivity
    {
        $payload = $this->client($connection)->get('/activities/'.rawurlencode($externalId))->throw()->json();

        return $this->mapActivity($payload);
    }

    public function downloadActivityFile(ProviderConnection $connection, string $externalId): string
    {
        return $this->client($connection)
            ->accept('application/vnd.ant.fit')
            ->get('/activities/'.rawurlencode($externalId).'/file')
            ->throw()
            ->body();
    }

    public function activityFileExtension(): string
    {
        return 'fit';
    }

    /** @param array<string, mixed> $payload */
    public function mapActivity(array $payload): ProviderActivity
    {
        $distanceM = isset($payload['distance'])
            ? (float) $payload['distance'] * config('activity-providers.hammerhead.distance_multiplier_to_meters')
            : null;
        $durationS = isset($payload['duration'])
            ? (int) round((float) $payload['duration'] * config('activity-providers.hammerhead.duration_multiplier_to_seconds'))
            : null;

        return new ProviderActivity(
            externalId: (string) $payload['id'],
            name: (string) ($payload['name'] ?? 'Untitled ride'),
            type: $this->mapType((string) ($payload['activityType'] ?? 'RIDE')),
            startedAt: CarbonImmutable::parse($payload['startedAt'] ?? $payload['createdAt'])->utc(),
            distanceM: $distanceM,
            movingTimeS: $durationS,
            elapsedTimeS: $durationS,
            averageSpeedMps: $distanceM && $durationS ? $distanceM / $durationS : null,
            summaryPolyline: $payload['polyline'] ?? null,
            externalUpdatedAt: isset($payload['updatedAt'])
                ? CarbonImmutable::parse($payload['updatedAt'])->utc()
                : null,
            raw: $payload,
        );
    }

    private function client(ProviderConnection $connection): PendingRequest
    {
        return Http::baseUrl(config('activity-providers.hammerhead.api_url'))
            ->connectTimeout(5)
            ->timeout(30)
            ->acceptJson()
            ->withToken($connection->access_token)
            ->retry(2, 250, throw: false);
    }

    /** @param array<string, mixed> $payload */
    private function tokenFromResponse(array $payload, ?string $fallbackUserId = null): ProviderToken
    {
        return new ProviderToken(
            accessToken: (string) $payload['access_token'],
            refreshToken: $payload['refresh_token'] ?? null,
            expiresAt: isset($payload['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $payload['expires_in'])
                : null,
            externalUserId: isset($payload['user_id']) ? (string) $payload['user_id'] : $fallbackUserId,
            scopes: config('activity-providers.hammerhead.scopes'),
        );
    }

    private function tokenUrl(): string
    {
        return rtrim(config('activity-providers.hammerhead.auth_url'), '/').'/oauth/token';
    }

    private function assertConfigured(): void
    {
        if (! config('activity-providers.hammerhead.enabled')
            || ! config('activity-providers.hammerhead.client_id')
            || ! config('activity-providers.hammerhead.client_secret')
            || ! config('activity-providers.hammerhead.redirect_uri')) {
            throw new RuntimeException('The Hammerhead provider is disabled or incomplete.');
        }
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            'GRAVEL' => 'gravel',
            'MOUNTAIN_BIKE' => 'mountain_bike',
            'EBIKE', 'EMOUNTAIN_BIKE' => 'e_bike',
            default => 'ride',
        };
    }
}
