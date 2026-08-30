<?php

namespace App\Domain\Activities\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderActivity
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $type,
        public CarbonImmutable $startedAt,
        public ?string $timezone = null,
        public ?float $distanceM = null,
        public ?int $movingTimeS = null,
        public ?int $elapsedTimeS = null,
        public ?float $elevationGainM = null,
        public ?float $averageSpeedMps = null,
        public ?float $maxSpeedMps = null,
        public ?int $averageHeartRate = null,
        public ?int $maxHeartRate = null,
        public ?float $averageCadence = null,
        public ?float $maxCadence = null,
        public ?float $averagePower = null,
        public ?float $weightedAveragePower = null,
        public ?float $maxPower = null,
        public ?float $energyKj = null,
        public ?string $summaryPolyline = null,
        public ?CarbonImmutable $externalUpdatedAt = null,
        public array $raw = [],
    ) {}
}
