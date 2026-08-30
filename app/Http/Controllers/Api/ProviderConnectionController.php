<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderConnectionResource;
use App\Jobs\SyncProviderConnection;
use App\Models\ProviderConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderConnectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProviderConnectionResource::collection(
            $request->user()->providerConnections()->orderBy('provider')->get(),
        );
    }

    public function sync(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $connection = ProviderConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->firstOrFail();

        SyncProviderConnection::dispatch($connection);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('settings.connections')
                ->with('status', ucfirst($provider).' Synchronisierung wurde eingereiht.');
        }

        return response()->json(['status' => 'queued', 'connection_id' => $connection->id], 202);
    }
}
