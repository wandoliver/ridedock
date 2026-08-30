<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Details und Messdaten zu {{ $activity->name }} in RideDock.">
    <title>{{ $activity->name }} · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
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

    <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
        <section class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span>{{ $activity->started_at->locale('de')->isoFormat('dddd, D. MMMM YYYY · HH:mm') }}</span>
                    @foreach ($activity->sources->pluck('connection.provider.value')->unique() as $provider)
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ ucfirst($provider) }}</span>
                    @endforeach
                </div>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">{{ $activity->name }}</h1>
                <p class="mt-3 text-base text-slate-600">{{ str($activity->type)->replace('_', ' ')->headline() }}</p>
            </div>
            @if ($activity->rpe)
                <span class="w-fit rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-800">RPE {{ $activity->rpe }}</span>
            @endif
        </section>

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Leistungsübersicht">
            @foreach ([
                ['label' => 'Distanz', 'value' => $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.').' km'],
                ['label' => 'Fahrzeit', 'value' => $activity->moving_time_s === null ? '–' : sprintf('%d:%02d h', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60))],
                ['label' => 'Höhenmeter', 'value' => $activity->elevation_gain_m === null ? '–' : number_format($activity->elevation_gain_m, 0, ',', '.').' m'],
                ['label' => 'Ø Leistung', 'value' => $activity->average_power === null ? '–' : number_format($activity->average_power, 0, ',', '.').' W'],
            ] as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.03]">
                    <p class="text-sm text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <script type="application/json" id="activity-streams-data">{!! json_encode($activityStreams, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

        <section class="mt-6 grid gap-6 lg:grid-cols-[1.45fr_0.8fr]">
            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <p class="text-sm text-slate-500">GPS-Stream</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Route</h2>
                </div>
                @if (isset($activityStreams['latlng']))
                    <div id="ride-route" class="h-[28rem] bg-slate-100" aria-label="Route der Aktivität"></div>
                @else
                    <div class="grid h-72 place-items-center px-6 text-center text-sm text-slate-500">Für diese Aktivität ist keine GPS-Route vorhanden.</div>
                @endif
            </article>

            <div class="grid gap-6">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                    <p class="text-sm text-slate-500">Belastung</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Gefühl & Notizen</h2>
                    <div class="mt-6 flex items-center justify-between rounded-2xl bg-emerald-50 p-4">
                        <span class="text-sm text-emerald-800">Anstrengung</span>
                        <strong class="text-lg text-emerald-900">{{ $activity->rpe ? 'RPE '.$activity->rpe : 'Nicht bewertet' }}</strong>
                    </div>
                    <p class="mt-5 text-sm leading-6 text-slate-600">{{ $activity->notes ?: 'Noch keine Notiz zu dieser Fahrt.' }}</p>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                    <p class="text-sm text-slate-500">FIT-Ereignisse</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ $activity->events->count() }} {{ $activity->events->count() === 1 ? 'Schaltvorgang' : 'Schaltvorgänge' }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Aus dem aufgezeichneten Event-Stream der Aktivität.</p>
                </article>
            </div>
        </section>

        @php
            $charts = [
                'power' => ['label' => 'Leistung', 'unit' => 'W', 'color' => '#059669'],
                'heart_rate' => ['label' => 'Herzfrequenz', 'unit' => 'bpm', 'color' => '#e11d48'],
                'cadence' => ['label' => 'Kadenz', 'unit' => 'rpm', 'color' => '#2563eb'],
                'altitude' => ['label' => 'Höhe', 'unit' => 'm', 'color' => '#7c3aed'],
                'speed' => ['label' => 'Geschwindigkeit', 'unit' => 'km/h', 'color' => '#d97706', 'multiplier' => 3.6],
            ];
        @endphp
        @if (collect(array_keys($charts))->intersect(array_keys($activityStreams))->isNotEmpty())
            <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                <div>
                    <p class="text-sm text-slate-500">Aufzeichnung</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Messreihen</h2>
                </div>
                <div class="mt-7 grid gap-4 lg:grid-cols-2">
                    @foreach ($charts as $type => $chart)
                        @if (isset($activityStreams[$type]))
                            <article class="rounded-2xl border border-slate-200 p-5" data-stream-chart="{{ $type }}" data-unit="{{ $chart['unit'] }}" data-multiplier="{{ $chart['multiplier'] ?? 1 }}" data-color="{{ $chart['color'] }}">
                                <div class="flex items-baseline justify-between gap-4">
                                    <h3 class="font-medium">{{ $chart['label'] }}</h3>
                                    <span class="text-xs text-slate-400" data-chart-range></span>
                                </div>
                                <div class="mt-4 h-40" data-chart-canvas></div>
                            </article>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · private Aktivitätsansicht</p>
            <p>Daten aus deinen importierten Quellen.</p>
        </footer>
    </main>
</body>
</html>
