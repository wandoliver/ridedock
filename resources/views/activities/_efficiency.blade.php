<section class="rd-surface p-4" aria-label="{{ __('efficiency.title') }}">
    <h2 class="!text-sm font-semibold">{{ __('efficiency.title') }}</h2>
    <p class="mt-1 text-xs text-ink-50">{{ __('efficiency.hint') }}</p>
    @if ($efficiency['available'])
        <p class="mt-3 text-lg font-semibold">{{ \Illuminate\Support\Number::format($efficiency['watts_per_bpm'], 3, locale: app()->getLocale()) }} W/bpm</p>
        <p class="mt-1 text-xs text-ink-50">{{ __('efficiency.window', ['start' => round($efficiency['window_start_seconds'] / 60, 1), 'minutes' => round($efficiency['duration_seconds'] / 60, 1), 'watts' => $efficiency['mean_power_watts'], 'bpm' => $efficiency['mean_heart_rate_bpm']]) }}</p>
        <p class="mt-2 text-xs">{{ $efficiency['change_percent'] === null ? __('efficiency.no_comparison') : __('efficiency.change', ['percent' => $efficiency['change_percent'], 'date' => $efficiency['previous_date']]) }}</p>
    @else
        <p class="mt-3 text-xs text-ink-50">{{ __('efficiency.'.$efficiency['reason']) }}</p>
    @endif
    <form class="mt-3 space-y-2" method="POST" action="{{ route('activities.comparison.update', $activity) }}">
        @csrf @method('PATCH')
        <label class="block text-xs" for="comparison_group">{{ __('efficiency.group') }}</label>
        <input class="block w-full rounded border border-border-input p-2 text-sm" id="comparison_group" name="comparison_group" maxlength="80" value="{{ old('comparison_group', $activity->comparison_group) }}" placeholder="{{ __('efficiency.group_example') }}">
        @error('comparison_group')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
        <p class="text-[11px] text-ink-50">{{ __('efficiency.group_hint') }}</p>
        <button class="rd-button rd-button-secondary text-xs" type="submit">{{ __('efficiency.save') }}</button>
    </form>
    <p class="mt-3 text-[11px] text-ink-50">{{ __('efficiency.limitation') }}</p>
</section>
