<?php

namespace App\Infrastructure\Providers\Wahoo;

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

final class WahooProvider implements ActivityFileProvider, OAuthActivityProvider
{
    /** @var list<int> */
    private const CYCLING_WORKOUT_TYPES = [0, 11, 12, 13, 14, 15, 16, 49, 61, 64, 68, 70];

    public function key(): ProviderName
    {
        return ProviderName::Wahoo;
    }

    public function capabilities(): array
    {
        return ['oauth', 'activities', 'activity_fit'];
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return rtrim(config('activity-providers.wahoo.auth_url'), '/').'/oauth/authorize?'.http_build_query([
            'client_id' => config('activity-providers.wahoo.client_id'),
            'redirect_uri' => config('activity-providers.wahoo.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('activity-providers.wahoo.scopes')),
            'state' => $state,
        ]);
    }

    public function exchangeAuthorizationCode(string $code): ProviderToken
    {
        $this->assertConfigured();

        $payload = Http::asForm()->post($this->tokenUrl(), [
            'client_id' => config('activity-providers.wahoo.client_id'),
            'client_secret' => config('activity-providers.wahoo.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('activity-providers.wahoo.redirect_uri'),
        ])->throw()->json();
        $user = $this->clientWithToken((string) $payload['access_token'])->get('/user')->throw()->json();

        return $this->tokenFromResponse($payload, isset($user['id']) ? (string) $user['id'] : null);
    }

    public function refreshAccessToken(ProviderConnection $connection): ProviderToken
    {
        $this->assertConfigured();

        return $this->tokenFromResponse(Http::asForm()->post($this->tokenUrl(), [
            'client_id' => config('activity-providers.wahoo.client_id'),
            'client_secret' => config('activity-providers.wahoo.client_secret'),
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
        $pageSize = (int) config('activity-providers.sync.page_size');
        $payload = $this->client($connection)->get('/workouts', [
            'page' => $page,
            'per_page' => $pageSize,
        ])->throw()->json();
        $workouts = is_array($payload['workouts'] ?? null) ? $payload['workouts'] : [];
        $activities = [];
        $reachedSince = false;

        foreach ($workouts as $workout) {
            if (! is_array($workout) || ! isset($workout['starts'])) {
                continue;
            }

            $startedAt = CarbonImmutable::parse($workout['starts'])->utc();

            if ($since !== null && $startedAt->isBefore($since)) {
                $reachedSince = true;

                continue;
            }

            if (! $this->isCompletedCyclingWorkout($workout)) {
                continue;
            }

            $activities[] = $this->mapActivity($workout);
        }

        $perPage = max(1, (int) ($payload['per_page'] ?? $pageSize));
        $total = max(0, (int) ($payload['total'] ?? count($workouts)));
        $hasNextPage = $page * $perPage < $total;

        return new ProviderPage(
            $activities,
            ! $reachedSince && $hasNextPage ? (string) ($page + 1) : null,
        );
    }

    public function activity(ProviderConnection $connection, string $externalId): ProviderActivity
    {
        $payload = $this->client($connection)
            ->get('/workouts/'.rawurlencode($externalId))
            ->throw()
            ->json();

        if (! $this->isCompletedCyclingWorkout($payload)) {
            throw new RuntimeException('The Wahoo workout is not a completed cycling activity.');
        }

        return $this->mapActivity($payload);
    }

    public function downloadActivityFile(ProviderConnection $connection, string $externalId): string
    {
        $summary = $this->client($connection)
            ->get('/workouts/'.rawurlencode($externalId).'/workout_summary')
            ->throw()
            ->json();
        $url = $summary['file']['url'] ?? null;

        if (! is_string($url) || $url === '' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('The Wahoo workout does not provide a valid FIT download URL.');
        }

        return Http::connectTimeout(5)
            ->timeout(60)
            ->accept('application/vnd.ant.fit')
            ->get($url)
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
        $summary = $payload['workout_summary'] ?? null;

        if (! is_array($summary) || ! isset($payload['id'], $payload['starts'])) {
            throw new RuntimeException('The Wahoo workout payload is incomplete.');
        }

        $distanceM = $this->floatOrNull($summary['distance_accum'] ?? null);
        $movingTimeS = $this->intOrNull($summary['duration_active_accum'] ?? null);
        $elapsedTimeS = $this->intOrNull($summary['duration_total_accum'] ?? null);
        $workJoules = $this->floatOrNull($summary['work_accum'] ?? null);

        return new ProviderActivity(
            externalId: (string) $payload['id'],
            name: (string) ($summary['name'] ?? $payload['name'] ?? 'Untitled ride'),
            type: $this->mapType((int) ($payload['workout_type_id'] ?? 0)),
            startedAt: CarbonImmutable::parse($payload['starts'])->utc(),
            timezone: isset($summary['time_zone']) ? (string) $summary['time_zone'] : null,
            distanceM: $distanceM,
            movingTimeS: $movingTimeS,
            elapsedTimeS: $elapsedTimeS,
            elevationGainM: $this->floatOrNull($summary['ascent_accum'] ?? null),
            averageSpeedMps: $this->floatOrNull($summary['speed_avg'] ?? null),
            averageHeartRate: $this->intOrNull($summary['heart_rate_avg'] ?? null),
            averageCadence: $this->floatOrNull($summary['cadence_avg'] ?? null),
            averagePower: $this->floatOrNull($summary['power_avg'] ?? null),
            weightedAveragePower: $this->floatOrNull($summary['power_bike_np_last'] ?? null),
            energyKj: $workJoules === null ? null : $workJoules / 1000,
            externalUpdatedAt: isset($summary['updated_at'])
                ? CarbonImmutable::parse($summary['updated_at'])->utc()
                : (isset($payload['updated_at']) ? CarbonImmutable::parse($payload['updated_at'])->utc() : null),
            raw: $payload,
        );
    }

    private function client(ProviderConnection $connection): PendingRequest
    {
        return $this->clientWithToken($connection->access_token);
    }

    private function clientWithToken(string $accessToken): PendingRequest
    {
        return Http::baseUrl(config('activity-providers.wahoo.api_url'))
            ->connectTimeout(5)
            ->timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->retry(2, 250, throw: false);
    }

    /** @param array<string, mixed> $payload */
    private function tokenFromResponse(array $payload, ?string $externalUserId): ProviderToken
    {
        return new ProviderToken(
            accessToken: (string) $payload['access_token'],
            refreshToken: isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            expiresAt: isset($payload['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $payload['expires_in'])
                : null,
            externalUserId: $externalUserId,
            scopes: config('activity-providers.wahoo.scopes'),
        );
    }

    private function tokenUrl(): string
    {
        return rtrim(config('activity-providers.wahoo.auth_url'), '/').'/oauth/token';
    }

    private function assertConfigured(): void
    {
        if (! config('activity-providers.wahoo.enabled')
            || ! config('activity-providers.wahoo.client_id')
            || ! config('activity-providers.wahoo.client_secret')
            || ! config('activity-providers.wahoo.redirect_uri')) {
            throw new RuntimeException('The Wahoo provider is disabled or incomplete.');
        }
    }

    /** @param array<string, mixed> $workout */
    private function isCompletedCyclingWorkout(array $workout): bool
    {
        return is_array($workout['workout_summary'] ?? null)
            && in_array((int) ($workout['workout_type_id'] ?? -1), self::CYCLING_WORKOUT_TYPES, true);
    }

    private function mapType(int $type): string
    {
        return match ($type) {
            11 => 'gravel',
            13 => 'mountain_bike',
            64 => 'e_bike',
            12, 49, 61, 68 => 'indoor_ride',
            default => 'ride',
        };
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
