<?php

namespace App\Http\Controllers\Api;

use App\Domain\Activities\Services\DisconnectProviderConnection;
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

    public function destroy(Request $request, string $provider, DisconnectProviderConnection $disconnect): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($provider, ['hammerhead', 'wahoo'], true), 404);
        $connection = $request->user()->providerConnections()->where('provider', $provider)->firstOrFail();
        $revoked = $disconnect->handle($connection);
        $message = $revoked ? 'provider_disconnected' : 'provider_disconnected_locally';

        if (! $request->expectsJson()) {
            return redirect()->route('settings.connections')
                ->with('status', __('ui.messages.'.$message, ['provider' => ucfirst($provider)]));
        }

        return response()->json(['status' => 'disconnected', 'remote_revocation_failed' => ! $revoked]);
    }

    public function sync(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $connection = ProviderConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->firstOrFail();

        abort_unless($connection->isConnected(), 409, 'Reconnect the provider before synchronizing.');

        SyncProviderConnection::dispatch($connection);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('settings.connections')
                ->with('status', __('ui.messages.provider_sync_started', ['provider' => ucfirst($provider)]));
        }

        return response()->json(['status' => 'accepted', 'connection_id' => $connection->id], 202);
    }
}
