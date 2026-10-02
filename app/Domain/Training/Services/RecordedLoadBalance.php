<?php

namespace App\Domain\Training\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

final class RecordedLoadBalance
{
    /** @return array<string, mixed> */
    public function report(User $user, CarbonImmutable $asOf, ?string $beforeDate = null): array
    {
        $asOf = $asOf->min(CarbonImmutable::now())->setTimezone(config('app.timezone'));
        $cutoff = $beforeDate === null ? $asOf->startOfDay()
            : CarbonImmutable::parse($beforeDate, config('app.timezone'))->min(CarbonImmutable::today(config('app.timezone')));
        $end = $cutoff->subDay();
        $start = $end->subDays(41);
        $first = $user->activities()->where('started_at', '<=', $asOf)->oldest('started_at')->first(['started_at', 'timezone']);
        $firstDate = $first?->localStartedAt()->toDateString();
        $days = [];
        for ($offset = 0; $offset < 42; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            $days[$date] = $this->day($date, $firstDate);
        }
        $user->activities()->whereBetween('started_at', [$start->subDay(), $asOf])
            ->select(['id', 'started_at', 'timezone', 'moving_time_s', 'rpe'])->chunkById(100, function ($rides) use (&$days): void {
                foreach ($rides as $ride) {
                    $date = $ride->localStartedAt()->toDateString();
                    if (! isset($days[$date])) {
                        continue;
                    }
                    $day = &$days[$date];
                    $day['ride_count']++;
                    if ($ride->moving_time_s !== null && $ride->moving_time_s >= 0 && $ride->rpe !== null && $ride->rpe >= 1 && $ride->rpe <= 10) {
                        $day['rated_ride_count']++;
                        if ($day['load'] !== null) {
                            $day['load'] += $ride->moving_time_s / 60 * $ride->rpe;
                        }
                    } else {
                        $day['load'] = null;
                        $day['missing_load_ride_count']++;
                    }
                }
            });
        $shortDays = array_slice(array_values($days), -7);
        $short = $this->period($shortDays, $firstDate);
        $long = $this->period(array_values($days), $firstDate);
        $monotony = $strain = null;
        $monotonyReason = $short['reason'];
        if ($monotonyReason === null) {
            $loads = array_column($shortDays, 'load');
            $mean = array_sum($loads) / 7;
            $deviation = sqrt(array_sum(array_map(fn ($load): float => ($load - $mean) ** 2, $loads)) / 7);
            if ($mean <= 0) {
                $monotonyReason = 'no_load';
            } elseif ($deviation < .000001) {
                $monotonyReason = 'no_variation';
            } else {
                $monotony = round($mean / $deviation, 2);
                $strain = round(array_sum($loads) * $mean / $deviation, 1);
            }
        }
        $balance = null;
        $balanceReason = $long['reason'] ?? $short['reason'];
        if ($balanceReason === null) {
            if ($long['total_load'] <= 0) {
                $balanceReason = 'no_load';
            } else {
                $balance = round($long['total_load'] / 42 - $short['total_load'] / 7, 1);
            }
        }
        return [
            'as_of' => $asOf->toIso8601String(), 'window_end' => $end->toDateString(),
            'seven_days' => $short, 'forty_two_days' => $long, 'daily_loads' => $shortDays,
            'monotony' => ['value' => $monotony, 'strain' => $strain, 'reason' => $monotonyReason,
                'method' => 'mean of 7 complete recorded daily session-RPE loads / population standard deviation; strain = total load × monotony'],
            'load_balance' => ['value' => $balance, 'reason' => $balanceReason, 'unit' => 'RPE-minutes/day',
                'method' => '42-day mean minus 7-day mean of recorded session-RPE load; descriptive arithmetic, not a readiness model'],
            'freshness' => ['available' => false, 'reason' => 'recovery_not_measured'],
        ];
    }

    /** @param list<array<string, mixed>> $days
     * @return array<string, mixed>
     */
    private function period(array $days, ?string $firstDate): array
    {
        if ($days === []) {
            throw new \InvalidArgumentException('A recorded-load period must contain calendar days.');
        }
        $start = $days[0]['date'];
        $reason = $firstDate === null || $firstDate > $start ? 'insufficient_history'
            : (array_sum(array_column($days, 'missing_load_ride_count')) > 0 ? 'incomplete_load' : null);
        return [
            'start' => $start, 'end' => $days[array_key_last($days)]['date'], 'reason' => $reason,
            'ride_count' => array_sum(array_column($days, 'ride_count')),
            'rated_ride_count' => array_sum(array_column($days, 'rated_ride_count')),
            'missing_load_ride_count' => array_sum(array_column($days, 'missing_load_ride_count')),
            'total_load' => $reason === null ? array_sum(array_column($days, 'load')) : null,
        ];
    }

    /** @return array{date: string, load: float|null, ride_count: int, rated_ride_count: int, missing_load_ride_count: int} */
    private function day(string $date, ?string $firstDate): array
    {
        return ['date' => $date, 'load' => $firstDate !== null && $date >= $firstDate ? 0.0 : null,
            'ride_count' => 0, 'rated_ride_count' => 0, 'missing_load_ride_count' => 0];
    }
}
