<x-layout :title="__('privacy.title')">
<x-page-header :title="__('privacy.title')" :description="__('privacy.description')" :back="route('dashboard')" :parent="__('ui.common.settings')"><x-slot:navigation><x-settings-nav /></x-slot:navigation></x-page-header>
<x-flash />
<main class="rd-content space-y-8">
    <section class="rounded-lg border border-line bg-white p-5">
        <h2>{{ __('privacy.retention') }}</h2>
        <p class="mt-2 text-sm text-ink-60">{{ __('privacy.retention_hint') }}</p>
        <form method="POST" action="{{ route('settings.privacy.update') }}" class="mt-5 space-y-4">
            @csrf @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['activity_retention_days' => 'activities', 'source_retention_days' => 'sources'] as $field => $label)
                <label class="text-sm font-semibold">{{ __('privacy.'.$label) }}
                    <select name="{{ $field }}" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3 py-2">
                        <option value="" @selected(old($field, $user->$field) === null || old($field, $user->$field) === '')>{{ __('privacy.forever') }}</option>
                        @foreach([30,90,180,365,730] as $days)<option value="{{ $days }}" @selected((string) old($field, $user->$field) === (string) $days)>{{ __('privacy.days', ['days' => $days]) }}</option>@endforeach
                    </select>
                </label>
                @endforeach
            </div>
            <p class="text-sm text-ink-60">{{ __('privacy.source_hint') }}</p>
            <x-button>{{ __('privacy.save') }}</x-button>
        </form>
    </section>
    <section class="rounded-lg border border-line bg-white p-5">
        <h2>{{ __('privacy.cleanup') }}</h2>
        <p class="mt-2 text-sm text-ink-60">{{ __('privacy.preview', $preview) }}</p>
        @if($preview['activities'] || $preview['sources'])
        <x-info-box class="mt-4">{{ __('privacy.cleanup_warning') }}</x-info-box>
        <form method="POST" action="{{ route('settings.privacy.cleanup') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="cleanup_snapshot" value="{{ $cleanupSnapshot }}">
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm_cleanup" value="1" required class="mt-1">{{ __('privacy.confirm') }}</label>
            <x-button secondary>{{ __('privacy.purge') }}</x-button>
        </form>
        @else<p class="mt-4 text-sm text-plan-text">{{ __('privacy.nothing') }}</p>@endif
    </section>
    <section class="rounded-lg border border-line bg-white p-5">
        <h2>{{ __('privacy.zones') }}</h2>
        <p class="mt-2 text-sm text-ink-60">{{ __('privacy.zone_hint') }}</p>
        <ul class="mt-4 divide-y divide-line">
            @forelse($zones as $zone)
            <li class="flex items-center justify-between gap-3 py-3"><span class="text-sm"><strong>{{ $zone->name }}</strong> · {{ $zone->radius_m }} m</span>
                <form method="POST" action="{{ route('settings.privacy.zones.destroy', $zone->id) }}">@csrf @method('DELETE')<x-button secondary>{{ __('privacy.delete_zone') }}</x-button></form>
            </li>
            @empty<li class="py-3 text-sm text-ink-60">{{ __('privacy.no_zones') }}</li>@endforelse
        </ul>
        <p class="mt-5 text-sm text-ink-60">{{ __('privacy.map_hint') }}</p>
        <div id="privacy-zone-map" class="mt-3 h-[360px] rounded-lg border border-line" aria-label="{{ __('privacy.map_label') }}"></div>
        <script type="application/json" id="privacy-zone-map-data">{!! json_encode(['zones' => $zones->map(fn ($zone) => $zone->only(['latitude','longitude','radius_m'])), 'routes' => $mapRoutes], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <form method="POST" action="{{ route('settings.privacy.zones.store') }}" class="mt-5 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('privacy.name') }}<input name="name" value="{{ old('name') }}" required maxlength="80" placeholder="{{ __('privacy.name_placeholder') }}" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2"></label>
                <label class="text-sm font-semibold">{{ __('privacy.radius') }}<input id="privacy-zone-radius" type="number" name="radius_m" value="{{ old('radius_m', 400) }}" min="100" max="5000" step="1" required class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2"></label>
            </div>
            <details><summary class="text-sm text-ink-60">{{ __('privacy.coordinates') }}</summary><div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach(['latitude', 'longitude'] as $coordinate)<label class="text-sm">{{ __('privacy.'.$coordinate) }}<input id="privacy-zone-{{ $coordinate }}" type="number" step="any" name="{{ $coordinate }}" min="{{ $coordinate === 'latitude' ? -90 : -180 }}" max="{{ $coordinate === 'latitude' ? 90 : 180 }}" value="{{ old($coordinate) }}" required class="mt-1 block w-full rounded-lg border border-border-input px-3 py-2"></label>@endforeach
            </div></details>
            <x-button>{{ __('privacy.add_zone') }}</x-button>
        </form>
    </section>
    <p class="text-sm text-ink-60">{{ __('privacy.scope_hint') }}</p>
</main>
</x-layout>
