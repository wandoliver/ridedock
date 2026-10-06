<x-layout :title="__('ui.settings.connections_title')">
<x-page-header :title="__('ui.common.connections')" :description="__('ux.settings_description')" :back="route('dashboard')" :parent="__('ui.common.settings')"><x-slot:navigation><x-settings-nav /></x-slot:navigation></x-page-header>
<x-flash />
<div class="rd-shell" data-selection-group="provider"><main class="rd-workspace">
<div class="flex flex-wrap justify-between gap-2 pb-3 pr-4 text-sm text-ink-50"><p class="font-semibold">{{ __('ui.settings.activity_services') }} · {{ __('ui.settings.connected_count', ['count' => $connections->filter(fn ($item) => $item->isConnected())->count()]) }}</p><p>{{ __('ui.settings.credentials_safe') }}</p></div>
            @php
                $providerDefinitions = [
                    ['key' => 'hammerhead', 'name' => 'Hammerhead'],
                    ['key' => 'wahoo', 'name' => 'Wahoo'],
                ];
                $connectionsByProvider = $connections->keyBy(fn ($item) => $item->provider->value);
            @endphp
            <div class="rd-surface overflow-hidden">
                @foreach ($providerDefinitions as $provider)
                    @php
                        $connection = $connectionsByProvider->get($provider['key']);
                        $connected = $connection?->isConnected() ?? false;
                    @endphp
                    <article class="flex flex-col gap-4 border-b border-line border-l-[3px] border-l-transparent px-4 py-3.5 sm:flex-row sm:flex-wrap sm:items-center" data-provider-connection-card>
                        <span class="grid size-11 shrink-0 place-items-center rounded-lg {{ $connected ? 'bg-plan-soft text-plan-text' : 'bg-canvas text-ink-50' }}">
                            <x-icon name="bike" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <button type="button" class="rd-provider-select" data-select="{{ $provider['key'] }}" aria-controls="provider-{{ $provider['key'] }}" aria-pressed="{{ request('provider', 'hammerhead') === $provider['key'] ? 'true' : 'false' }}">{{ $provider['name'] }}</button>
                            <p class="mt-1 text-sm {{ $connection?->status->value === 'error' ? 'text-ink-60' : 'text-ink-50' }}">
                                @if (! $connected)
                                    {{ __('ui.settings.not_connected') }}
                                @elseif (in_array($connection->status->value, ['syncing', 'queued'], true))
                                    {{ __('ui.settings.sync_running') }}
                                @elseif ($connection->status->value === 'waiting')
                                    {{ __('operations.sync_retry', ['time' => $connection->sync_retry_at?->locale(app()->getLocale())->diffForHumans() ?? '–']) }}
                                @elseif ($connection->status->value === 'error')
                                    {{ __('ui.settings.sync_failed') }}
                                @elseif ($connection->last_synced_at)
                                    {{ __('ui.settings.last_synced', ['time' => $connection->last_synced_at->locale(app()->getLocale())->diffForHumans()]) }}
                                @else
                                    {{ __('ui.settings.sync_pending') }}
                                @endif
                            </p>
                        </div>

                        @if ($connection?->status->value === 'error')
                            <form method="POST" action="{{ route('provider.sync', $provider['key']) }}" data-provider-sync-form>
                                @csrf
                                <button type="submit" class="rd-button rd-button-secondary" data-provider-sync-button data-pending-label="{{ __('ui.settings.sync_running') }}">{{ __('ui.settings.try_again') }}</button>
                            </form>
                        @elseif (in_array($connection?->status->value, ['syncing', 'queued'], true))
                            <span class="w-fit rounded-full bg-canvas px-3 py-1.5 text-sm font-medium text-ink-60">{{ __('ui.settings.running') }}</span>
                        @elseif ($connected)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="w-fit rounded-full bg-plan-soft px-3 py-1.5 text-sm font-medium text-plan-text">{{ __('ui.settings.connected') }}</span>
                                <form method="POST" action="{{ route('provider.sync', $provider['key']) }}" data-provider-sync-form>
                                    @csrf
                                    <button type="submit" class="rd-button rd-button-secondary" data-provider-sync-button data-pending-label="{{ __('ui.settings.sync_running') }}">{{ __('ui.settings.sync_now') }}</button>
                                </form>
                            </div>
                        @elseif (config('activity-providers.'.$provider['key'].'.enabled'))
                            <a href="{{ route('provider.connect', $provider['key']) }}" class="rd-button rd-button-secondary">{{ __('ui.settings.connect') }}</a>
                        @else
                            <span class="w-fit rounded-full bg-canvas px-3 py-1.5 text-sm text-ink-50">{{ __('ui.common.not_configured') }}</span>
                        @endif

                        @if ($connected)
                            <form method="POST" action="{{ route('provider.disconnect', $provider['key']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rd-button rd-button-secondary" aria-label="{{ __('ui.settings.disconnect_provider', ['provider' => $provider['name']]) }}">{{ __('ui.settings.disconnect') }}</button>
                            </form>
                        @endif

                        @if ($connected)
                            <div class="w-full basis-full rounded-lg bg-plan-soft px-4 py-3" data-provider-sync-progress role="status" aria-live="polite" aria-busy="true" hidden>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-plan-text">{{ __('ui.settings.syncing_rides') }}</span>
                                    <span class="text-sm text-plan-text">{{ __('ui.common.please_wait') }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-plan-soft" role="progressbar" aria-label="{{ __('ui.settings.sync_progress', ['provider' => $provider['name']]) }}" aria-valuetext="{{ __('ui.settings.sync_running') }}">
                                    <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-plan"></span>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
</main>
<aside class="rd-panel">
@foreach($providerDefinitions as $provider)
@php
    $connection = $connectionsByProvider->get($provider['key']);
    $connected = $connection?->isConnected() ?? false;
@endphp
<div id="provider-{{ $provider['key'] }}" data-detail="{{ $provider['key'] }}" @if(request('provider', 'hammerhead') !== $provider['key']) hidden @endif>
<p class="text-sm font-semibold text-plan-text">{{ $provider['name'] }}</p>
<h2 class="mt-2">{{ $connected ? __('design.connected_since', ['date' => $connection->created_at?->locale(app()->getLocale())->isoFormat('D. MMMM YYYY')]) : __('ui.settings.not_connected') }}</h2>
<div class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><x-panel-label>{{ __('ui.dashboard.activities') }}</x-panel-label>{{ $connection?->sources_count ?? 0 }}</div><div><x-panel-label>{{ __('design.last_sync') }}</x-panel-label>{{ $connection?->last_synced_at?->locale(app()->getLocale())->diffForHumans() ?? '–' }}</div><div><x-panel-label>Privacy-Trim</x-panel-label>{{ config('heatmap.privacy_trim_m') }} m</div></div>
<x-info-box class="mt-5">{{ __('ui.settings.credentials_safe') }}</x-info-box>
</div>
@endforeach
</aside></div></x-layout>
