<section class="rd-surface p-4" aria-label="{{ __('efficiency.trends') }}">
    <h2 class="text-sm font-semibold">{{ __('efficiency.trends') }}</h2>
    <p class="mt-1 text-sm text-ink-50">{{ __('efficiency.trends_hint') }}</p>
    @if ($efficiencyTrends !== [])
        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr class="text-ink-50"><th scope="col" class="py-2 pr-3">{{ __('efficiency.group') }}</th><th scope="col" class="pr-3">{{ __('efficiency.date') }}</th><th scope="col" class="pr-3">W/bpm</th><th>{{ __('efficiency.previous') }}</th></tr></thead>
            <tbody>@foreach ($efficiencyTrends as $row)
                <tr class="border-t border-line"><td class="py-2 pr-3 break-words">{{ $row['group'] }}</td><td class="pr-3"><a class="text-accent-400 underline" href="{{ route('activities.show', $row['activity_id']) }}">{{ $row['date'] }}</a></td><td class="pr-3">{{ \Illuminate\Support\Number::format($row['watts_per_bpm'], 3, locale: app()->getLocale()) }}</td><td>{{ $row['change_percent'] === null ? '–' : $row['change_percent'].'%' }}@if ($row['previous_date']) <span class="text-ink-50">({{ $row['previous_date'] }})</span>@endif</td></tr>
            @endforeach</tbody>
        </table></div>
    @else<p class="mt-3 text-sm text-ink-50">{{ __('efficiency.empty') }}</p>@endif
    <p class="mt-3 text-sm text-ink-50">{{ __('efficiency.limitation') }}</p>
</section>
