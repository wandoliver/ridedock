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

        if (in_array($connection->status, [\App\Domain\Activities\Enums\ConnectionStatus::Active, \App\Domain\Activities\Enums\ConnectionStatus::Error], true)) {
            $connection->updateWhileConnected(['status' => \App\Domain\Activities\Enums\ConnectionStatus::Queued, 'sync_started_at' => now(), 'sync_retry_at' => null]);
        }
        try {
            SyncProviderConnection::dispatch($connection);
        } catch (\App\Domain\Operations\Exceptions\WorkDeferred $deferred) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'waiting', 'retry_after' => $deferred->retryAfter], 429)->header('Retry-After', (string) $deferred->retryAfter);
            }
            return redirect()->route('settings.connections')->with('status', __('operations.sync_waiting', ['seconds' => $deferred->retryAfter]));
        } catch (\Throwable) {
            $connection->updateWhileConnected(['status' => \App\Domain\Activities\Enums\ConnectionStatus::Error, 'last_error' => 'Provider synchronization failed. Retry the synchronization to resume.']);
            app(\App\Domain\Operations\Services\OperationalEvents::class)->record('provider', 'sync_dispatch_failed', $connection->user_id, $connection->provider->value);
            if ($request->expectsJson()) {
                return response()->json(['status' => 'failed'], 503);
            }
            return redirect()->route('settings.connections')->with('analysis_error', __('ui.settings.sync_failed'));
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('settings.connections')
                ->with('status', __('ui.messages.provider_sync_started', ['provider' => ucfirst($provider)]));
        }

        return response()->json(['status' => 'accepted', 'connection_id' => $connection->id], 202);
    }
}
