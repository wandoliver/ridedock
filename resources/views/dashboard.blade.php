@guest
@include('auth.login')
@else
<x-layout :title="__('ui.common.overview')">
<x-page-header :title="__('ui.common.overview')" :description="__('ux.overview_description')" />
<x-flash />
<main class="rd-content rd-stack">
@if($connectionErrorCount > 0)<x-info-box><a href="{{ route('settings.connections') }}">{{ $connectionErrorCount === 1 ? __('ui.dashboard.one_connection_error') : __('ui.dashboard.many_connection_errors', ['count' => $connectionErrorCount]) }} · {{ __('ui.dashboard.check_connections') }} <x-icon name="chevron-right" /></a></x-info-box>@endif
<section class="rd-dashboard-next"><h2>{{ __('ux.next_session') }}</h2><div class="flex flex-wrap items-center justify-between gap-2 text-sm"><span class="font-semibold text-ink-50">{{ $now->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}</span><a class="font-semibold text-plan-text" href="{{ route('coach.index') }}#week">{{ __('ui.dashboard.open_coach') }} <x-icon name="chevron-right" /></a></div>
@if($todayPlan)
<div class="mt-5 rd-next-session"><p class="font-semibold">{{ $todayPlan['date'] === $now->toDateString() ? __('design.planned_today') : __('design.next_planned') }} · {{ $todayPlan['duration_minutes'] ?? 0 }} min</p><a class="rd-next-title mt-2 block" href="{{ route('coach.index', ['plan'=>$weeklyPlan->id, 'day'=>$todayPlan['date']]) }}#week">{{ $todayPlan['title'] }} <x-icon name="chevron-right" /></a><p class="mt-1 text-sm text-ink-60">{{ $todayPlan['intensity'] }}</p>@foreach($todayRides as $ride)<a class="mt-3 block border-t border-line pt-2 text-sm font-semibold" href="{{ route('activities.show', $ride) }}"><x-icon name="bike" /> {{ $ride->name }} · {{ round($ride->moving_time_s / 60) }} min {{ __('design.ridden') }} <x-icon name="chevron-right" /></a>@endforeach</div>
<x-info-box class="mt-4"><b>{{ $weeklyPlan->output['weekly_goal'] ?? '' }}</b> · {{ round($planRiddenMinutes) }} / {{ $weeklyPlan->output['total_duration_minutes'] ?? 0 }} min {{ __('design.ridden') }}</x-info-box>
@else<x-info-box class="mt-3">{{ __('design.no_week') }} <a class="font-semibold text-plan-text" href="{{ route('coach.index') }}#week">{{ __('ui.dashboard.open_coach') }} <x-icon name="chevron-right" /></a></x-info-box>@endif
</section>
@php $currentWeek = $trainingStatistics['weeks'][5]; @endphp
<section><div class="rd-section-heading"><h2>{{ __('ux.this_week') }}</h2><a class="rd-text-link" href="{{ route('analytics') }}">{{ __('ux.more_details') }} <x-icon name="chevron-right" /></a></div>
<div class="rd-metrics" aria-label="{{ __('ux.this_week') }}">
@foreach([
    [__('ux.week_rides'), $currentWeek['activity_count'], 'activity'],
    [__('ux.week_time'), \Illuminate\Support\Number::format($currentWeek['duration_hours'], 1, locale: app()->getLocale()).' h', 'clock'],
    [__('ux.week_distance'), \Illuminate\Support\Number::format($currentWeek['distance_km'], 1, locale: app()->getLocale()).' km', 'route'],
] as [$label, $value, $icon])
<article><x-icon :name="$icon" /><div><p class="rd-meta">{{ $label }}</p><p class="rd-metric-value">{{ $value }}</p></div></article>
@endforeach</div></section>
<section><div class="mt-6 flex items-center justify-between gap-2"><h2>{{ __('design.recent_activities') }}</h2><a href="{{ route('activities.index', []) }}" class="text-sm font-semibold text-plan-text">{{ __('ui.dashboard.all_activities') }} <x-icon name="chevron-right" /></a></div>
            @forelse ($activities->take(3) as $activity)
                <a href="{{ route('activities.show', ['activity' => $activity->id, 'return_to' => '/']) }}" class="group grid gap-4 border-b border-line px-0 py-[11px] transition last:border-0 hover:bg-plan-soft/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 grid-cols-[minmax(0,1fr)_auto] sm:items-center ">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold transition group-hover:text-plan-text">{{ $activity->name }}</h3>
                            @include('activities._analysis-status', ['status' => $activity->latestAiAnalysis?->status->value])
                        </div>
                        <p class="mt-1 text-sm text-ink-50">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM · HH:mm') }}</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span><strong class="font-medium text-ink">{{ number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-ink-50">km</span></span>
                        <span><strong class="font-medium text-ink">{{ number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-ink-50">hm</span></span>
                        @if ($activity->average_power)
                            <span><strong class="font-medium text-ink">{{ number_format($activity->average_power, 0, ',', '.') }}</strong> <span class="text-ink-50">W</span></span>
                        @endif
                        <x-icon name="chevron-right" />
                    </div>
                </a>
            @empty
                <div class="px-6 py-14 text-center ">
                    <span class="mx-auto grid size-12 place-items-center rounded-lg bg-plan-soft text-plan-text">
                        <x-icon name="download" />
                    </span>
                    <h3 class="mt-4 font-medium">{{ __('ui.dashboard.no_activities') }}</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-50">{{ __('ui.dashboard.no_activities_copy') }}</p>
                </div>
            @endforelse

</section>
</main></x-layout>
@endguest
