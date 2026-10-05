<section class="rd-surface p-4" aria-label="{{ __('zones.sensor_title') }}">
    <h2 class="text-sm font-semibold">{{ __('zones.sensor_title') }}</h2><p class="mt-1 text-sm text-ink-50">{{ __('zones.sensor_hint') }}</p>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
    @forelse ($sensorZones as $sensor => $distribution)
        <div><h3 class="text-sm font-semibold">{{ __('zones.'.$sensor) }}</h3>
        @if ($distribution['available'])
            <dl class="mt-2 space-y-1 text-sm">@foreach ($distribution['seconds'] as $zone => $seconds)
                <div class="flex justify-between gap-3"><dt>{{ $zone }}</dt><dd>{{ \Illuminate\Support\Number::format($seconds / 60, 1, locale: app()->getLocale()) }} min · {{ \Illuminate\Support\Number::format($seconds / max(1, $distribution['covered_seconds']) * 100, 0, locale: app()->getLocale()) }}%</dd></div>
            @endforeach</dl>
        @else<p class="mt-2 text-sm text-ink-50">{{ __('zones.'.$distribution['reason']) }}</p>@endif
        <p class="mt-2 text-sm text-ink-50">{{ __('zones.sensor_coverage', ['percent' => $distribution['coverage_percent'] ?? '–', 'minutes' => round($distribution['unknown_seconds'] / 60, 1)]) }}</p>
        </div>
    @empty<p class="text-sm text-ink-50">{{ __('zones.samples_missing') }}</p>@endforelse
    </div>
</section>
