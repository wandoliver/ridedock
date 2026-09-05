@guest
@include('auth.login')
@else
<x-layout title="RideDock">
<x-topbar>
<x-slot:context><nav class="flex flex-wrap items-center gap-4 text-[13px]"><a href="{{ route('dashboard') }}" class="font-semibold" aria-current="page">{{ __('ui.common.overview') }}</a><a href="{{ route('coach.index') }}" class="text-ink-50">Coach</a><a href="{{ route('settings.connections') }}" class="text-ink-50">{{ __('ui.common.settings') }}</a></nav></x-slot:context>
<x-slot:center><x-segment aria-label="{{ __('ui.dashboard.select_period') }}">@foreach ($periods as $option)<a href="{{ route('dashboard', ['period' => $option->value]) }}" @if($period === $option) aria-current="page" @endif>{{ $option->shortLabel($now) }}</a>@endforeach</x-segment></x-slot:center>
<x-slot:actions><span class="hidden text-[13px] text-ink-50 xl:block">{{ auth()->user()->name }}</span><x-button :href="route('settings.connections')">{{ __('ui.common.connections') }}</x-button><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-xs text-ink-50">{{ __('ui.dashboard.sign_out') }}</button></form></x-slot:actions>
</x-topbar>
<x-flash />
<div class="rd-shell"><main class="rd-workspace">
@if($connectionErrorCount > 0)<x-info-box><a href="{{ route('settings.connections') }}">{{ $connectionErrorCount === 1 ? __('ui.dashboard.one_connection_error') : __('ui.dashboard.many_connection_errors', ['count' => $connectionErrorCount]) }} · {{ __('ui.dashboard.check_connections') }} ›</a></x-info-box>@endif
<h1 class="sr-only">{{ $periodLabel }}</h1>
<section class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="{{ __('ui.dashboard.metrics_heatmap') }}">
@foreach ([
['label' => __('ui.dashboard.activities'), 'value' => $activityCount, 'unit' => '', 'detail' => __('design.recent_count', ['count' => $recentCount])],
['label' => __('ui.dashboard.distance'), 'value' => \Illuminate\Support\Number::format(round($distanceKilometres), 0, locale: app()->getLocale()), 'unit'=>'km', 'detail'=> $activityCount ? __('design.per_ride',['distance'=>\Illuminate\Support\Number::format($distanceKilometres/$activityCount,1,locale:app()->getLocale())]) : ''],
['label' => __('ui.dashboard.elevation'), 'value' => \Illuminate\Support\Number::format($elevationMetres, 0, locale: app()->getLocale()), 'unit'=>'m', 'detail'=>''],
['label' => __('ui.dashboard.saddle_time'), 'value' => \Illuminate\Support\Number::format($movingHours, 1, locale: app()->getLocale()), 'unit'=>'h', 'detail'=>''],
] as $metric)
<article class="rd-surface px-4 py-3.5"><p class="text-xs font-semibold text-ink-50">{{ $metric['label'] }}</p><p aria-label="{{ $metric['value'] }} {{ $metric['unit'] }}" class="mt-1 text-[28px] font-bold tracking-[-.02em]">{{ $metric['value'] }} <span class="text-sm font-medium text-ink-50">{{ $metric['unit'] }}</span></p>@if($metric['detail'])<p class="text-xs text-ink-50">{{ $metric['detail'] }}</p>@endif</article>
@endforeach</section>
<section class="rd-surface overflow-hidden">
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3.5 py-2.5 text-xs"><div class="flex flex-wrap gap-3"><h2 class="font-bold">Heatmap</h2><span class="text-ink-50">{{ count($heatmapRoutes) === 1 ? __('ui.dashboard.one_route') : __('ui.dashboard.many_routes', ['count'=>count($heatmapRoutes)]) }} · {{ number_format($privacyTrimMetres,0,',','.') }} m Privacy-Trim</span></div>
<button type="button" class="rounded-md bg-canvas px-2 py-1 font-semibold" data-heatmap-expand data-expand-label="{{ __('ui.dashboard.expand') }}" data-collapse-label="{{ __('ui.dashboard.collapse') }}" aria-pressed="false">⤢ <span data-heatmap-expand-label>{{ __('ui.dashboard.expand') }}</span></button></div>
<div class="relative min-h-[380px]" data-heatmap-frame><div id="ride-heatmap" class="absolute inset-0" aria-label="{{ __('ui.dashboard.interactive_heatmap') }}"></div></div>
<script type="application/json" id="ride-heatmap-data">{!! json_encode($heatmapRoutes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</section>
<section class="rd-surface px-3.5 py-3"><div class="flex justify-between gap-3 text-xs"><h2 class="font-bold">{{ __('design.weekly_volume') }} · {{ $periodLabel }}</h2><span class="text-ink-50">{{ __('design.hours_week') }}</span></div>
@php
    $volumeDates = $weeklyVolume->keys()->merge($plannedVolume->keys())->unique()->sort()->values();
    $volumeMax = max(.1, $weeklyVolume->max() ?? 0, $plannedVolume->max() ?? 0);
@endphp
@if($volumeDates->isNotEmpty())
<div class="mt-3 flex h-10 items-end gap-[3px]" role="img" aria-label="{{ __('design.weekly_volume') }}">
@foreach($volumeDates as $date)<div class="relative h-full min-w-0 flex-1" title="{{ $date }} · {{ round($weeklyVolume->get($date, 0),1) }} h {{ __('design.ridden') }} · {{ round($plannedVolume->get($date,0),1) }} h {{ __('design.planned') }}">
@if($plannedVolume->has($date))<div class="absolute inset-x-0 bottom-0 rounded-sm border-[1.5px] border-dashed border-plan-dash" style="height: {{ $plannedVolume->get($date)/$volumeMax*100 }}%"></div>@endif
<div class="absolute inset-x-0 bottom-0 rounded-sm {{ $date === $now->startOfWeek()->toDateString() ? 'bg-plan' : 'bg-plan-bar' }}" style="height: {{ $weeklyVolume->get($date,0)/$volumeMax*100 }}%"></div>
<span class="sr-only">{{ $date }}: {{ round($weeklyVolume->get($date,0),1) }} h {{ __('design.ridden') }}, {{ round($plannedVolume->get($date,0),1) }} h {{ __('design.planned') }}</span></div>@endforeach</div>
<div class="mt-2 flex justify-between text-[11px] text-ink-45"><span>{{ $volumeDates->first() }}</span><span>{{ $volumeDates->last() }} @if($plannedVolume->isNotEmpty()) · ▱ {{ __('design.planned') }}@endif</span></div>
@else<p class="py-6 text-sm text-ink-50">{{ __('ui.dashboard.no_activities') }}</p>@endif
</section>
</main><aside class="rd-panel">
<div class="flex flex-wrap items-center justify-between gap-2 text-xs"><span class="font-semibold text-ink-50">{{ $now->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}</span><a class="font-semibold text-plan-text" href="{{ route('coach.index') }}#week">{{ __('ui.dashboard.open_coach') }} ›</a></div>
@if($todayPlan)
<x-plan-block class="mt-3 p-3.5"><p class="font-semibold">{{ $todayPlan['date'] === $now->toDateString() ? __('design.planned_today') : __('design.next_planned') }} · {{ $todayPlan['duration_minutes'] ?? 0 }} min</p><a class="mt-1 block text-[17px] font-bold text-ink" href="{{ route('coach.index', ['plan'=>$weeklyPlan->id, 'day'=>$todayPlan['date']]) }}#week">{{ $todayPlan['title'] }} ›</a><p class="mt-1 text-[13px] text-ink-60">{{ $todayPlan['intensity'] }}</p>@foreach($todayRides as $ride)<a class="mt-3 block border-t border-line pt-2 text-xs font-semibold" href="{{ route('activities.show', $ride) }}">● {{ $ride->name }} · {{ round($ride->moving_time_s / 60) }} min {{ __('design.ridden') }} ›</a>@endforeach</x-plan-block>
<x-info-box class="mt-2"><b>{{ $weeklyPlan->output['weekly_goal'] ?? '' }}</b> · {{ round($planRiddenMinutes) }} / {{ $weeklyPlan->output['total_duration_minutes'] ?? 0 }} min {{ __('design.ridden') }}</x-info-box>
@else<x-info-box class="mt-3">{{ __('design.no_week') }} <a class="font-semibold text-plan-text" href="{{ route('coach.index') }}#week">{{ __('ui.dashboard.open_coach') }} ›</a></x-info-box>@endif
<div class="mt-6 flex items-center justify-between gap-2"><h2 class="!text-xs font-semibold text-ink-50">{{ __('design.recent_activities') }}</h2><a href="{{ route('activities.index', ['period'=>$period->value]) }}" class="text-xs font-semibold text-plan-text">{{ __('ui.dashboard.all_activities') }} ›</a></div>
            @forelse ($activities as $activity)
                <a href="{{ route('activities.show', $activity) }}" class="group grid gap-4 border-b border-line px-0 py-[11px] transition last:border-0 hover:bg-plan-soft/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 grid-cols-[1fr_auto] sm:items-center ">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold transition group-hover:text-plan-text">{{ $activity->name }}</p>
                            @include('activities._analysis-status', ['status' => $activity->latestAiAnalysis?->status->value])
                        </div>
                        <p class="mt-1 text-xs text-ink-50">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM · HH:mm') }}</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span><strong class="font-medium text-ink">{{ number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-ink-50">km</span></span>
                        <span><strong class="font-medium text-ink">{{ number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-ink-50">hm</span></span>
                        @if ($activity->average_power)
                            <span><strong class="font-medium text-ink">{{ number_format($activity->average_power, 0, ',', '.') }}</strong> <span class="text-ink-50">W</span></span>
                        @endif
                        <svg viewBox="0 0 20 20" class="hidden size-4 text-ink-45 transition group-hover:translate-x-0.5 group-hover:text-plan-text sm:block" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                    </div>
                </a>
            @empty
                <div class="px-6 py-14 text-center ">
                    <span class="mx-auto grid size-12 place-items-center rounded-lg bg-plan-soft text-plan-text">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                    </span>
                    <h3 class="mt-4 font-medium">{{ __('ui.dashboard.no_activities') }}</h3>
                    <p class="mx-auto mt-2 max-w-md text-xs leading-6 text-ink-50">{{ __('ui.dashboard.no_activities_copy') }}</p>
                </div>
            @endforelse

<footer class="mt-6 border-t border-line pt-4 text-[11px] leading-relaxed text-ink-45"><p>{{ __('ui.dashboard.footer_left') }}</p><p>{{ __('ui.dashboard.footer_right') }}</p></footer>
</aside></div></x-layout>
@endguest
