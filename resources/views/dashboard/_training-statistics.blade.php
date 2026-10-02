@php
    $stats = $trainingStatistics['summary'];
    $statWeeks = $trainingStatistics['weeks'];
    $comparison = $trainingStatistics['comparison'];
    $currentStats = $statWeeks[5];
    $statNumber = fn ($value, $digits = 0) => \Illuminate\Support\Number::format($value, $digits, locale: app()->getLocale());
    $statDate = fn ($date) => \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->isoFormat('D. MMM');
    $maxLoad = max(1, ...array_map(fn ($week) => $week['session_rpe_load'] ?? 0, $statWeeks));
    $effortColors = ['easy' => 'bg-plan', 'moderate' => 'bg-amber-400', 'hard' => 'bg-ink', 'unrated' => 'bg-ink/20'];
@endphp
<section class="rd-surface px-4 py-4" aria-labelledby="training-statistics-heading">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="training-statistics-heading" class="!text-sm font-bold">{{ __('training.title') }}</h2>
        <span class="text-xs text-ink-50">{{ __('training.six_weeks') }} · {{ $statDate($trainingStatistics['window_start']) }} – {{ $statDate($trainingStatistics['window_end']) }}</span>
    </div>
    <p class="mt-1 text-xs text-ink-50">{{ __('training.period_note') }}</p>
    @if ($stats['activity_count'] === 0)
        <p class="py-6 text-sm text-ink-50">{{ __('training.empty') }}</p>
    @else
    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div><p class="text-xs text-ink-50">{{ __('training.current_week') }}</p><p class="mt-1 text-xl font-semibold">{{ $statNumber($currentStats['duration_hours'], 1) }} h</p><p class="text-[11px] text-ink-50">{{ __('training.partial') }}</p></div>
        <div><p class="text-xs text-ink-50">{{ __('training.rated_load') }}</p><p class="mt-1 text-xl font-semibold">{{ $currentStats['session_rpe_load'] === null ? '–' : $statNumber($currentStats['session_rpe_load']) }}</p><p class="text-[11px] text-ink-50">{{ __('training.load_unit') }} · {{ __('training.current_week') }}</p></div>
        @foreach (['volume_change_percent' => 'volume_change', 'load_change_percent' => 'load_change'] as $key => $label)
        <div><p class="text-xs text-ink-50">{{ __('training.'.$label) }}</p><p class="mt-1 text-xl font-semibold">{{ $comparison[$key] === null ? '–' : ($comparison[$key] > 0 ? '+' : '').$statNumber($comparison[$key], 1).'%' }}</p><p class="text-[11px] text-ink-50">{{ __('training.last_complete_week') }}</p></div>
        @endforeach
    </div>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <div>
            <h3 class="text-xs font-semibold">{{ __('training.weekly_load') }}</h3>
            <div class="mt-3 grid grid-cols-6 gap-2" aria-label="{{ __('training.weekly_load') }}">
                @foreach ($statWeeks as $week)
                <div class="min-w-0 text-center" title="{{ $statDate($week['week_start']) }} · {{ $week['session_rpe_load'] === null ? __('training.unknown') : $statNumber($week['session_rpe_load']).' '.__('training.load_unit') }}">
                    <div class="relative h-24 rounded bg-canvas" aria-hidden="true">
                        @if ($week['session_rpe_load'] !== null)
                        <span class="absolute inset-x-0 bottom-0 rounded {{ $week['partial'] ? 'bg-plan' : 'bg-plan-bar' }}" style="height: {{ $week['session_rpe_load'] / $maxLoad * 100 }}%"></span>
                        @else<span class="absolute inset-0 grid place-items-center text-xs text-ink-50">?</span>@endif
                    </div>
                    <p class="mt-1 text-[11px] text-ink-50">{{ $statDate($week['week_start']) }}</p>
                    <p class="text-[11px] font-semibold">{{ $week['session_rpe_load'] === null ? '–' : $statNumber($week['session_rpe_load']) }}{{ $week['load_complete'] ? '' : '*' }}</p>
                </div>
                @endforeach
            </div>
            <p class="mt-2 text-[11px] text-ink-50">{{ __('training.partial_load') }}</p>
        </div>
        <div>
            <h3 class="text-xs font-semibold">{{ __('training.effort_title') }}</h3>
            <p class="mt-1 text-[11px] text-ink-50">{{ __('training.effort_basis') }}</p>
            <div class="mt-3 flex h-3 overflow-hidden rounded bg-canvas" aria-hidden="true">
                @foreach ($stats['effort_minutes'] as $bucket => $minutes)
                @if ($minutes > 0)<span class="{{ $effortColors[$bucket] }}" style="width: {{ $minutes / max(.000001, $stats['duration_minutes']) * 100 }}%"></span>@endif
                @endforeach
            </div>
            <dl class="mt-3 space-y-2 text-xs">
                @foreach ($stats['effort_minutes'] as $bucket => $minutes)
                <div class="flex justify-between gap-2"><dt><span class="mr-1 inline-block size-2 rounded {{ $effortColors[$bucket] }}" aria-hidden="true"></span>{{ __('training.'.$bucket) }}</dt><dd>{{ $statNumber($minutes) }} min · {{ $stats['duration_minutes'] > 0 ? $statNumber($minutes / $stats['duration_minutes'] * 100) : '–' }}%</dd></div>
                @endforeach
            </dl>
            <p class="mt-3 text-[11px] text-ink-50">{{ __('training.coverage', ['rated' => $stats['rated_activity_count'], 'total' => $stats['activity_count'], 'percent' => $stats['rpe_coverage_percent'] === null ? '–' : $statNumber($stats['rpe_coverage_percent'])]) }}</p>
            @if (! $stats['load_complete'])<a class="mt-2 inline-block text-xs font-semibold text-plan-text" href="{{ route('activities.index') }}">{{ __('training.rate_rides') }} ›</a>@endif
        </div>
    </div>
    <details class="mt-4 border-t border-line pt-3 text-xs">
        <summary class="cursor-pointer font-semibold text-ink-60">{{ __('training.week_details') }}</summary>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-xs">
                <caption class="sr-only">{{ __('training.six_weeks') }}</caption>
                <thead><tr class="text-ink-50">@foreach (['week', 'rides', 'hours', 'distance', 'load', 'coverage_label'] as $column)<th scope="col" class="whitespace-nowrap px-2 py-2 font-medium">{{ __('training.'.$column) }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($statWeeks as $week)<tr class="border-t border-line">
                    <th scope="row" class="whitespace-nowrap px-2 py-2 font-medium">{{ $statDate($week['week_start']) }}{{ $week['partial'] ? ' · '.__('training.partial') : '' }}</th>
                    <td class="px-2 py-2">{{ $week['activity_count'] }}</td><td class="px-2 py-2">{{ $statNumber($week['duration_hours'], 1) }}</td><td class="px-2 py-2">{{ $statNumber($week['distance_km'], 1) }} km</td>
                    <td class="px-2 py-2">{{ $week['session_rpe_load'] === null ? '–' : $statNumber($week['session_rpe_load']) }}{{ $week['load_complete'] ? '' : '*' }}</td><td class="px-2 py-2">{{ $week['rpe_coverage_percent'] === null ? '–' : $statNumber($week['rpe_coverage_percent']).'%' }}</td>
                </tr>@endforeach</tbody>
            </table>
        </div>
    </details>
    @endif
    <details class="mt-3 text-xs text-ink-50">
        <summary class="cursor-pointer">{{ __('training.method') }}</summary>
        <p class="mt-2 leading-relaxed">{{ __('training.method_load') }}</p>
        <p class="mt-2 leading-relaxed">{{ __('training.method_effort') }}</p>
        <p class="mt-2 leading-relaxed">{{ __('training.method_trend', ['week' => $statDate($comparison['week_start']), 'from' => $statDate($comparison['baseline_start']), 'to' => $statDate($comparison['baseline_end'])]) }}</p>
        @if ($stats['missing_duration_count'] > 0)<p class="mt-2">{{ __('training.missing_duration', ['count' => $stats['missing_duration_count']]) }}</p>@endif
    </details>
</section>
