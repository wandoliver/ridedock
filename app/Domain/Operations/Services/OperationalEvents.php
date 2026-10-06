<?php

namespace App\Domain\Operations\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class OperationalEvents
{
    public function record(string $scope, string $code, ?int $userId = null, ?string $provider = null, ?int $httpStatus = null): void
    {
        // Call sites supply fixed codes only, never exceptions, URLs, contexts or response bodies.
        DB::transaction(function () use ($scope, $code, $userId, $provider, $httpStatus): void {
            if ($userId !== null && ! DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id'])) {
                return;
            }
            DB::table('operation_events')->insert([
                'user_id' => $userId, 'scope' => $scope, 'code' => $code,
                'provider' => $provider, 'http_status' => $httpStatus, 'created_at' => now(),
            ]);
        });
        Log::warning('RideDock operational event', ['scope' => $scope, 'code' => $code, 'provider' => $provider, 'http_status' => $httpStatus]);
    }
}
