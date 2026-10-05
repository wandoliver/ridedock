<section class="rd-form-group">
    <h2>{{ __('zones.title') }}</h2><p class="text-sm leading-relaxed text-ink-50">{{ __('zones.hint') }}</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['power' => 'power_ranges', 'heart_rate' => 'heart_rate_ranges'] as $sensor => $key)
        <div><h3 class="text-sm font-semibold">{{ __('zones.'.$sensor) }}</h3>
            @forelse ($zoneContext[$key] as $range)
            <p class="mt-1 flex justify-between text-sm"><span>{{ $range['label'] }}</span><span>{{ $range['lower'] }}{{ $range['upper'] === null ? '+' : '–'.$range['upper'] }} {{ $sensor === 'power' ? 'W' : 'bpm' }}</span></p>
            @empty<p class="mt-2 text-sm text-ink-50">{{ __('zones.missing') }}</p>@endforelse
        </div>
        @endforeach
    </div>
    <details class="mt-4 text-sm"><summary>{{ __('zones.add') }}</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-3" method="POST" action="{{ route('settings.training.zones.store') }}">@csrf<input type="hidden" name="section" value="thresholds">
            <label class="text-sm">{{ __('zones.effective') }} ({{ config('app.timezone') }})<input class="mt-1 block w-full rounded border border-border-input p-2" type="datetime-local" name="effective_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('effective_at', now()->format('Y-m-d\TH:i')) }}"></label>
            <label class="text-sm">FTP (W)<input class="mt-1 block w-full rounded border border-border-input p-2" type="number" name="ftp_watts" min="50" max="600" value="{{ old('ftp_watts', $profile?->ftp_watts) }}"></label>
            <label class="text-sm">{{ __('ui.settings.max_hr') }} (bpm)<input class="mt-1 block w-full rounded border border-border-input p-2" type="number" name="max_heart_rate" min="100" max="230" value="{{ old('max_heart_rate', $profile?->max_heart_rate) }}"></label>
            <p class="text-sm text-ink-50 sm:col-span-3">{{ __('zones.dated_hint') }}</p><x-button>{{ __('zones.save') }}</x-button>
        </form>
    </details>
    <details class="mt-4 text-sm"><summary>{{ __('zones.history') }}</summary>
        <div class="mt-2 space-y-2">@forelse ($zoneHistory as $snapshot)
            <p>{{ $snapshot->effective_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY HH:mm') }} · FTP {{ $snapshot->ftp_watts ?? '–' }} W · {{ __('ui.settings.max_hr') }} {{ $snapshot->max_heart_rate ?? '–' }} bpm</p>
        @empty<p>{{ __('zones.no_history') }}</p>@endforelse</div>
    </details>
</section>
