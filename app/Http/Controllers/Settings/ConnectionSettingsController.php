<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Activities\Enums\ProviderName;
use App\Http\Controllers\Controller;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ConnectionSettingsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        /** @var Collection<int, ProviderConnection> $connections */
        $connections = $user->providerConnections()
            ->whereIn('provider', [ProviderName::Hammerhead, ProviderName::Wahoo])
            ->orderByDesc('last_synced_at')
            ->get(['id', 'user_id', 'provider', 'status', 'last_synced_at']);

        return view('settings.connections', ['connections' => $connections]);
    }
}
