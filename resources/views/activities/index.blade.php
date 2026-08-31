<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Alle importierten Fahrradaktivitäten in RideDock.">
    <title>Aktivitäten · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard', ['period' => $period->value]) }}" class="flex items-center gap-3" aria-label="Zur RideDock Übersicht">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <a href="{{ route('dashboard', ['period' => $period->value]) }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 4-6 6 6 6"/></svg>
                Übersicht
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        <section class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-emerald-700">Dein Archiv</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Aktivitäten</h1>
                <p class="mt-3 text-base text-slate-600">{{ number_format($activities->total(), 0, ',', '.') }} {{ $activities->total() === 1 ? 'Aktivität' : 'Aktivitäten' }} · {{ $periodLabel }}</p>
            </div>
            <nav class="flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Zeitraum auswählen">
                @foreach ($periods as $option)
                    <a href="{{ route('activities.index', ['period' => $option->value]) }}"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $period === $option ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
                        @if ($period === $option) aria-current="page" @endif>
                        {{ $option->shortLabel($now) }}
                    </a>
                @endforeach
            </nav>
        </section>

        <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-label="Aktivitätsliste">
            @forelse ($activities as $activity)
                <a href="{{ route('activities.show', $activity) }}" class="group grid gap-4 border-b border-slate-100 px-6 py-5 transition last:border-0 hover:bg-emerald-50/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium transition group-hover:text-emerald-800">{{ $activity->name }}</h2>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-medium text-slate-500">{{ str($activity->type)->replace('_', ' ')->headline() }}</span>
                            @include('activities._analysis-status', ['status' => $activity->latestAiAnalysis?->status->value])
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $activity->started_at->locale('de')->isoFormat('ddd, D. MMM YYYY · HH:mm') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <span><strong class="font-medium text-slate-900">{{ $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-slate-500">km</span></span>
                        <span><strong class="font-medium text-slate-900">{{ $activity->elevation_gain_m === null ? '–' : number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-slate-500">hm</span></span>
                        <span><strong class="font-medium text-slate-900">{{ $activity->moving_time_s === null ? '–' : sprintf('%d:%02d', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60)) }}</strong> <span class="text-slate-500">h</span></span>
                        <svg viewBox="0 0 20 20" class="hidden size-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-700 sm:block" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                    </div>
                </a>
            @empty
                <div class="px-6 py-16 text-center sm:px-8">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                    </span>
                    <h2 class="mt-4 font-medium">Keine Aktivitäten in diesem Zeitraum</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Wähle einen anderen Zeitraum oder synchronisiere eine Datenquelle.</p>
                </div>
            @endforelse
        </section>

        @if ($activities->hasPages())
            <nav class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Seitennavigation">
                <p class="text-sm text-slate-500">{{ $activities->firstItem() }}–{{ $activities->lastItem() }} von {{ number_format($activities->total(), 0, ',', '.') }}</p>
                <div class="flex items-center gap-2">
                    @if ($activities->onFirstPage())
                        <span class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300">Zurück</span>
                    @else
                        <a href="{{ $activities->previousPageUrl() }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Zurück</a>
                    @endif
                    <span class="px-2 text-sm text-slate-500">Seite {{ $activities->currentPage() }} von {{ $activities->lastPage() }}</span>
                    @if ($activities->hasMorePages())
                        <a href="{{ $activities->nextPageUrl() }}" class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">Weiter</a>
                    @else
                        <span class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300">Weiter</span>
                    @endif
                </div>
            </nav>
        @endif

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · dein privates Aktivitätsarchiv</p>
            <p>Neueste Aktivitäten zuerst.</p>
        </footer>
    </main>
</body>
</html>
