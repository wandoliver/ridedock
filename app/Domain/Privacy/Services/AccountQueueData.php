<?php

namespace App\Domain\Privacy\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AccountQueueData
{
    /** @return array<string, int|bool> */
    public function payloadFor(mixed $job): array
    {
        $owner = match (true) {
            $job instanceof \App\Jobs\ImportProviderActivity,
            $job instanceof \App\Jobs\SyncProviderConnection => $job->providerConnection->user_id,
            $job instanceof \App\Jobs\EnrichActivitySource => DB::table('activities')->where('id', $job->activitySource->activity_id)->value('user_id'),
            $job instanceof \App\Jobs\GenerateActivityAnalysis,
            $job instanceof \App\Jobs\GenerateWeeklyTrainingPlan,
            $job instanceof \App\Jobs\GenerateGoalTrainingPlan,
            $job instanceof \App\Jobs\GenerateTrainingBlock => DB::table('ai_analyses')->where('id', $job->analysisId)->value('user_id'),
            default => null,
        };
        if ($owner !== null) {
            return ['lifecycle_user_id' => (int) $owner];
        }
        if ($job instanceof \App\Jobs\EnrichActivitySource
            || $job instanceof \App\Jobs\GenerateActivityAnalysis
            || $job instanceof \App\Jobs\GenerateWeeklyTrainingPlan
            || $job instanceof \App\Jobs\GenerateGoalTrainingPlan
            || $job instanceof \App\Jobs\GenerateTrainingBlock) {
            return ['lifecycle_discard' => true];
        }
        return [];
    }

    public function cleanLateJob(\Illuminate\Queue\Events\JobQueued $event): void
    {
        $payload = $event->payload();
        $owner = $payload['lifecycle_user_id'] ?? null;
        $config = config("queue.connections.{$event->connectionName}");
        if (($config['driver'] ?? null) !== 'database' || ($owner === null && ! ($payload['lifecycle_discard'] ?? false))) {
            return;
        }
        DB::transaction(function () use ($event, $payload, $owner, $config): void {
            // Close the race where a request enqueues work just after account deletion.
            if (($payload['lifecycle_discard'] ?? false) || ! User::query()->whereKey($owner)->lockForUpdate()->first()) {
                DB::connection($config['connection'] ?? null)->table($config['table'])->where('id', $event->id)->delete();
            }
        });
    }

    /** @return list<Builder> */
    public function tables(): array
    {
        $tables = [];
        foreach (array_unique([config('queue.default'), config('ai.queue_connection')]) as $name) {
            $config = config("queue.connections.$name");
            if (($config['driver'] ?? null) === 'sync') {
                continue;
            }
            if (($config['driver'] ?? null) !== 'database') {
                throw new RuntimeException('Account lifecycle requires sync or database queues.');
            }
            $key = ($config['connection'] ?? '').':'.$config['table'];
            $tables[$key] = DB::connection($config['connection'] ?? null)->table($config['table']);
        }
        if (config('queue.failed.driver') !== 'null') {
            if (config('queue.failed.driver') !== 'database-uuids') {
                throw new RuntimeException('Account lifecycle requires database-uuids or null failed-job storage.');
            }
            $tables['failed'] = DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'));
        }
        return array_values($tables);
    }

    /** @param array<string, mixed> $payload */
    public function owner(array $payload): ?int
    {
        if (isset($payload['lifecycle_user_id'])) {
            return (int) $payload['lifecycle_user_id'];
        }
        // Legacy payloads are inspected as strings, never unserialized.
        $command = $payload['data']['command'] ?? '';
        if (! is_string($command)) {
            return null;
        }
        foreach (['providerConnection' => 'provider_connections', 'activitySource' => 'activity_sources'] as $property => $table) {
            if (preg_match('/"'. $property .'";O:\d+:"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier":\d+:\{s:5:"class";s:\d+:"[^"]+";s:2:"id";i:(\d+);/', $command, $match)) {
                $record = DB::table($table)->where('id', $match[1])->first();
                if ($record === null) {
                    return null;
                }
                return $table === 'provider_connections' ? (int) $record->user_id
                    : (int) DB::table('activities')->where('id', $record->activity_id)->value('user_id');
            }
        }
        if (preg_match('/"analysisId";s:\d+:"([^"]+)";/', $command, $match)) {
            $owner = DB::table('ai_analyses')->where('id', $match[1])->value('user_id');
            return $owner === null ? null : (int) $owner;
        }
        return null;
    }

    public function remove(User $user): void
    {
        foreach ($this->tables() as $table) {
            foreach ((clone $table)->orderBy('id')->cursor() as $job) {
                $payload = json_decode($job->payload, true);
                if (is_array($payload) && $this->owner($payload) === $user->id) {
                    (clone $table)->where('id', $job->id)->delete();
                }
            }
        }
    }
}
