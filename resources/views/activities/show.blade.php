<x-layout :title="$activity->name">
@if ($latestAiAnalysis && in_array($latestAiAnalysis->status->value, ['pending', 'processing'], true))<span hidden data-ai-status-url="{{ route('ai-analyses.status', $latestAiAnalysis) }}"></span><noscript><meta http-equiv="refresh" content="10"></noscript>@endif
<x-topbar><x-slot:context><a href="{{ route('activities.index') }}" class="text-[13px] text-ink-50">‹ {{ __('ui.dashboard.activities') }}</a></x-slot:context>
<x-slot:center><div><h1 class="text-[15px] font-bold">{{ $activity->name }}</h1><p class="text-xs text-ink-50">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMMM YYYY · HH:mm') }} · {{ $activity->sources->pluck('connection.provider.value')->unique()->map(fn($p)=>ucfirst($p))->implode(' · ') }}</p></div></x-slot:center>
<x-slot:actions>                @if ($aiConfigured && (! $latestAiAnalysis || in_array($latestAiAnalysis->status->value, ['completed', 'failed'], true)))
                    <form method="POST" action="{{ route('activities.ai-analysis.store', $activity) }}" data-activity-analysis-form>
                        @csrf
                        <button type="submit" class="rounded-lg bg-ink px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                            {{ $latestAiAnalysis?->status->value === 'completed' ? __('ui.activity.reanalyze') : __('ui.activity.start_analysis') }}
                        </button>
                    </form>
                @endif</x-slot:actions></x-topbar>
<x-flash />
<script type="application/json" id="activity-streams-data">{!! json_encode($activityStreams, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<div class="rd-shell"><main class="rd-workspace">            <article class="overflow-hidden rounded-lg border border-line bg-white  ">
                <div class="border-b border-line px-3.5 py-2.5">

                    <h2 class="text-sm font-semibold tracking-tight">{{ __('ui.activity.route') }}</h2>
                </div>
                @if (isset($activityStreams['latlng']))
                    <div id="ride-route" class="h-[340px] bg-canvas" aria-label="{{ __('ui.activity.activity_route') }}"></div>
                @else
                    <div class="grid h-72 place-items-center px-6 text-center text-sm text-ink-50">{{ __('ui.activity.no_route') }}</div>
                @endif
            </article>        @php
            $charts = [
                'power' => ['label' => __('ui.activity.power'), 'unit' => 'W', 'color' => '#1f9a6a'],
                'heart_rate' => ['label' => __('ui.activity.heart_rate'), 'unit' => 'bpm', 'color' => '#171a1f'],
                'cadence' => ['label' => __('ui.activity.cadence'), 'unit' => 'rpm', 'color' => '#171a1f'],
                'altitude' => ['label' => __('ui.activity.altitude'), 'unit' => 'm', 'color' => '#171a1f'],
                'speed' => ['label' => __('ui.activity.speed'), 'unit' => 'km/h', 'color' => '#1f9a6a', 'multiplier' => 3.6],
            ];
        @endphp
        @if (collect(array_keys($charts))->intersect(array_keys($activityStreams))->isNotEmpty())
            <section class="mt-2 rounded-lg border border-line bg-white p-3.5">
                <div>
                    <p class="text-sm text-ink-50">{{ __('ui.activity.recording') }}</p>
                    <h2 class="mt-1 text-sm font-semibold tracking-tight">{{ __('ui.activity.series') }}</h2>
                </div>
                <div class="mt-3 flex flex-wrap gap-3 text-xs font-semibold text-ink-50">@foreach(['speed', 'heart_rate', 'altitude'] as $type)@if(isset($activityStreams[$type]))<span style="color: {{ $charts[$type]['color'] }}">■ {{ $charts[$type]['label'] }}</span>@endif @endforeach</div>
                <div class="mt-3 h-[150px]" data-combined-chart aria-label="{{ __('ui.activity.series') }}"></div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                @foreach(['speed', 'heart_rate', 'altitude'] as $type)
                @if(isset($activityStreams[$type]))
                @php
                    $values = collect($activityStreams[$type])->map(fn($sample) => is_array($sample) ? ($sample['value'] ?? null) : $sample)->filter(fn($value) => is_numeric($value));
                @endphp
                <div class="rounded-md bg-canvas p-2.5"><p class="text-ink-50">{{ $charts[$type]['label'] }}</p><p class="mt-1 font-semibold">@if($values->isNotEmpty()){{ round($values->avg() * ($charts[$type]['multiplier'] ?? 1), 1) }} · max {{ round($values->max() * ($charts[$type]['multiplier'] ?? 1), 1) }} {{ $charts[$type]['unit'] }}@else – @endif</p></div>
                @endif @endforeach
                <div class="rounded-md bg-canvas p-2.5"><p class="text-ink-50">{{ __('ui.activity.fit_events') }}</p><p class="mt-1 font-semibold">{{ $activity->events->count() }}</p></div></div>
                <details class="mt-4 text-xs text-ink-50"><summary>{{ __('ui.activity.series') }} · {{ __('design.details') }}</summary>
                <div class="mt-3 grid gap-2">
                    @foreach ($charts as $type => $chart)
                        @if (isset($activityStreams[$type]))
                            <article class="rounded-lg border border-line p-3" data-stream-chart="{{ $type }}" data-unit="{{ $chart['unit'] }}" data-multiplier="{{ $chart['multiplier'] ?? 1 }}" data-color="{{ $chart['color'] }}">
                                <div class="flex items-baseline justify-between gap-4">
                                    <h3 class="font-medium">{{ $chart['label'] }}</h3>
                                    <span class="text-xs text-ink-45" data-chart-range></span>
                                </div>
                                <div class="mt-4 h-[150px]" data-chart-canvas></div>
                            </article>
                        @endif
                    @endforeach
                </div></details>
            </section>
        @endif


<x-info-box>{{ $activity->events->count() === 1 ? __('ui.activity.shift_one', ['count'=>1]) : __('ui.activity.shift_many', ['count'=>$activity->events->count()]) }}</x-info-box>
</main><aside class="rd-panel">        <section class="grid grid-cols-2 gap-x-4 gap-y-2" aria-label="{{ __('ui.activity.performance') }}">
            @foreach ([
                ['label' => __('ui.activity.distance'), 'value' => $activity->distance_m === null ? '–' : \Illuminate\Support\Number::format($activity->distance_m / 1000, 1, locale: app()->getLocale()).' km'],
                ['label' => __('ui.activity.ride_time'), 'value' => $activity->moving_time_s === null ? '–' : sprintf('%d:%02d h', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60))],
                ['label' => __('ui.activity.elevation'), 'value' => $activity->elevation_gain_m === null ? '–' : \Illuminate\Support\Number::format($activity->elevation_gain_m, 0, locale: app()->getLocale()).' m'],
                ['label' => __('ui.activity.speed'), 'value' => $activity->average_speed_mps === null ? '–' : \Illuminate\Support\Number::format($activity->average_speed_mps * 3.6, 1, locale: app()->getLocale()).' km/h'],
            ] as $metric)
                <article class="">
                    <p class="text-xs font-semibold text-ink-50">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-[22px] font-semibold tracking-tight">{{ $metric['value'] }}</p>
                </article>
            @endforeach
        </section>
@if($activity->average_power)<p class="mt-2 text-xs text-ink-50">{{ __('ui.activity.avg_power') }} · {{ $activity->average_power }} W</p>@endif
<section class="mt-4 rounded-lg bg-canvas px-3.5 py-3"><h2 class="!text-sm">{{ __('ui.activity.feeling_notes') }} · RPE {{ $activity->rpe ?? '–' }}</h2>                    <form method="POST" action="{{ route('activities.feedback.update', $activity) }}" class="mt-3">
                        @csrf
                        @method('PATCH')

                        <fieldset>
                            <legend class="sr-only">{{ __('ui.activity.effort') }}</legend>
                            <div class="mt-3 grid grid-cols-10 gap-[3px]" aria-label="{{ __('ui.activity.effort_scale') }}">
                                @foreach (range(1, 10) as $rpe)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="rpe"
                                            value="{{ $rpe }}"
                                            class="peer sr-only"
                                            @checked((string) old('rpe', $activity->rpe) === (string) $rpe)
                                        >
                                        <span class="grid h-[22px] place-items-center rounded-lg border border-line bg-white text-sm font-medium text-ink-60 transition hover:border-plan-dash peer-checked:border-plan-dash peer-checked:bg-plan-soft peer-checked:text-plan-text peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-emerald-600">{{ $rpe }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-ink-45">
                                <span>{{ __('ui.activity.very_easy') }}</span>
                                <span>{{ __('ui.activity.maximal') }}</span>
                            </div>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs text-ink-50">
                                <input
                                    type="radio"
                                    name="rpe"
                                    value=""
                                    class="size-4 border-border-input text-plan-text focus:ring-emerald-600"
                                    @checked(old('rpe', $activity->rpe) === null || old('rpe', $activity->rpe) === '')
                                >
                                {{ __('ui.activity.no_rating') }}
                            </label>
                            @error('rpe')
                                <p class="mt-2 text-sm text-ink-60">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <details class="mt-3"><summary class="text-xs font-semibold text-plan-text">{{ __('ui.activity.note') }} ›</summary><label for="activity-notes" class="mt-3 block text-sm font-medium text-ink-70">{{ __('ui.activity.note') }}</label>
                        <textarea
                            id="activity-notes"
                            name="notes"
                            rows="2"
                            maxlength="10000"
                            placeholder="{{ __('ui.activity.note_placeholder') }}"
                            class="mt-2 block w-full resize-y rounded-lg border border-line bg-white px-3.5 py-3 text-sm leading-6 text-ink  outline-none transition placeholder:text-ink-45 focus:border-plan-dash focus:ring-2 focus:ring-emerald-100"
                        >{{ old('notes', $activity->notes) }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-ink-60">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="mt-4 w-full rounded-lg bg-ink px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90">{{ __('ui.activity.save_feeling') }}</button></details><noscript><button type="submit" class="rd-button mt-3">{{ __('ui.activity.save_feeling') }}</button></noscript>
                    </form></section>
@if($matchedDay)<x-plan-block class="mt-4 p-3.5">{{ __('design.planned') }} · {{ $matchedDay['title'] }}, {{ $matchedDay['duration_minutes'] }} min · {{ $matchedDay['intensity'] }}<a href="{{ route('coach.index', ['plan'=>$matchedPlan->id, 'day'=>$matchedDay['date']]) }}#week" class="mt-1 block font-semibold">{{ __('design.linked') }} ›</a></x-plan-block>@endif
@include('activities._sensor-zones')
<section class="rd-panel-section" aria-labelledby="ai-analysis-heading"><p class="text-xs text-plan-text">RideDock Coach</p><h2 id="ai-analysis-heading" class="!text-sm text-plan-text">{{ __('ui.activity.analyze_ride') }}</h2>            <div class="py-3" data-activity-analysis-progress role="status" aria-live="polite" aria-busy="true" hidden>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-medium">{{ __('ui.activity.analysis_pending') }}</h3>
                        <p class="mt-1 text-sm text-ink-50">{{ __('ui.activity.analysis_copy') }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-plan-text">{{ __('ui.common.please_wait') }}</span>
                </div>
                <div class="mt-5 h-[3px] overflow-hidden rounded-full bg-plan-soft" role="progressbar" aria-label="{{ __('ui.activity.analysis_progress') }}" aria-valuetext="{{ __('ui.activity.analysis_pending') }}">
                    <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-plan"></span>
                </div>
            </div>

            @if (! $aiConfigured)
                <div class="py-3">
                    <h3 class="font-medium">{{ __('ui.activity.ai_missing') }}</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-60">{{ __('ui.activity.ai_missing_copy') }}</p>
                    <a href="{{ route('settings.training') }}" class="mt-4 inline-flex text-sm font-medium text-plan-text hover:text-plan-text">{{ __('ui.activity.configure_ai') }}</a>
                </div>
            @elseif (! $latestAiAnalysis)
                <div class="space-y-3 py-3">
                    <div>
                        <h3 class="font-medium">{{ __('ui.activity.no_analysis') }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-60">{{ __('ui.activity.no_analysis_copy') }}</p>
                    </div>
                    <a href="{{ route('settings.training') }}" class="text-sm font-medium text-plan-text hover:text-plan-text">{{ __('ui.activity.check_profile') }}</a>
                </div>
            @elseif (in_array($latestAiAnalysis->status->value, ['pending', 'processing'], true))
                @php
                    $analysisIsProcessing = $latestAiAnalysis->status->value === 'processing';
                    $analysisStatus = $analysisIsProcessing ? __('ui.activity.analysis_pending') : __('ui.activity.waiting');
                @endphp
                <div class="py-3" role="status" aria-live="polite" aria-busy="true">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-medium">{{ $analysisStatus }}</h3>
                            <p class="mt-1 text-sm text-ink-50">
                                {{ $analysisIsProcessing ? __('ui.activity.analysis_copy') : __('ui.activity.waiting_copy') }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-medium text-plan-text">{{ __('ui.activity.auto_refresh') }}</span>
                    </div>
                    <div
                        class="mt-5 h-[3px] overflow-hidden rounded-full bg-plan-soft"
                        role="progressbar"
                        aria-label="{{ __('ui.activity.analysis_progress') }}"
                        aria-valuetext="{{ $analysisStatus }}"
                    >
                        <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-plan"></span>
                    </div>
                </div>
            @elseif ($latestAiAnalysis->status->value === 'failed')
                <div class="py-3">
                    <h3 class="font-medium text-ink-60">{{ __('ui.activity.failed') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-ink-60">{{ __('ui.activity.failed_copy') }}</p>
                </div>
            @else
                @php
                    $analysisOutput = $latestAiAnalysis->output ?? [];
                    $nextSession = $nextPlannedSession ?? (array) data_get($analysisOutput, 'next_session', []);
                    $nextSessionUsesWeeklyPlan = $nextPlannedSession !== null;
                @endphp
                <div class="space-y-4 py-3">
                    <div>
                        <x-panel-label>{{ __('design.summary') }}</x-panel-label><p class="text-[15px] leading-relaxed text-ink">{{ data_get($analysisOutput, 'summary') }}</p>
                        <div class="mt-4">
                            <p class="text-xs font-semibold text-plan-text">{{ __('ui.activity.training_effect') }}</p>
                            <p class="mt-2 text-sm leading-6 text-ink-70">{{ data_get($analysisOutput, 'training_effect') }}</p>
                        </div>

                        @if (data_get($analysisOutput, 'observations', []) !== [])
                            <div class="mt-7 space-y-5">
                                @foreach ((array) data_get($analysisOutput, 'observations', []) as $observation)
                                    <article>
                                        <h3 class="font-medium">{{ data_get($observation, 'title') }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-ink-60">{{ data_get($observation, 'detail') }}</p>
                                        <p class="mt-2 text-xs text-ink-45">{{ __('ui.activity.basis') }} {{ implode(', ', (array) data_get($observation, 'evidence', [])) }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <aside class="rounded-lg bg-canvas p-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-ink-45">{{ $nextSessionUsesWeeklyPlan ? __('ui.activity.next_plan_day') : __('ui.activity.next_session') }}</p>
                            @if ($nextSessionUsesWeeklyPlan)
                                <span class="rounded-full bg-plan-soft px-2 py-0.5 text-xs font-medium text-plan-text">{{ __('ui.activity.from_week_plan') }}</span>
                            @endif
                        </div>
                        @if ($nextSessionUsesWeeklyPlan)
                            <p class="mt-3 text-xs font-medium text-ink-45">{{ \Carbon\CarbonImmutable::parse((string) data_get($nextSession, 'date'))->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}</p>
                        @endif
                        <h3 class="mt-2 text-lg font-semibold">{{ data_get($nextSession, 'title') }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if (data_get($nextSession, 'duration_minutes'))
                                <span class="rounded-full bg-canvas px-2.5 py-1 text-ink-60">{{ data_get($nextSession, 'duration_minutes') }} min</span>
                            @endif
                            <span class="rounded-full bg-canvas px-2.5 py-1 text-ink-60">{{ data_get($nextSession, 'intensity') }}</span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-ink-70">{{ data_get($nextSession, 'details') }}</p>
                        <p class="mt-4 text-sm leading-6 text-ink-50">{{ data_get($nextSession, 'rationale') }}</p>

                        @if ($nextSessionUsesWeeklyPlan)
                            <a href="{{ route('coach.index') }}#weekly-plan" class="mt-5 inline-flex text-sm font-medium text-plan-text hover:text-plan-text">{{ __('ui.activity.open_week_plan') }}</a>
                        @endif

                        @if (data_get($analysisOutput, 'data_gaps', []) !== [])
                            <div class="mt-5 border-t border-line pt-4">
                                <p class="text-xs font-medium text-ink-50">{{ __('ui.activity.data_gaps') }}</p>
                                <ul class="mt-2 space-y-1 text-xs leading-5 text-ink-50">
                                    @foreach ((array) data_get($analysisOutput, 'data_gaps', []) as $gap)
                                        <li>· {{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </aside>
                </div>
                @foreach ($analysisOutput['cautions'] ?? [] as $caution)<p class="mt-3 text-[11px] text-ink-45">{{ $caution }}</p>@endforeach
                <div class="border-t border-line py-4 text-[11px] text-ink-45">{{ __('ui.common.ai_disclaimer', ['time' => $latestAiAnalysis->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}</div>
            @endif
</section></aside></div></x-layout>
