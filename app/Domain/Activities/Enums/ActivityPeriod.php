<?php

namespace App\Domain\Activities\Enums;

use Carbon\CarbonImmutable;

enum ActivityPeriod: string
{
    case ThirtyDays = '30d';
    case CurrentYear = 'year';
    case All = 'all';

    public static function resolve(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::CurrentYear : self::CurrentYear;
    }

    /** @return array{from: CarbonImmutable|null, to: CarbonImmutable|null} */
    public function range(CarbonImmutable $now): array
    {
        return match ($this) {
            self::ThirtyDays => [
                'from' => $now->subDays(29)->startOfDay(),
                'to' => $now->endOfDay(),
            ],
            self::CurrentYear => [
                'from' => $now->startOfYear(),
                'to' => $now->endOfYear(),
            ],
            self::All => ['from' => null, 'to' => null],
        };
    }

    public function shortLabel(CarbonImmutable $now): string
    {
        return match ($this) {
            self::ThirtyDays => '30 Tage',
            self::CurrentYear => (string) $now->year,
            self::All => 'Alles',
        };
    }

    public function label(CarbonImmutable $now): string
    {
        return match ($this) {
            self::ThirtyDays => 'Letzte 30 Tage',
            self::CurrentYear => 'Jahr '.$now->year,
            self::All => 'Gesamter Zeitraum',
        };
    }
}
