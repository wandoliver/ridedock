<section class="rd-surface p-4" aria-labelledby="load-balance-heading">
    <h2 class="text-sm font-semibold" id="load-balance-heading">{{ __('balance.title') }}</h2>
    <p class="mt-1 text-sm text-ink-50">{{ __('balance.period', ['date' => $loadBalance['window_end']]) }}</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @foreach (['monotony', 'load_balance'] as $metric)
            <div><h3 class="text-sm font-semibold">{{ __('balance.'.$metric) }}</h3>
                <p class="mt-1 text-xl font-semibold">{{ $loadBalance[$metric]['value'] === null ? '–' : \Illuminate\Support\Number::format($loadBalance[$metric]['value'], $metric === 'monotony' ? 2 : 1, locale: app()->getLocale()) }}</p>
                <p class="mt-1 text-sm text-ink-50">{{ $loadBalance[$metric]['reason'] === null ? __('balance.'.$metric.'_hint') : __('balance.'.$loadBalance[$metric]['reason']) }}</p>
                @if ($metric === 'monotony' && $loadBalance['monotony']['strain'] !== null)
                    <p class="mt-2 text-sm">{{ __('balance.strain') }}: {{ \Illuminate\Support\Number::format($loadBalance['monotony']['strain'], 1, locale: app()->getLocale()) }}</p>
                @endif
            </div>
        @endforeach
    </div>
    <details class="mt-4 text-sm"><summary>{{ __('balance.details') }}</summary>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
            <dl class="space-y-1">@foreach ($loadBalance['daily_loads'] as $day)
                <div class="flex justify-between gap-3"><dt>{{ $day['date'] }}</dt><dd>{{ $day['load'] === null ? '–' : \Illuminate\Support\Number::format($day['load'], 1, locale: app()->getLocale()) }}</dd></div>
            @endforeach</dl>
            <div class="space-y-2">@foreach (['seven_days' => 7, 'forty_two_days' => 42] as $key => $days)
                <p>{{ __('balance.coverage', ['days' => $days, 'rated' => $loadBalance[$key]['rated_ride_count'], 'total' => $loadBalance[$key]['ride_count'], 'missing' => $loadBalance[$key]['missing_load_ride_count']]) }}</p>
            @endforeach
                <p class="text-ink-50">{{ __('balance.formula') }}</p>
            </div>
        </div>
    </details>
    <p class="mt-3 text-sm text-ink-50">{{ __('balance.recording_limit') }}</p>
    <p class="mt-2 text-sm text-ink-50">{{ __('balance.freshness_limit') }}</p>
</section>
