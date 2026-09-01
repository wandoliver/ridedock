<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('ui.activity.meta', ['activity' => $activity->name]) }}">
    @if ($latestAiAnalysis && in_array($latestAiAnalysis->status->value, ['pending', 'processing'], true))
        <meta http-equiv="refresh" content="5">
    @endif
    <title>{{ $activity->name }} · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
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

    <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
        @endif
        @if (session('analysis_error'))
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900" role="alert">{{ session('analysis_error') }}</div>
        @endif

        <section class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span>{{ $activity->started_at->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY · HH:mm') }}</span>
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

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('ui.activity.performance') }}">
            @foreach ([
                ['label' => __('ui.activity.distance'), 'value' => $activity->distance_m === null ? '–' : \Illuminate\Support\Number::format($activity->distance_m / 1000, 1, locale: app()->getLocale()).' km'],
                ['label' => __('ui.activity.ride_time'), 'value' => $activity->moving_time_s === null ? '–' : sprintf('%d:%02d h', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60))],
                ['label' => __('ui.activity.elevation'), 'value' => $activity->elevation_gain_m === null ? '–' : \Illuminate\Support\Number::format($activity->elevation_gain_m, 0, locale: app()->getLocale()).' m'],
                ['label' => __('ui.activity.avg_power'), 'value' => $activity->average_power === null ? '–' : \Illuminate\Support\Number::format($activity->average_power, 0, locale: app()->getLocale()).' W'],
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
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.activity.route') }}</h2>
                </div>
                @if (isset($activityStreams['latlng']))
                    <div id="ride-route" class="h-[28rem] bg-slate-100" aria-label="{{ __('ui.activity.activity_route') }}"></div>
                @else
                    <div class="grid h-72 place-items-center px-6 text-center text-sm text-slate-500">{{ __('ui.activity.no_route') }}</div>
                @endif
            </article>

            <div class="grid gap-6">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                    <p class="text-sm text-slate-500">{{ __('ui.activity.load') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.activity.feeling_notes') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.activity.feeling_copy') }}</p>

                    <form method="POST" action="{{ route('activities.feedback.update', $activity) }}" class="mt-6">
                        @csrf
                        @method('PATCH')

                        <fieldset>
                            <legend class="text-sm font-medium text-slate-700">{{ __('ui.activity.effort') }}</legend>
                            <div class="mt-3 grid grid-cols-5 gap-2" aria-label="{{ __('ui.activity.effort_scale') }}">
                                @foreach (range(1, 10) as $rpe)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="rpe"
                                            value="{{ $rpe }}"
                                            class="peer sr-only"
                                            @checked((string) old('rpe', $activity->rpe) === (string) $rpe)
                                        >
                                        <span class="grid min-h-10 place-items-center rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-600 transition hover:border-emerald-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-emerald-600">{{ $rpe }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
                                <span>{{ __('ui.activity.very_easy') }}</span>
                                <span>{{ __('ui.activity.maximal') }}</span>
                            </div>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                                <input
                                    type="radio"
                                    name="rpe"
                                    value=""
                                    class="size-4 border-slate-300 text-emerald-600 focus:ring-emerald-600"
                                    @checked(old('rpe', $activity->rpe) === null || old('rpe', $activity->rpe) === '')
                                >
                                {{ __('ui.activity.no_rating') }}
                            </label>
                            @error('rpe')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <label for="activity-notes" class="mt-6 block text-sm font-medium text-slate-700">{{ __('ui.activity.note') }}</label>
                        <textarea
                            id="activity-notes"
                            name="notes"
                            rows="4"
                            maxlength="10000"
                            placeholder="{{ __('ui.activity.note_placeholder') }}"
                            class="mt-2 block w-full resize-y rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm leading-6 text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >{{ old('notes', $activity->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="mt-4 w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">{{ __('ui.activity.save_feeling') }}</button>
                    </form>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                    <p class="text-sm text-slate-500">{{ __('ui.activity.fit_events') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ $activity->events->count() === 1 ? __('ui.activity.shift_one', ['count' => 1]) : __('ui.activity.shift_many', ['count' => $activity->events->count()]) }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.activity.events_copy') }}</p>
                </article>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="ai-analysis-heading">
            <div class="flex flex-col justify-between gap-5 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:px-8">
                <div>
                    <p class="text-sm text-emerald-700">RideDock Coach</p>
                    <h2 id="ai-analysis-heading" class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.activity.analyze_ride') }}</h2>
                </div>

                @if ($aiConfigured && (! $latestAiAnalysis || in_array($latestAiAnalysis->status->value, ['completed', 'failed'], true)))
                    <form method="POST" action="{{ route('activities.ai-analysis.store', $activity) }}" data-activity-analysis-form>
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            {{ $latestAiAnalysis?->status->value === 'completed' ? __('ui.activity.reanalyze') : __('ui.activity.start_analysis') }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="px-6 py-8 sm:px-8" data-activity-analysis-progress role="status" aria-live="polite" aria-busy="true" hidden>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-medium">{{ __('ui.activity.analysis_pending') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('ui.activity.analysis_copy') }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-emerald-700">{{ __('ui.common.please_wait') }}</span>
                </div>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-emerald-100" role="progressbar" aria-label="{{ __('ui.activity.analysis_progress') }}" aria-valuetext="{{ __('ui.activity.analysis_pending') }}">
                    <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-emerald-600"></span>
                </div>
            </div>

            @if (! $aiConfigured)
                <div class="px-6 py-8 sm:px-8">
                    <h3 class="font-medium">{{ __('ui.activity.ai_missing') }}</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ __('ui.activity.ai_missing_copy') }}</p>
                    <a href="{{ route('settings.training') }}" class="mt-4 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.activity.configure_ai') }}</a>
                </div>
            @elseif (! $latestAiAnalysis)
                <div class="grid gap-6 px-6 py-8 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <h3 class="font-medium">{{ __('ui.activity.no_analysis') }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ __('ui.activity.no_analysis_copy') }}</p>
                    </div>
                    <a href="{{ route('settings.training') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.activity.check_profile') }}</a>
                </div>
            @elseif (in_array($latestAiAnalysis->status->value, ['pending', 'processing'], true))
                @php
                    $analysisIsProcessing = $latestAiAnalysis->status->value === 'processing';
                    $analysisStatus = $analysisIsProcessing ? __('ui.activity.analysis_pending') : __('ui.activity.waiting');
                @endphp
                <div class="px-6 py-8 sm:px-8" role="status" aria-live="polite" aria-busy="true">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-medium">{{ $analysisStatus }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $analysisIsProcessing ? __('ui.activity.analysis_copy') : __('ui.activity.waiting_copy') }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-medium text-emerald-700">{{ __('ui.activity.auto_refresh') }}</span>
                    </div>
                    <div
                        class="mt-5 h-2 overflow-hidden rounded-full bg-emerald-100"
                        role="progressbar"
                        aria-label="{{ __('ui.activity.analysis_progress') }}"
                        aria-valuetext="{{ $analysisStatus }}"
                    >
                        <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-emerald-600"></span>
                    </div>
                </div>
            @elseif ($latestAiAnalysis->status->value === 'failed')
                <div class="px-6 py-8 sm:px-8">
                    <h3 class="font-medium text-rose-800">{{ __('ui.activity.failed') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.activity.failed_copy') }}</p>
                </div>
            @else
                @php
                    $analysisOutput = $latestAiAnalysis->output ?? [];
                    $nextSession = $nextPlannedSession ?? (array) data_get($analysisOutput, 'next_session', []);
                    $nextSessionUsesWeeklyPlan = $nextPlannedSession !== null;
                @endphp
                <div class="grid gap-8 px-6 py-8 lg:grid-cols-[1.25fr_0.75fr] sm:px-8">
                    <div>
                        <p class="text-lg leading-8 text-slate-800">{{ data_get($analysisOutput, 'summary') }}</p>
                        <div class="mt-6 rounded-2xl bg-emerald-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">{{ __('ui.activity.training_effect') }}</p>
                            <p class="mt-2 text-sm leading-6 text-emerald-950">{{ data_get($analysisOutput, 'training_effect') }}</p>
                        </div>

                        @if (data_get($analysisOutput, 'observations', []) !== [])
                            <div class="mt-7 space-y-5">
                                @foreach ((array) data_get($analysisOutput, 'observations', []) as $observation)
                                    <article>
                                        <h3 class="font-medium">{{ data_get($observation, 'title') }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ data_get($observation, 'detail') }}</p>
                                        <p class="mt-2 text-xs text-slate-400">{{ __('ui.activity.basis') }} {{ implode(', ', (array) data_get($observation, 'evidence', [])) }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <aside class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $nextSessionUsesWeeklyPlan ? __('ui.activity.next_plan_day') : __('ui.activity.next_session') }}</p>
                            @if ($nextSessionUsesWeeklyPlan)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('ui.activity.from_week_plan') }}</span>
                            @endif
                        </div>
                        @if ($nextSessionUsesWeeklyPlan)
                            <p class="mt-3 text-xs font-medium text-slate-400">{{ \Carbon\CarbonImmutable::parse((string) data_get($nextSession, 'date'))->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}</p>
                        @endif
                        <h3 class="mt-2 text-lg font-semibold">{{ data_get($nextSession, 'title') }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if (data_get($nextSession, 'duration_minutes'))
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ data_get($nextSession, 'duration_minutes') }} min</span>
                            @endif
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ data_get($nextSession, 'intensity') }}</span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-700">{{ data_get($nextSession, 'details') }}</p>
                        <p class="mt-4 text-sm leading-6 text-slate-500">{{ data_get($nextSession, 'rationale') }}</p>

                        @if ($nextSessionUsesWeeklyPlan)
                            <a href="{{ route('coach.index') }}#weekly-plan" class="mt-5 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.activity.open_week_plan') }}</a>
                        @endif

                        @if (data_get($analysisOutput, 'data_gaps', []) !== [])
                            <div class="mt-5 border-t border-slate-100 pt-4">
                                <p class="text-xs font-medium text-slate-500">{{ __('ui.activity.data_gaps') }}</p>
                                <ul class="mt-2 space-y-1 text-xs leading-5 text-slate-500">
                                    @foreach ((array) data_get($analysisOutput, 'data_gaps', []) as $gap)
                                        <li>· {{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </aside>
                </div>
                <div class="border-t border-slate-100 px-6 py-4 text-xs text-slate-400 sm:px-8">{{ __('ui.common.ai_disclaimer', ['time' => $latestAiAnalysis->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}</div>
            @endif
        </section>

        @php
            $charts = [
                'power' => ['label' => __('ui.activity.power'), 'unit' => 'W', 'color' => '#059669'],
                'heart_rate' => ['label' => __('ui.activity.heart_rate'), 'unit' => 'bpm', 'color' => '#e11d48'],
                'cadence' => ['label' => __('ui.activity.cadence'), 'unit' => 'rpm', 'color' => '#2563eb'],
                'altitude' => ['label' => __('ui.activity.altitude'), 'unit' => 'm', 'color' => '#7c3aed'],
                'speed' => ['label' => __('ui.activity.speed'), 'unit' => 'km/h', 'color' => '#d97706', 'multiplier' => 3.6],
            ];
        @endphp
        @if (collect(array_keys($charts))->intersect(array_keys($activityStreams))->isNotEmpty())
            <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/[0.03] sm:p-8">
                <div>
                    <p class="text-sm text-slate-500">{{ __('ui.activity.recording') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.activity.series') }}</h2>
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
            <p>{{ __('ui.activity.footer_left') }}</p>
            <p>{{ __('ui.activity.footer_right') }}</p>
        </footer>
    </main>
</body>
</html>
