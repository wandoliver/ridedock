<x-layout :title="__('ux.analytics')">
<x-page-header :title="__('ux.analytics')" :description="__('ux.analysis_description')">
<x-slot:navigation><x-tabs :items="['volume' => __('ux.volume'), 'sensors' => __('ux.sensors'), 'efficiency' => __('ux.efficiency'), 'routes' => __('ux.routes')]" :label="__('ux.analytics')" /></x-slot:navigation>
</x-page-header><x-flash />
<main class="rd-content">
<section id="volume" data-page-panel="volume" class="rd-stack">@include('dashboard._training-statistics') @include('dashboard._load-balance')</section>
<section id="sensors" data-page-panel="sensors">@include('activities._sensor-zones')</section>
<section id="efficiency" data-page-panel="efficiency">@include('dashboard._efficiency')</section>
<section id="routes" data-page-panel="routes" class="rd-stack"><div class="rd-section-heading"><h2>{{ $periodLabel }}</h2><x-segment aria-label="{{ __('ui.dashboard.select_period') }}">@foreach($periods as $option)<a href="{{ route('analytics', ['period' => $option->value]) }}#routes" @if($period === $option) aria-current="page" @endif>{{ $option->shortLabel($now) }}</a>@endforeach</x-segment></div><section class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="{{ __('ui.dashboard.metrics_heatmap') }}">
@foreach ([
['label' => __('ui.dashboard.activities'), 'value' => $activityCount, 'unit' => '', 'detail' => __('design.recent_count', ['count' => $recentCount])],
['label' => __('ui.dashboard.distance'), 'value' => \Illuminate\Support\Number::format(round($distanceKilometres), 0, locale: app()->getLocale()), 'unit'=>'km', 'detail'=> $activityCount ? __('design.per_ride',['distance'=>\Illuminate\Support\Number::format($distanceKilometres/$activityCount,1,locale:app()->getLocale())]) : ''],
['label' => __('ui.dashboard.elevation'), 'value' => \Illuminate\Support\Number::format($elevationMetres, 0, locale: app()->getLocale()), 'unit'=>'m', 'detail'=>''],
['label' => __('ui.dashboard.saddle_time'), 'value' => \Illuminate\Support\Number::format($movingHours, 1, locale: app()->getLocale()), 'unit'=>'h', 'detail'=>''],
] as $metric)
<article class="rd-surface px-4 py-3.5"><p class="text-sm font-semibold text-ink-50">{{ $metric['label'] }}</p><p aria-label="{{ $metric['value'] }} {{ $metric['unit'] }}" class="mt-1 text-[28px] font-bold tracking-[-.02em]">{{ $metric['value'] }} <span class="text-sm font-medium text-ink-50">{{ $metric['unit'] }}</span></p>@if($metric['detail'])<p class="text-sm text-ink-50">{{ $metric['detail'] }}</p>@endif</article>
@endforeach</section>
<section class="rd-surface overflow-hidden">
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-3.5 py-2.5 text-sm"><div class="flex flex-wrap gap-3"><h2 class="font-bold">Heatmap</h2><span class="text-ink-50">{{ count($heatmapRoutes) === 1 ? __('ui.dashboard.one_route') : __('ui.dashboard.many_routes', ['count'=>count($heatmapRoutes)]) }} · {{ number_format($privacyTrimMetres,0,',','.') }} m Privacy-Trim</span></div>
<button type="button" class="rd-button rd-button-secondary" data-heatmap-expand data-expand-label="{{ __('ui.dashboard.expand') }}" data-collapse-label="{{ __('ui.dashboard.collapse') }}" aria-pressed="false"><x-icon name="arrows-maximize" /><span data-heatmap-expand-label>{{ __('ui.dashboard.expand') }}</span></button></div>
<div class="relative min-h-[380px]" data-heatmap-frame><div id="ride-heatmap" class="absolute inset-0" aria-label="{{ __('ui.dashboard.interactive_heatmap') }}"></div></div>
<script type="application/json" id="ride-heatmap-data">{!! json_encode($heatmapRoutes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</section>
<section class="rd-surface px-3.5 py-3"><div class="flex justify-between gap-3 text-sm"><h2 class="font-bold">{{ __('design.weekly_volume') }} · {{ $periodLabel }}</h2><span class="text-ink-50">{{ __('design.hours_week') }}</span></div>
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
<div class="mt-2 flex justify-between text-sm text-ink-45"><span>{{ $volumeDates->first() }}</span><span>{{ $volumeDates->last() }} @if($plannedVolume->isNotEmpty()) · ▱ {{ __('design.planned') }}@endif</span></div>
@else<p class="py-6 text-sm text-ink-50">{{ __('ui.dashboard.no_activities') }}</p>@endif
</section>
</section>
</main></x-layout>
