<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('ui.coach.meta') }}">
    <title>Coach · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="{{ __('ui.common.back_to_overview') }}">
                <span class="grid size-9 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">RideDock</span>
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 4-6 6 6 6"/></svg>
                {{ __('ui.common.overview') }}
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
        @endif
        @if (session('analysis_error'))
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900" role="alert">{{ session('analysis_error') }}</div>
        @endif

        <section class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-emerald-700">RideDock Coach</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">{{ __('ui.coach.headline') }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">{{ __('ui.coach.intro') }}</p>
            </div>
            <a href="{{ route('settings.training') }}" class="w-fit rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-800">{{ __('ui.settings.training_profile') }}</a>
        </section>

        @php
            $goalProfileReady = $profile?->goal && $profile?->goal_date?->isAfter(\Carbon\CarbonImmutable::today(config('app.timezone')));
            $goalPlanReady = $latestGoalPlan?->status->value === 'completed';
            $trainingBlockReady = $latestTrainingBlock?->status->value === 'completed';
        @endphp

        <nav class="mt-8 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm shadow-slate-950/[0.03]" aria-label="{{ __('ui.coach.sections') }}" data-coach-tabs>
            <div class="flex min-w-max gap-1">
                <a href="#goal-plan" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition" data-coach-tab="goal-plan" aria-current="page">{{ __('ui.coach.roadmap') }}</a>
                <a href="#training-block" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" data-coach-tab="training-block">{{ __('ui.coach.four_week_tab') }}</a>
                <a href="#weekly-plan" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" data-coach-tab="weekly-plan">{{ __('ui.coach.seven_day_tab') }}</a>
                <a href="#rides" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" data-coach-tab="rides">{{ __('ui.coach.rides') }}</a>
            </div>
        </nav>

        <section class="mt-6 grid gap-4" aria-label="{{ __('ui.coach.active_section') }}">
            <a href="#rides" class="group rounded-3xl border border-emerald-200 bg-emerald-50 p-6 transition hover:border-emerald-300 hover:bg-emerald-100/70 sm:p-7" data-coach-section="rides" hidden>
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 19V9m5 10V5m5 14v-7m4 7V3"/></svg>
                    </span>
                    <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium {{ $aiConfigured ? 'text-emerald-800' : 'text-amber-800' }}">{{ $aiConfigured ? __('ui.common.available') : __('ui.coach.configuration_missing') }}</span>
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight">{{ __('ui.coach.analyze_ride') }}</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-950/70">{{ __('ui.coach.analyze_copy') }}</p>
                <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-800">
                    {{ __('ui.coach.select_ride') }}
                    <svg viewBox="0 0 20 20" class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 4 6 6-6 6"/></svg>
                </span>
            </a>

            <article id="goal-plan" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7" data-coach-section="goal-plan">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="m15 9 5-5m-2 0h2v2"/></svg>
                    </span>
                    @if ($aiConfigured && $goalProfileReady)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ __('ui.common.available') }}</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">{{ __('ui.coach.complete_profile') }}</span>
                    @endif
                </div>
                <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('ui.coach.whole_goal_period') }}</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.coach.goal_roadmap') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.coach.goal_copy') }}</p>
                @if ($aiConfigured && $goalProfileReady)
                    <form method="POST" action="{{ route('coach.goal-plan.store') }}" class="mt-5" data-training-plan-form data-pending-label="{{ __('ui.coach.roadmap_pending') }}" data-progress-title="{{ __('ui.coach.roadmap_progress') }}" data-progress-copy="{{ __('ui.coach.roadmap_progress_copy') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            {{ $goalPlanReady ? __('ui.coach.recreate_roadmap') : __('ui.coach.create_roadmap') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('settings.training') }}" class="mt-5 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.coach.complete_goal') }}</a>
                @endif
            </article>

            <article id="training-block" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7" data-coach-section="training-block" hidden>
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-violet-50 text-violet-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="7" cy="6" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="17" cy="18" r="1" fill="currentColor"/></svg>
                    </span>
                    @if ($aiConfigured && $goalPlanReady)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ __('ui.common.available') }}</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">{{ __('ui.coach.roadmap_required') }}</span>
                    @endif
                </div>
                <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('ui.coach.current_four_weeks') }}</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.coach.training_block') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.coach.block_copy') }}</p>
                @if ($aiConfigured && $goalPlanReady)
                    <form method="POST" action="{{ route('coach.training-block.store') }}" class="mt-5" data-training-plan-form data-pending-label="{{ __('ui.coach.block_pending') }}" data-progress-title="{{ __('ui.coach.block_progress') }}" data-progress-copy="{{ __('ui.coach.block_progress_copy') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            {{ $trainingBlockReady ? __('ui.coach.recreate_block') : __('ui.coach.create_block') }}
                        </button>
                    </form>
                @else
                    <a href="#goal-plan" class="mt-5 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.coach.create_roadmap_first') }}</a>
                @endif
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7" data-coach-section="weekly-plan" hidden>
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-11 place-items-center rounded-2xl bg-sky-50 text-sky-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 5h14v14H5zM8 3v4m8-4v4M5 10h14"/></svg>
                    </span>
                    @if ($aiConfigured && ($profile?->available_days ?? []) !== [])
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ __('ui.common.available') }}</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">{{ __('ui.coach.complete_profile') }}</span>
                    @endif
                </div>
                <p class="mt-5 text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('ui.coach.coming_seven_days') }}</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.coach.seven_day_plan') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.coach.week_copy') }}</p>
                @if ($aiConfigured && ($profile?->available_days ?? []) !== [])
                    <form method="POST" action="{{ route('coach.weekly-plan.store') }}" class="mt-5" data-training-plan-form data-weekly-plan-form data-pending-label="{{ __('ui.coach.plan_pending') }}" data-progress-title="{{ __('ui.coach.week_progress') }}" data-progress-copy="{{ __('ui.coach.week_progress_copy') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            {{ $latestWeeklyPlan?->status->value === 'completed' ? __('ui.coach.recreate_plan') : __('ui.coach.create_plan') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('settings.training') }}" class="mt-5 inline-flex text-sm font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.coach.complete_days') }}</a>
                @endif
            </article>
        </section>

        <section class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-7 sm:px-8" data-weekly-plan-progress role="status" aria-live="polite" aria-busy="true" hidden>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium text-emerald-950" data-plan-progress-title>{{ __('ui.coach.plan_progress') }}</h2>
                    <p class="mt-1 text-sm text-emerald-900/65" data-plan-progress-copy>{{ __('ui.coach.plan_progress_copy') }}</p>
                </div>
                <span class="shrink-0 text-xs font-medium text-emerald-700">{{ __('ui.common.please_wait') }}</span>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-emerald-200" role="progressbar" aria-label="{{ __('ui.coach.planning_progress') }}" aria-valuetext="{{ __('ui.coach.training_plan_pending') }}">
                <span class="analysis-progress-bar block h-full w-1/3 rounded-full bg-emerald-600"></span>
            </div>
        </section>

        <section class="mt-6" data-coach-section="goal-plan">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.03]">
                <p class="text-sm text-slate-500">{{ __('ui.settings.training_profile') }}</p>
                @if ($profile)
                    <p class="mt-2 font-medium">{{ $profile->goal ?: __('ui.coach.no_goal') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $profile->weekly_hours ? __('ui.coach.hours_per_week', ['hours' => \Illuminate\Support\Number::format($profile->weekly_hours, 1, locale: app()->getLocale())]) : __('ui.coach.budget_open') }}</p>
                @else
                    <p class="mt-2 font-medium">{{ __('ui.coach.profile_empty') }}</p>
                    <a href="{{ route('settings.training') }}" class="mt-2 inline-flex text-sm font-medium text-emerald-700">{{ __('ui.coach.create_profile') }}</a>
                @endif
            </article>
        </section>

        @if ($goalPlanReady)
            @php
                $goalPlan = $latestGoalPlan->output ?? [];
            @endphp
            <section id="goal-plan-details" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="goal-plan-heading" data-coach-section="goal-plan">
                <div class="bg-gradient-to-br from-emerald-50 via-white to-white px-6 py-7 sm:px-8 sm:py-8">
                    <p class="text-sm font-medium text-emerald-700">{{ __('ui.coach.long_term') }}</p>
                    <h2 id="goal-plan-heading" class="mt-1 text-2xl font-semibold tracking-tight">{{ __('ui.coach.goal_roadmap') }}</h2>
                    <p class="mt-4 text-lg font-medium leading-7 text-slate-800">{{ data_get($goalPlan, 'goal_summary') }}</p>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ data_get($goalPlan, 'overview') }}</p>
                    <p class="mt-4 text-xs text-slate-400">
                        {{ \Carbon\CarbonImmutable::parse((string) data_get($goalPlan, 'plan_start'))->locale(app()->getLocale())->isoFormat('D. MMM YYYY') }}
                        {{ __('ui.coach.until') }} {{ \Carbon\CarbonImmutable::parse((string) data_get($goalPlan, 'plan_end'))->locale(app()->getLocale())->isoFormat('D. MMM YYYY') }}
                    </p>
                </div>

                <div class="grid gap-4 bg-stone-50/70 p-4 sm:p-6 md:grid-cols-2">
                    @foreach ((array) data_get($goalPlan, 'phases', []) as $index => $phase)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.025]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">{{ __('ui.coach.phase', ['number' => $index + 1]) }}</p>
                                    <h3 class="mt-1 font-semibold text-slate-900">{{ data_get($phase, 'name') }}</h3>
                                </div>
                                <p class="shrink-0 text-right text-xs leading-5 text-slate-400">
                                    {{ \Carbon\CarbonImmutable::parse((string) data_get($phase, 'start_date'))->locale(app()->getLocale())->isoFormat('D. MMM') }}<br>
                                    {{ __('ui.coach.until') }} {{ \Carbon\CarbonImmutable::parse((string) data_get($phase, 'end_date'))->locale(app()->getLocale())->isoFormat('D. MMM') }}
                                </p>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600">{{ data_get($phase, 'focus') }}</p>
                            <p class="mt-4 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500"><span class="font-medium text-slate-700">{{ __('ui.coach.success_marker') }}</span> {{ data_get($phase, 'success_marker') }}</p>
                        </article>
                    @endforeach
                </div>

                @if ((array) data_get($goalPlan, 'principles', []))
                    <div class="border-t border-slate-100 px-6 py-5 sm:px-8">
                        <p class="text-sm font-medium text-slate-800">{{ __('ui.coach.principles') }}</p>
                        <ul class="mt-3 grid gap-2 text-sm leading-6 text-slate-600 sm:grid-cols-2">
                            @foreach ((array) data_get($goalPlan, 'principles', []) as $principle)
                                <li class="flex gap-2"><span class="text-emerald-600">✓</span><span>{{ $principle }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="border-t border-slate-100 px-6 py-4 text-xs text-slate-400 sm:px-8">
                    {{ __('ui.coach.roadmap_footer', ['time' => $latestGoalPlan->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}
                </div>
            </section>
        @endif

        @if ($trainingBlockReady)
            @php
                $trainingBlock = $latestTrainingBlock->output ?? [];
            @endphp
            <section id="training-block-details" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="training-block-heading" data-coach-section="training-block" hidden>
                <div class="bg-gradient-to-br from-violet-50 via-white to-white px-6 py-7 sm:px-8 sm:py-8">
                    <p class="text-sm font-medium text-violet-700">{{ __('ui.coach.current_focus', ['phase' => data_get($trainingBlock, 'phase_name')]) }}</p>
                    <h2 id="training-block-heading" class="mt-1 text-2xl font-semibold tracking-tight">{{ __('ui.coach.four_week_block') }}</h2>
                    <p class="mt-4 text-lg font-medium leading-7 text-slate-800">{{ data_get($trainingBlock, 'block_goal') }}</p>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ data_get($trainingBlock, 'overview') }}</p>
                </div>

                <div class="grid gap-4 bg-stone-50/70 p-4 sm:p-6 md:grid-cols-2">
                    @foreach ((array) data_get($trainingBlock, 'weeks', []) as $week)
                        @php
                            $loadPresentation = match (data_get($week, 'load_level')) {
                                'recovery' => [__('ui.coach.recovery'), 'bg-sky-50 text-sky-700'],
                                'maintenance' => [__('ui.coach.maintenance'), 'bg-slate-100 text-slate-600'],
                                'peak' => [__('ui.coach.peak'), 'bg-amber-50 text-amber-700'],
                                default => [__('ui.coach.build'), 'bg-violet-50 text-violet-700'],
                            };
                            $weekMinutes = (int) data_get($week, 'target_duration_minutes', 0);
                            $weekDuration = intdiv($weekMinutes, 60).' h'.($weekMinutes % 60 ? ' '.($weekMinutes % 60).' min' : '');
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/[0.025]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-violet-700">{{ __('ui.coach.week', ['number' => data_get($week, 'week')]) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ \Carbon\CarbonImmutable::parse((string) data_get($week, 'week_start'))->locale(app()->getLocale())->isoFormat('D. MMM') }}–{{ \Carbon\CarbonImmutable::parse((string) data_get($week, 'week_end'))->locale(app()->getLocale())->isoFormat('D. MMM') }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $loadPresentation[1] }}">{{ $loadPresentation[0] }}</span>
                            </div>
                            <p class="mt-4 text-sm font-medium leading-6 text-slate-800">{{ data_get($week, 'focus') }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ __('ui.coach.target_volume', ['duration' => $weekDuration]) }}</p>
                            @if ((array) data_get($week, 'key_sessions', []))
                                <ul class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-500">
                                    @foreach ((array) data_get($week, 'key_sessions', []) as $session)
                                        <li class="flex gap-2"><span class="text-violet-500">•</span><span>{{ $session }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if ((array) data_get($trainingBlock, 'adaptation_rules', []))
                    <div class="border-t border-slate-100 px-6 py-5 sm:px-8">
                        <p class="text-sm font-medium text-slate-800">{{ __('ui.coach.adaptation') }}</p>
                        <ul class="mt-3 grid gap-2 text-sm leading-6 text-slate-600 sm:grid-cols-2">
                            @foreach ((array) data_get($trainingBlock, 'adaptation_rules', []) as $rule)
                                <li class="flex gap-2"><span class="text-violet-500">→</span><span>{{ $rule }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="border-t border-slate-100 px-6 py-4 text-xs text-slate-400 sm:px-8">
                    {{ __('ui.coach.block_footer', ['time' => $latestTrainingBlock->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}
                </div>
            </section>
        @endif

        @if ($latestWeeklyPlan?->status->value === 'completed')
            @php
                $weeklyPlan = $latestWeeklyPlan->output ?? [];
                $totalMinutes = (int) data_get($weeklyPlan, 'total_duration_minutes', 0);
                $totalHours = intdiv($totalMinutes, 60);
                $remainingMinutes = $totalMinutes % 60;
                $totalDurationLabel = $totalHours > 0
                    ? $totalHours.' h'.($remainingMinutes > 0 ? ' '.$remainingMinutes.' min' : '')
                    : $remainingMinutes.' min';
            @endphp
            <section id="weekly-plan" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="weekly-plan-heading" data-weekly-plan-panel data-coach-section="weekly-plan" hidden>
                <div class="bg-gradient-to-br from-emerald-50 via-white to-white px-6 py-7 sm:px-8 sm:py-8">
                    <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
                        <div class="max-w-3xl">
                            <p class="text-sm font-medium text-emerald-700">{{ __('ui.coach.your_seven_days') }}</p>
                            <h2 id="weekly-plan-heading" class="mt-1 text-2xl font-semibold tracking-tight">{{ __('ui.coach.seven_day_plan') }}</h2>
                            <p class="mt-4 text-lg font-medium leading-7 text-slate-800">{{ data_get($weeklyPlan, 'weekly_goal') }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($weeklyPlan, 'overview') }}</p>
                        </div>
                        <div class="w-fit shrink-0 rounded-2xl border border-emerald-100 bg-white px-4 py-3 shadow-sm shadow-emerald-950/[0.04]">
                            <p class="whitespace-nowrap text-lg font-semibold text-slate-900">{{ $totalDurationLabel }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('ui.coach.planned_volume') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 bg-stone-50/70 p-4 sm:p-6 md:grid-cols-2">
                    @foreach ((array) data_get($weeklyPlan, 'days', []) as $day)
                        @php
                            $dayType = data_get($day, 'type');
                            $dayDate = \Carbon\CarbonImmutable::parse((string) data_get($day, 'date'))->locale(app()->getLocale());
                            [$typeLabel, $typeClasses, $accentClasses] = match ($dayType) {
                                'ride' => [__('ui.coach.ride'), 'bg-emerald-50 text-emerald-700', 'border-l-emerald-500'],
                                'mobility' => [__('ui.coach.mobility'), 'bg-violet-50 text-violet-700', 'border-l-violet-400'],
                                default => [__('ui.coach.rest_day'), 'bg-slate-100 text-slate-600', 'border-l-slate-300'],
                            };
                        @endphp
                        <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 border-l-4 {{ $accentClasses }} bg-white p-5 shadow-sm shadow-slate-950/[0.025]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $dayDate->isoFormat('dddd') }}</p>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $dayDate->isoFormat('D. MMMM') }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $typeClasses }}">{{ $typeLabel }}</span>
                            </div>

                            <h3 class="mt-5 text-base font-semibold leading-6 text-slate-900">{{ data_get($day, 'title') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($day, 'details') }}</p>

                            <div class="mt-auto flex flex-wrap gap-2 pt-5 text-xs">
                                @if (data_get($day, 'duration_minutes') !== null)
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-medium text-slate-700">{{ data_get($day, 'duration_minutes') }} min</span>
                                @endif
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-slate-600">{{ data_get($day, 'intensity') }}</span>
                            </div>

                            <p class="mt-4 border-t border-slate-100 pt-3 text-xs leading-5 text-slate-400">{{ __('ui.coach.why') }} {{ data_get($day, 'rationale') }}</p>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-6 py-4 text-xs text-slate-400 sm:px-8">
                    {{ __('ui.coach.week_footer', ['time' => $latestWeeklyPlan->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}
                </div>
            </section>
        @endif

        <section id="rides" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.03]" aria-labelledby="rides-heading" data-coach-section="rides" hidden>
            <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                <p class="text-sm text-slate-500">{{ __('ui.coach.activity_analysis') }}</p>
                <h2 id="rides-heading" class="mt-1 text-xl font-semibold tracking-tight">{{ __('ui.coach.select_ride') }}</h2>
            </div>

            @forelse ($activities as $activity)
                @php($analysisStatus = $activity->latestAiAnalysis?->status->value)
                <a href="{{ route('activities.show', $activity) }}#ai-analysis-heading" class="group grid gap-4 border-b border-slate-100 px-6 py-5 transition last:border-0 hover:bg-emerald-50/40 sm:grid-cols-[1fr_auto] sm:items-center sm:px-8">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium transition group-hover:text-emerald-800">{{ $activity->name }}</h3>
                            @include('activities._analysis-status', ['status' => $analysisStatus])
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $activity->started_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM YYYY · HH:mm') }}</p>
                    </div>
                    <div class="flex items-center gap-5 text-sm">
                        <span><strong class="font-medium text-slate-900">{{ $activity->distance_m === null ? '–' : number_format($activity->distance_m / 1000, 1, ',', '.') }}</strong> <span class="text-slate-500">km</span></span>
                        <span class="rounded-xl bg-slate-950 px-3.5 py-2 font-medium text-white transition group-hover:bg-emerald-700">{{ $analysisStatus === 'completed' ? __('ui.coach.open_analysis') : __('ui.coach.select') }}</span>
                    </div>
                </a>
            @empty
                <div class="px-6 py-14 text-center sm:px-8">
                    <h3 class="font-medium">{{ __('ui.coach.no_ride') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ __('ui.coach.no_ride_copy') }}</p>
                </div>
            @endforelse
        </section>

        <footer class="mt-8 flex flex-col gap-2 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>RideDock · Coach</p>
            <p>{{ __('ui.coach.footer') }}</p>
        </footer>
    </main>
</body>
</html>
