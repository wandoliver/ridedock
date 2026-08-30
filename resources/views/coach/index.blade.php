<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Fahrten analysieren und Training mit RideDock planen.">
    <title>Coach · RideDock</title>
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
            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
        @endif
        @if (session('analysis_error'))
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900" role="alert">{{ session('analysis_error') }}</div>
        @endif

        <section class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-emerald-700">RideDock Coach</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Was möchtest du tun?</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">Wähle eine Fahrt für eine konkrete Analyse. Der Wochenplan baut anschließend auf derselben Datengrundlage auf.</p>
            </div>
            <a href="{{ route('settings.training') }}" class="w-fit rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800">Trainingsprofil</a>
        </section>

        <section class="mt-8 grid gap-4 sm:grid-cols-2" aria-label="Coach-Funktionen">
            <a href="#rides" class="group rounded-3xl border border-emerald-200 bg-emerald-50 p-6 transition hover:border-emerald-300 hover:bg-emerald-100/70 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 19V9m5 10V5m5 14v-7m4 7V3"/></svg>
                    </span>
                    <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium {{ $aiConfigured ? 'text-emerald-800' : 'text-amber-800' }}">{{ $aiConfigured ? 'Verfügbar' : 'Konfiguration fehlt' }}</span>
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight">Fahrt analysieren</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-950/70">Belastung, Trainingswirkung und Empfehlung für die nächste Einheit.</p>
                <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-800">
                    Fahrt auswählen
                    <svg viewBox="0 0 20 20" class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                </span>
            </a>

            <article class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 5h14v14H5zM8 3v4m8-4v4M5 10h14"/></svg>
                    </span>
                    @if ($aiConfigured && ($profile?->available_days ?? []) !== [])
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Verfügbar</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">Profil ergänzen</span>
                    @endif
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight">Sieben-Tage-Plan</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Ein strukturierter Wochenplan aus Ziel, verfügbarer Zeit und aktueller Belastung.</p>
                @if ($aiConfigured && ($profile?->available_days ?? []) !== [])
                    <form method="POST" action="{{ route('coach.weekly-plan.store') }}" class="mt-5" data-weekly-plan-form>
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            {{ $latestWeeklyPlan?->status->value === 'completed' ? 'Plan neu erstellen' : 'Plan erstellen' }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('settings.training') }}" class="mt-5 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">Trainingsprofil vervollständigen</a>
                @endif
            </article>
        </section>

        <section class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-7 sm:px-8" data-weekly-plan-progress role="status" aria-live="polite" aria-busy="true" hidden>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium text-emerald-950">Wochenplan wird erstellt</h2>
                    <p class="mt-1 text-sm text-emerald-900/65">Der Coach verteilt Belastung und Erholung auf deine verfügbaren Tage.</p>
                </div>
                <span class="shrink-0 text-xs font-medium text-emerald-700">Bitte kurz warten</span>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-emerald-200" role="progressbar" aria-label="Fortschritt des Wochenplans" aria-valuetext="Wochenplan wird erstellt">
                <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-emerald-600"></span>
            </div>
        </section>

        <section class="mt-6">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.03]">
                <p class="text-sm text-slate-500">Trainingsprofil</p>
                @if ($profile)
                    <p class="mt-2 font-medium">{{ $profile->goal ?: 'Noch kein konkretes Ziel' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $profile->weekly_hours ? number_format($profile->weekly_hours, 1, ',', '.').' h pro Woche' : 'Zeitbudget noch offen' }}</p>
                @else
                    <p class="mt-2 font-medium">Noch nicht ausgefüllt</p>
                    <a href="{{ route('settings.training') }}" class="mt-2 inline-flex text-sm font-medium text-emerald-700">Profil anlegen</a>
                @endif
            </article>
        </section>

        @if ($latestWeeklyPlan?->status->value === 'completed')
            @php
                $weeklyPlan = $latestWeeklyPlan->output ?? [];
                $totalMinutes = (int) data_get($weeklyPlan, 'total_duration_minutes', 0);
                $totalHours = intdiv($totalMinutes, 60);
                $remainingMinutes = $totalMinutes % 60;
                $totalDurationLabel = $totalHours > 0
                    ? $totalHours.' h'.($remainingMinutes > 0 ? ' '.$remainingMinutes.' min' : '')
                    : $remainingMinutes.' min';
            @endphp
            <section id="weekly-plan" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="weekly-plan-heading" data-weekly-plan-panel>
                <div class="bg-gradient-to-br from-emerald-50 via-white to-white px-6 py-7 sm:px-8 sm:py-8">
                    <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
                        <div class="max-w-3xl">
                            <p class="text-sm font-medium text-emerald-700">Deine kommenden sieben Tage</p>
                            <h2 id="weekly-plan-heading" class="mt-1 text-2xl font-semibold tracking-tight">Sieben-Tage-Plan</h2>
                            <p class="mt-4 text-lg font-medium leading-7 text-slate-800">{{ data_get($weeklyPlan, 'weekly_goal') }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($weeklyPlan, 'overview') }}</p>
                        </div>
                        <div class="w-fit shrink-0 rounded-2xl border border-emerald-100 bg-white px-4 py-3 shadow-sm shadow-emerald-950/[0.04]">
                            <p class="whitespace-nowrap text-lg font-semibold text-slate-900">{{ $totalDurationLabel }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">Geplanter Umfang</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 bg-stone-50/70 p-4 sm:p-6 md:grid-cols-2">
                    @foreach ((array) data_get($weeklyPlan, 'days', []) as $day)
                        @php
                            $dayType = data_get($day, 'type');
                            $dayDate = \Carbon\CarbonImmutable::parse((string) data_get($day, 'date'))->locale('de');
                            [$typeLabel, $typeClasses, $accentClasses] = match ($dayType) {
                                'ride' => ['Rad', 'bg-emerald-50 text-emerald-700', 'border-l-emerald-500'],
                                'mobility' => ['Beweglichkeit', 'bg-violet-50 text-violet-700', 'border-l-violet-400'],
                                default => ['Ruhetag', 'bg-slate-100 text-slate-600', 'border-l-slate-300'],
                            };
                        @endphp
                        <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 border-l-4 {{ $accentClasses }} bg-white p-5 shadow-sm shadow-slate-950/[0.025]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $dayDate->isoFormat('dddd') }}</p>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $dayDate->isoFormat('D. MMMM') }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $typeClasses }}">{{ $typeLabel }}</span>
                            </div>

                            <h3 class="mt-5 text-base font-semibold leading-6 text-slate-900">{{ data_get($day, 'title') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($day, 'details') }}</p>

                            <div class="mt-auto flex flex-wrap gap-2 pt-5 text-xs">
                                @if (data_get($day, 'duration_minutes') !== null)
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-medium text-slate-700">{{ data_get($day, 'duration_minutes') }} min</span>
                                @endif
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-slate-600">{{ data_get($day, 'intensity') }}</span>
                            </div>

                            <p class="mt-4 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-400">Warum: {{ data_get($day, 'rationale') }}</p>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-6 py-4 text-xs text-slate-400 sm:px-8">
                    KI-generierter Trainingsvorschlag · passe Einheiten an Tagesform und Wohlbefinden an · {{ $latestWeeklyPlan->completed_at?->locale('de')->diffForHumans() }}
                </div>
            </section>
        @endif

        <section id="rides" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="rides-heading">
            <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                <p class="text-sm text-slate-500">Aktivitätsanalyse</p>
                <h2 id="rides-heading" class="mt-1 text-xl font-semibold tracking-tight">Fahrt auswählen</h2>
            </div>

            @forelse ($activities as $activity)
                @php($analysisStatus = $activity->latestAiAnalysis?->status->value)
                <a href="{{ route('activities.show', $activity) }}#ai-analysis-heading" class="group grid gap-4 border-b border-slate-100 px-6 py-5 transition last:border-0 hover:bg-emerald-50/40 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium transition group-hover:text-emerald-800">{{ $activity->name }}</h3>
                            @if (in_array($analysisStatus, ['pending', 'processing'], true))
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Analyse läuft</span>
                            @elseif ($analysisStatus === 'completed')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Analysiert</span>
                            @elseif ($analysisStatus === 'failed')
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Erneut versuchen</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $activity->started_at->locale('de')->isoFormat('ddd, D. MMM YYYY · HH:mm') }}</p>
                    </div>
                    <div class="flex items-center gap-5 text-sm">
                        <span><strong class="font-medium text-slate-900">{{ $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-slate-500">km</span></span>
                        <span class="rounded-xl bg-slate-950 px-3.5 py-2 font-medium text-white transition group-hover:bg-emerald-700">{{ $analysisStatus === 'completed' ? 'Analyse öffnen' : 'Auswählen' }}</span>
                    </div>
                </a>
            @empty
                <div class="px-6 py-14 text-center sm:px-8">
                    <h3 class="font-medium">Noch keine Fahrt vorhanden</h3>
                    <p class="mt-2 text-sm text-slate-500">Sobald eine Aktivität importiert wurde, kannst du sie hier auswählen.</p>
                </div>
            @endforelse
        </section>

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · Coach</p>
            <p>Analysen entstehen nur auf deine Anfrage.</p>
        </footer>
    </main>
</body>
</html>
