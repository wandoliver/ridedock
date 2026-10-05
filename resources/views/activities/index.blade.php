<x-layout :title="__('ui.activities.title')">
<x-page-header :title="__('ui.activities.title')" :description="($activities->total() === 1 ? __('ui.activities.count_one', ['count' => 1]) : __('ui.activities.count_many', ['count' => $activities->total()])).' · '.$periodLabel">
<x-slot:navigation><x-segment aria-label="{{ __('ui.dashboard.select_period') }}">@foreach($periods as $option)<a href="{{ route('activities.index', ['period' => $option->value]) }}" @if($period === $option) aria-current="page" @endif>{{ $option->shortLabel($now) }}</a>@endforeach</x-segment></x-slot:navigation>
</x-page-header><main class="rd-content"><section class="mt-5 overflow-hidden rounded-lg border border-line bg-white  " aria-label="{{ __('ui.activities.list') }}">
            @forelse ($activities as $activity)
                <a href="{{ route('activities.show', ['activity' => $activity->id, 'return_to' => request()->getRequestUri()]) }}" class="group grid gap-4 border-b border-line px-4 py-3.5 transition last:border-0 hover:bg-plan-soft/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium transition group-hover:text-plan-text">{{ $activity->name }}</h3>
                            <span class="rounded-full bg-canvas px-2 py-0.5 text-sm font-medium text-ink-50">{{ str($activity->type)->replace('_', ' ')->headline() }}</span>
                            @include('activities._analysis-status', ['status' => $activity->latestAiAnalysis?->status->value])
                        </div>
                        <p class="mt-1 text-sm text-ink-50">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM YYYY · HH:mm') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <span><strong class="font-medium text-ink">{{ $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-ink-50">km</span></span>
                        <span><strong class="font-medium text-ink">{{ $activity->elevation_gain_m === null ? '–' : number_format($activity->elevation_gain_m, 0, ',', '.') }}</strong> <span class="text-ink-50">hm</span></span>
                        <span><strong class="font-medium text-ink">{{ $activity->moving_time_s === null ? '–' : sprintf('%d:%02d', intdiv($activity->moving_time_s, 3600), intdiv($activity->moving_time_s % 3600, 60)) }}</strong> <span class="text-ink-50">h</span></span>
                        <x-icon name="chevron-right" />
                    </div>
                </a>
            @empty
                <div class="px-6 py-16 text-center sm:px-8">
                    <span class="mx-auto grid size-12 place-items-center rounded-lg bg-plan-soft text-plan-text">
                        <x-icon name="download" />
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

        <footer class="mt-8 flex flex-col gap-2 border-t border-line pt-6 text-sm text-ink-50 sm:flex-row sm:items-center sm:justify-between">
            <p>{{ __('ui.activities.footer_left') }}</p>
            <p>{{ __('ui.activities.footer_right') }}</p>
        </footer>

</main></x-layout>
