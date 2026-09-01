<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('ui.settings.connections_meta') }}">
    <title>{{ __('ui.settings.connections_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="{{ __('ui.common.back_to_overview') }}">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 4-6 6 6 6"/></svg>
                {{ __('ui.common.overview') }}
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <div class="mb-8 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <section>
            <p class="text-sm font-medium text-emerald-700">{{ __('ui.common.settings') }}</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">{{ __('ui.common.connections') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">{{ __('ui.settings.connections_intro') }}</p>
        </section>

        <nav class="mt-7 flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="{{ __('ui.settings.areas') }}">
            <a href="{{ route('settings.training') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">{{ __('ui.common.training_ai') }}</a>
            <a href="{{ route('settings.connections') }}" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white" aria-current="page">{{ __('ui.common.connections') }}</a>
        </nav>

        <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8" aria-label="{{ __('ui.settings.data_sources') }}">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">{{ __('ui.settings.data_sources') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.settings.activity_services') }}</h2>
                </div>
                <span class="text-sm text-slate-400">{{ $connections->isNotEmpty() ? __('ui.settings.connected_count', ['count' => $connections->count()]) : __('ui.settings.not_connected') }}</span>
            </div>

            @php
                $providerDefinitions = [
                    ['key' => 'hammerhead', 'name' => 'Hammerhead'],
                    ['key' => 'wahoo', 'name' => 'Wahoo'],
                ];
                $connectionsByProvider = $connections->keyBy(fn ($item) => $item->provider->value);
            @endphp
            <div class="mt-6 space-y-3">
                @foreach ($providerDefinitions as $provider)
                    @php($connection = $connectionsByProvider->get($provider['key']))
                    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:flex-wrap sm:items-center" data-provider-connection-card>
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $connection ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="m6 17 4-8h4l4 8M9 12h7M10 9 8 6"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-medium">{{ $provider['name'] }}</h3>
                            <p class="mt-1 text-sm {{ $connection?->status->value === 'error' ? 'text-rose-600' : 'text-slate-500' }}">
                                @if (! $connection)
                                    {{ __('ui.settings.not_connected_yet') }}
                                @elseif ($connection->status->value === 'syncing')
                                    {{ __('ui.settings.sync_running') }}
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
                                <button type="submit" class="rounded-xl bg-rose-50 px-3.5 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100" data-provider-sync-button data-pending-label="{{ __('ui.settings.sync_running') }}">{{ __('ui.settings.try_again') }}</button>
                            </form>
                        @elseif ($connection?->status->value === 'syncing')
                            <span class="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700">{{ __('ui.settings.running') }}</span>
                        @elseif ($connection)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700">{{ __('ui.settings.connected') }}</span>
                                <form method="POST" action="{{ route('provider.sync', $provider['key']) }}" data-provider-sync-form>
                                    @csrf
                                    <button type="submit" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700" data-provider-sync-button data-pending-label="{{ __('ui.settings.sync_running') }}">{{ __('ui.settings.sync_now') }}</button>
                                </form>
                            </div>
                        @elseif (config('activity-providers.'.$provider['key'].'.enabled'))
                            <a href="{{ route('provider.connect', $provider['key']) }}" class="w-fit rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">{{ __('ui.settings.connect') }}</a>
                        @else
                            <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-500">{{ __('ui.common.not_configured') }}</span>
                        @endif

                        @if ($connection)
                            <div class="w-full basis-full rounded-xl bg-emerald-50 px-4 py-3" data-provider-sync-progress role="status" aria-live="polite" aria-busy="true" hidden>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-emerald-900">{{ __('ui.settings.syncing_rides') }}</span>
                                    <span class="text-xs text-emerald-700">{{ __('ui.common.please_wait') }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-emerald-200" role="progressbar" aria-label="{{ __('ui.settings.sync_progress', ['provider' => $provider['name']]) }}" aria-valuetext="{{ __('ui.settings.sync_running') }}">
                                    <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-emerald-600"></span>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · {{ __('ui.common.settings') }}</p>
            <p>{{ __('ui.settings.credentials_safe') }}</p>
        </footer>
    </main>
</body>
</html>
