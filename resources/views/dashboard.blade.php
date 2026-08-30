<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RideDock vereint deine Radtouren, FIT-Daten und Heatmap in einem privaten, offenen Zuhause.">
    <title>RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="RideDock Startseite">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true">
                        <path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <div class="flex items-center gap-2">
                @auth
                    <span class="hidden text-sm text-slate-500 sm:inline">{{ auth()->user()->name }}</span>
                    <a href="{{ route('coach.index') }}" class="rounded-xl px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-900">Coach</a>
                    <a href="{{ route('settings.connections') }}" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Einstellungen</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">Abmelden</button>
                    </form>
                @else
                    <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">Konto erstellen</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14">
        @if (session('status'))
            <div class="mb-8 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @guest
            <section class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_26rem] lg:gap-16">
                <div class="pt-4 lg:pt-12">
                <p class="mb-3 text-sm font-medium text-emerald-700">Deine Raddaten. An einem Ort.</p>
                <h1 class="max-w-2xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">Fahrten verstehen, Daten behalten.</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">RideDock sammelt deine Aktivitäten, wertet FIT-Dateien aus und zeigt deine persönliche Heatmap.</p>
                </div>

                <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm shadow-slate-950/[0.03] sm:p-9" aria-labelledby="dashboard-login-heading">
                    <p class="text-sm font-medium text-emerald-700">Willkommen zurück</p>
                    <h2 id="dashboard-login-heading" class="mt-2 text-3xl font-semibold tracking-tight">Anmelden</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Öffne deine private Fahrtenübersicht und verwalte deine Verbindungen.</p>
                    <div class="mt-7">
                        @include('auth._login-form')
                    </div>
                    <p class="mt-6 text-center text-sm text-slate-500">Noch kein Konto? <a href="{{ route('register') }}" class="font-medium text-emerald-700 hover:text-emerald-900">Konto erstellen</a></p>
                </section>
            </section>
        @else
            @if ($connectionErrorCount > 0)
                <a href="{{ route('settings.connections') }}" class="mb-8 flex items-center justify-between gap-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900 transition hover:bg-rose-100">
                    <span>{{ $connectionErrorCount === 1 ? 'Eine Datenquelle benötigt Aufmerksamkeit.' : $connectionErrorCount.' Datenquellen benötigen Aufmerksamkeit.' }}</span>
                    <span class="font-medium">Verbindungen prüfen</span>
                </a>
            @endif

        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end" aria-label="Zeitraumfilter">
            <div>
                <p class="text-sm text-slate-500">Kennzahlen & Heatmap</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ $periodLabel }}</h2>
            </div>
            <nav class="flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Zeitraum auswählen">
                @foreach ($periods as $option)
                    <a href="{{ route('dashboard', ['period' => $option->value]) }}"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $period === $option ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
                        @if ($period === $option) aria-current="page" @endif>
                        {{ $option->shortLabel($now) }}
                    </a>
                @endforeach
            </nav>
        </section>

        <section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Übersicht {{ $periodLabel }}">
            @foreach ([
                ['label' => 'Aktivitäten', 'value' => number_format($activityCount, 0, ',', '.')],
                ['label' => 'Distanz', 'value' => number_format($distanceKilometres, 0, ',', '.').' km'],
                ['label' => 'Höhenmeter', 'value' => number_format($elevationMetres, 0, ',', '.').' m'],
                ['label' => 'Zeit im Sattel', 'value' => number_format($movingHours, 1, ',', '.').' h'],
            ] as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.03]">
                    <p class="text-sm text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-5 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:px-8" aria-label="RideDock Coach">
            <div>
                <p class="text-sm font-medium text-emerald-700">RideDock Coach</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">Training analysieren</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-950/70">Wähle eine importierte Fahrt und erhalte eine Analyse auf Basis deines Trainingsprofils.</p>
            </div>
            <a href="{{ route('coach.index') }}" class="mt-4 inline-flex shrink-0 items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 sm:mt-0">
                Coach öffnen
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
            </a>
        </section>

        <section class="mt-6">
            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 text-white shadow-sm">
                <div class="relative min-h-[28rem]" data-heatmap-frame>
                    <div id="ride-heatmap" class="absolute inset-0" aria-label="Interaktive Heatmap deiner Routen"></div>
                    <script type="application/json" id="ride-heatmap-data">{!! json_encode($heatmapRoutes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-[500] flex items-start justify-between gap-3 p-4 sm:p-5">
                        <span class="rounded-full bg-slate-950/70 px-3 py-1.5 text-xs text-slate-100 ring-1 ring-white/15 backdrop-blur">{{ count($heatmapRoutes) }} {{ count($heatmapRoutes) === 1 ? 'Route' : 'Routen' }}</span>
                        <button type="button" class="pointer-events-auto inline-flex items-center gap-2 rounded-xl bg-white/95 px-3 py-2 text-xs font-medium text-slate-800 shadow-sm ring-1 ring-slate-950/10 backdrop-blur transition hover:bg-white" data-heatmap-expand aria-pressed="false">
                            <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 3H3v4M13 3h4v4M7 17H3v-4m10 4h4v-4"/></svg>
                            <span data-heatmap-expand-label>Vergrößern</span>
                        </button>
                    </div>
                    <div class="pointer-events-none absolute bottom-5 left-5 z-[500] flex flex-wrap gap-2 text-xs text-slate-100 sm:bottom-6 sm:left-6">
                        <span class="rounded-lg bg-slate-950/70 px-2.5 py-1.5 ring-1 ring-white/10 backdrop-blur">{{ number_format($privacyTrimMetres, 0, ',', '.') }} m Privacy-Trim</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]">
            <div class="flex flex-col justify-between gap-4 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:px-8">
                <div>
                    <p class="text-sm text-slate-500">Im gewählten Zeitraum</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">Aktivitäten</h2>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">Neueste 5</span>
                    <a href="{{ route('activities.index', ['period' => $period->value]) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 transition hover:text-emerald-900">
                        Alle Aktivitäten
                        <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                    </a>
                </div>
            </div>

            @forelse ($activities as $activity)
                <a href="{{ route('activities.show', $activity) }}" class="group grid gap-4 border-b border-slate-100 px-6 py-5 transition last:border-0 hover:bg-emerald-50/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <p class="font-medium transition group-hover:text-emerald-800">{{ $activity->name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $activity->started_at->locale('de')->isoFormat('ddd, D. MMM · HH:mm') }}</p>
                    </div>
                    <div class="flex items-center gap-6 text-sm">
                        <span><strong class="font-medium text-slate-900">{{ number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-slate-500">km</span></span>
                        <span><strong class="font-medium text-slate-900">{{ number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-slate-500">hm</span></span>
                        @if ($activity->average_power)
                            <span><strong class="font-medium text-slate-900">{{ number_format($activity->average_power, 0, ',', '.') }}</strong> <span class="text-slate-500">W</span></span>
                        @endif
                        <svg viewBox="0 0 20 20" class="hidden size-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-700 sm:block" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                    </div>
                </a>
            @empty
                <div class="px-6 py-14 text-center sm:px-8">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                    </span>
                    <h3 class="mt-4 font-medium">Keine Aktivitäten in diesem Zeitraum</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Wähle einen anderen Zeitraum, um weitere Fahrten zu sehen.</p>
                </div>
            @endforelse
        </section>
        @endguest

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · gebaut für Datenhoheit und Open Source</p>
            <p>Keine Werbung. Kein Datenverkauf.</p>
        </footer>
    </main>
</body>
</html>
