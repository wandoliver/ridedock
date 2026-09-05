<x-layout :title="__('ui.activities.title')"><x-topbar><x-slot:context><span class="text-[13px] font-semibold">{{ __('ui.activities.title') }}</span></x-slot:context><x-slot:center>            <x-segment aria-label="{{ __('ui.dashboard.select_period') }}">
                @foreach ($periods as $option)
                    <a href="{{ route('activities.index', ['period' => $option->value]) }}"

                        @if ($period === $option) aria-current="page" @endif>
                        {{ $option->shortLabel($now) }}
                    </a>
                @endforeach
            </x-segment></x-slot:center><x-slot:actions><x-button :href="route('coach.index')" secondary>Coach</x-button><x-button :href="route('settings.connections')" secondary>{{ __('ui.common.connections') }}</x-button></x-slot:actions></x-topbar>    <main class="mx-auto max-w-[1440px] px-5 py-6 sm:px-7">
        <section class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-plan-text">{{ __('ui.activities.archive') }}</p>
                <h1 class="mt-1 text-[22px] font-bold">{{ __('ui.activities.title') }}</h1>
                <p class="mt-3 text-base text-ink-60">{{ $activities->total() === 1 ? __('ui.activities.count_one', ['count' => 1]) : __('ui.activities.count_many', ['count' => \Illuminate\Support\Number::format($activities->total(), locale: app()->getLocale())]) }} · {{ $periodLabel }}</p>
            </div>

        </section>

        <section class="mt-5 overflow-hidden rounded-lg border border-line bg-white  " aria-label="{{ __('ui.activities.list') }}">
            @forelse ($activities as $activity)
                <a href="{{ route('activities.show', $activity) }}" class="group grid gap-4 border-b border-line px-4 py-3.5 transition last:border-0 hover:bg-plan-soft/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium transition group-hover:text-plan-text">{{ $activity->name }}</h2>
                            <span class="rounded-full bg-canvas px-2 py-0.5 text-[0.7rem] font-medium text-ink-50">{{ str($activity->type)->replace('_', ' ')->headline() }}</span>
                            @include('activities._analysis-status', ['status' => $activity->latestAiAnalysis?->status->value])
                        </div>
                        <p class="mt-1 text-sm text-ink-50">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM YYYY · HH:mm') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <span><strong class="font-medium text-ink">{{ $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-ink-50">km</span></span>
                        <span><strong class="font-medium text-ink">{{ $activity->elevation_gain_m === null ? '–' : number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-ink-50">hm</span></span>
                        <span><strong class="font-medium text-ink">{{ $activity->moving_time_s === null ? '–' : sprintf('%d:%02d', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60)) }}</strong> <span class="text-ink-50">h</span></span>
                        <svg viewBox="0 0 20 20" class="hidden size-4 text-ink-45 transition group-hover:translate-x-0.5 group-hover:text-plan-text sm:block" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                    </div>
                </a>
            @empty
                <div class="px-6 py-16 text-center sm:px-8">
                    <span class="mx-auto grid size-12 place-items-center rounded-lg bg-plan-soft text-plan-text">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
                    </span>
                    <h2 class="mt-4 font-medium">{{ __('ui.activities.none') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-ink-50">{{ __('ui.activities.none_copy') }}</p>
                </div>
            @endforelse
        </section>

        @if ($activities->hasPages())
            <nav class="mt-6 flex flex-col gap-3 rounded-lg border border-line bg-white px-4 py-3  sm:flex-row sm:items-center sm:justify-between" aria-label="{{ __('ui.activities.pagination') }}">
                <p class="text-sm text-ink-50">{{ __('ui.common.range_of', ['first' => $activities->firstItem(), 'last' => $activities->lastItem(), 'total' => \Illuminate\Support\Number::format($activities->total(), locale: app()->getLocale())]) }}</p>
                <div class="flex items-center gap-2">
                    @if ($activities->onFirstPage())
                        <span class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300">{{ __('ui.common.back') }}</span>
                    @else
                        <a href="{{ $activities->previousPageUrl() }}" class="rounded-lg px-3 py-2 text-sm font-medium text-ink-60 transition hover:bg-canvas hover:text-ink">{{ __('ui.common.back') }}</a>
                    @endif
                    <span class="px-2 text-sm text-ink-50">{{ __('ui.common.page_of', ['current' => $activities->currentPage(), 'last' => $activities->lastPage()]) }}</span>
                    @if ($activities->hasMorePages())
                        <a href="{{ $activities->nextPageUrl() }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white transition hover:opacity-90">{{ __('ui.common.next') }}</a>
                    @else
                        <span class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300">{{ __('ui.common.next') }}</span>
                    @endif
                </div>
            </nav>
        @endif

        <footer class="mt-8 flex flex-col gap-2 border-t border-line pt-6 text-xs text-ink-50 sm:flex-row sm:items-center sm:justify-between">
            <p>{{ __('ui.activities.footer_left') }}</p>
            <p>{{ __('ui.activities.footer_right') }}</p>
        </footer>
    </main>
</x-layout>
