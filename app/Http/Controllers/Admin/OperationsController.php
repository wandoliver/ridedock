<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Services\OperationsOverview;
use App\Domain\Operations\Services\ProviderOperations;
use App\Http\Controllers\Controller;
use App\Models\ProviderConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class OperationsController extends Controller
{
    public function __invoke(OperationsOverview $overview, ProviderOperations $providers): View
    {
        return view('admin.operations', [
            'snapshot' => $overview->snapshot(),
            'groups' => $overview->attempts()->select(['user_id', 'feature', 'model'])
                ->selectRaw('COUNT(*) AS attempts, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, SUM(cost_microusd) AS cost_microusd, SUM(CASE WHEN cost_microusd IS NULL THEN 1 ELSE 0 END) AS unknown_costs')
                ->groupBy(['user_id', 'feature', 'model'])->orderBy('user_id')->paginate(50),
            'connections' => ProviderConnection::query()->select(['id', 'user_id', 'provider', 'status', 'sync_started_at', 'sync_retry_at', 'last_synced_at'])
                ->whereIn('status', ['error', 'waiting', 'syncing', 'queued'])->orderBy('id')->limit(50)->get(),
            'events' => DB::table('operation_events')->orderByDesc('id')->limit(50)->get(),
            'cooldowns' => ['hammerhead' => $providers->cooldown('hammerhead'), 'wahoo' => $providers->cooldown('wahoo')],
        ]);
    }
}
