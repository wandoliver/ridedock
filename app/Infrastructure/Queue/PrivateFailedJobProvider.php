<?php

namespace App\Infrastructure\Queue;

use App\Models\User;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\DB;

final class PrivateFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /** @param string $connection
     * @param string $queue
     * @param string $payload
     * @param \Throwable $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $data = json_decode($payload, true);
        if ($data['lifecycle_discard'] ?? false) {
            return null;
        }
        $owner = $data['lifecycle_user_id'] ?? null;
        if ($owner === null) {
            return parent::log($connection, $queue, $payload, $exception);
        }
        return DB::transaction(function () use ($connection, $queue, $payload, $exception, $owner): ?string {
            // An in-flight failure cannot retain personal payloads after account deletion.
            if (! User::query()->whereKey($owner)->lockForUpdate()->first()) {
                return null;
            }
            return parent::log($connection, $queue, $payload, $exception);
        });
    }
}
