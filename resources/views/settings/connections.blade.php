<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Datenquellen und Synchronisierung für RideDock verwalten.">
    <title>Verbindungen · Einstellungen · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Zur RideDock Übersicht">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 4-6 6 6 6"/></svg>
                Übersicht
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
            <p class="text-sm font-medium text-emerald-700">Einstellungen</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Verbindungen</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">Verwalte die Dienste, aus denen RideDock deine Aktivitäten und FIT-Dateien synchronisiert.</p>
        </section>

        <nav class="mt-7 flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Einstellungsbereiche">
            <a href="{{ route('settings.training') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Training & KI</a>
            <a href="{{ route('settings.connections') }}" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white" aria-current="page">Verbindungen</a>
        </nav>

        <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8" aria-label="Datenquellen">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Datenquellen</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Aktivitätsdienste</h2>
                </div>
                <span class="text-sm text-slate-400">{{ $connections->isNotEmpty() ? '1 verbunden' : 'Nicht verbunden' }}</span>
            </div>

            <div class="mt-6 space-y-3">
                @php($connection = $connections->first())
                <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-center">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $connection ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="m6 17 4-8h4l4 8M9 12h7M10 9 8 6"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-medium">Hammerhead</h3>
                        <p class="mt-1 text-sm {{ $connection?->status->value === 'error' ? 'text-rose-600' : 'text-slate-500' }}">
                            @if (! $connection)
                                Noch nicht verbunden
                            @elseif ($connection->status->value === 'syncing')
                                Synchronisierung läuft
                            @elseif ($connection->status->value === 'error')
                                Synchronisierung fehlgeschlagen
                            @elseif ($connection->last_synced_at)
                                Zuletzt synchronisiert {{ $connection->last_synced_at->locale('de')->diffForHumans() }}
                            @else
                                Verbunden · Synchronisierung ausstehend
                            @endif
                        </p>
                    </div>

                    @if ($connection?->status->value === 'error')
                        <form method="POST" action="{{ route('provider.sync', 'hammerhead') }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-rose-50 px-3.5 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100">Erneut versuchen</button>
                        </form>
                    @elseif ($connection)
                        <span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700">Verbunden</span>
                    @elseif (config('activity-providers.hammerhead.enabled'))
                        <a href="{{ route('provider.connect', 'hammerhead') }}" class="w-fit rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">Verbinden</a>
                    @else
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-500">Nicht konfiguriert</span>
                    @endif
                </article>

                <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-center">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="m6 17 4-8h4l4 8M9 12h7M10 9 8 6"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-medium">Wahoo</h3>
                        <p class="mt-1 text-sm text-slate-500">Integration folgt</p>
                    </div>
                    <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-500">Folgt</span>
                </article>
            </div>
        </section>

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · Einstellungen</p>
            <p>Deine Zugangsdaten werden verschlüsselt gespeichert.</p>
        </footer>
    </main>
</body>
</html>
