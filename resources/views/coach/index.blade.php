@php
    $goalPlan = $latestGoalPlan?->output ?? [];
    $trainingBlock = $latestTrainingBlock?->output ?? [];
    $weeklyPlan = $latestWeeklyPlan?->output ?? [];
    $phases = collect($goalPlan['phases'] ?? []);
    $weeks = collect($trainingBlock['weeks'] ?? []);
    $days = collect($weeklyPlan['days'] ?? []);
    $today = \Carbon\CarbonImmutable::today(config('app.timezone'))->toDateString();
    $dateLabel = fn ($date, $format = 'D. MMM') => $date ? \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->isoFormat($format) : '–';
    $duration = fn ($minutes) => intdiv((int) $minutes, 60).' h '.sprintf('%02d', (int) $minutes % 60);
    $currentPhase = $phases->search(fn ($p) => $p['start_date'] <= $today && $p['end_date'] >= $today);
    $currentWeek = $weeks->search(fn ($w) => $w['week_start'] <= $today && $w['week_end'] >= $today);
    $selectedPhase = min(max(0, (int) request('phase', $currentPhase === false ? 0 : $currentPhase)), max(0, $phases->count() - 1));
    $selectedWeek = min(max(0, (int) request('block_week', $currentWeek === false ? 0 : $currentWeek)), max(0, $weeks->count() - 1));
    $defaultDay = $days->firstWhere('date', $today) ?? $days->first(fn ($day) => $day['type'] === 'ride' && $day['date'] >= $today) ?? $days->firstWhere('type', 'ride') ?? $days->first();
    $selectedDay = request('day') === 'overview' ? 'overview' : ($days->contains('date', request('day')) ? request('day') : ($defaultDay['date'] ?? 'overview'));
    $ridesByDay = app(\App\Domain\Training\Services\PlanActivityMatcher::class)->match($days, $calendarActivities);
    $actualMinutes = fn ($from, $to) => $calendarActivities->filter(fn ($a) => ($d = $a->localStartedAt()->toDateString()) >= $from && $d <= $to)->sum('moving_time_s') / 60;
    $weekStart = $days->min('date') ?? (is_string(request('week')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', request('week')) ? request('week') : $today);
    $weekEnd = $days->max('date') ?? \Carbon\CarbonImmutable::parse($weekStart)->addDays(6)->toDateString();
    $ridden = $actualMinutes($weekStart, $weekEnd);
    $openRides = $days->filter(fn ($d) => $d['type'] === 'ride' && ! ($d['completed'] ?? false) && $ridesByDay->get($d['date'], collect())->isEmpty())->count();
    $maxMinutes = max(90, (int) $days->max('duration_minutes'), (int) ceil($ridesByDay->map(fn ($rides) => $rides->sum('moving_time_s') / 60)->max() ?? 0));
    $maxBlockMinutes = max(300, (int) $weeks->max('target_duration_minutes'), (int) ceil($weeks->map(fn ($w) => $actualMinutes($w['week_start'], $w['week_end']))->max() ?? 0));
    $keyDay = $days->filter(fn ($d) => $d['type'] === 'ride')->sortByDesc(function ($d) {
        preg_match('/RPE\s*(\d+)/i', $d['intensity'] ?? '', $matches);
        return (preg_match('/intervall|interval/i', $d['title']) ? 100 : 0) + (int) ($matches[1] ?? 0);
    })->first();
    $goalProfileReady = $profile?->goal && $profile?->goal_date?->isAfter(\Carbon\CarbonImmutable::today(config('app.timezone')));
    $actions = [
        'goal-plan' => ['route' => 'coach.goal-plan.store', 'ready' => $aiConfigured && $goalProfileReady, 'label' => $latestGoalPlan ? __('ui.coach.recreate_roadmap') : __('ui.coach.create_roadmap')],
        'training-block' => ['route' => 'coach.training-block.store', 'ready' => $aiConfigured && $latestGoalPlan, 'label' => $latestTrainingBlock ? __('ui.coach.recreate_block') : __('ui.coach.create_block')],
        'weekly-plan' => ['route' => 'coach.weekly-plan.store', 'ready' => $aiConfigured && ($profile?->available_days ?? []) !== [], 'label' => $latestWeeklyPlan ? __('ui.coach.recreate_plan') : __('ui.coach.create_plan')],
    ];
@endphp
<x-layout title="Coach">
<x-topbar>
    <x-slot:context><span class="hidden text-[13px] font-semibold sm:block">Coach</span><a href="{{ route('activities.index') }}" class="hidden text-[13px] text-ink-50 sm:block">{{ __('ui.coach.rides') }}</a></x-slot:context>
    <x-slot:center><x-segment data-coach-tabs aria-label="{{ __('ui.coach.sections') }}">
        <a href="#roadmap" data-coach-tab="goal-plan" aria-current="page">Roadmap</a>
        <a href="#block" data-coach-tab="training-block">Block</a>
        <a href="#week" data-coach-tab="weekly-plan">{{ __('design.week') }}</a>
    </x-segment></x-slot:center>
    <x-slot:actions>
        <x-button :href="route('settings.training')" secondary>{{ __('ui.settings.training_profile') }}</x-button>
        @foreach ($actions as $section => $action)
        <div data-coach-section="{{ $section }}" @if($section !== 'goal-plan') hidden @endif>
            @php $attempt = $planAttempts->get(str_replace('-', '_', $section)); @endphp
            @if ($attempt && in_array($attempt->status->value, ['pending', 'processing'], true))
                <x-button disabled aria-disabled="true">{{ __('ui.coach.plan_pending') }}</x-button>
            @elseif ($action['ready'])
            <form method="POST" action="{{ route($action['route']) }}" data-training-plan-form data-pending-label="{{ __('ui.coach.plan_pending') }}">@csrf @if($section === 'weekly-plan' && !$latestWeeklyPlan)<input type="hidden" name="plan_start" value="{{ $weekStart }}">@endif
                <x-button>{{ $action['label'] }}</x-button>
            </form>
            @else
                <x-button :href="$section === 'training-block' && $aiConfigured ? '#roadmap' : route('settings.training')" secondary>{{ $section === 'training-block' && $aiConfigured ? __('ui.coach.create_roadmap_first') : __('ui.coach.complete_profile') }}</x-button>
            @endif
        </div>
        @endforeach
    </x-slot:actions>
</x-topbar>
<x-flash />
@foreach ($planAttempts as $attempt)
    @if (in_array($attempt->status->value, ['pending', 'processing'], true))
        <div class="mx-7 my-3 text-sm" role="status" aria-live="polite" data-ai-status-url="{{ route('ai-analyses.status', $attempt) }}">
            <p>{{ __('ui.coach.'.(['goal_plan' => 'roadmap_pending', 'training_block' => 'block_pending', 'weekly_plan' => 'plan_pending'][$attempt->type])) }}</p>
            <p class="text-ink-50">{{ __('ui.messages.ai_queued') }}</p>
            <div class="mt-2 h-[3px] overflow-hidden bg-plan-soft"><span class="analysis-progress-bar block h-full w-1/3 bg-plan"></span></div>
        </div>
        <noscript><meta http-equiv="refresh" content="10"></noscript>
    @elseif ($attempt->status->value === 'failed')
        <p class="mx-7 my-3 text-sm" role="alert">{{ $attempt->error }}</p>
    @endif
@endforeach
<h1 class="sr-only">{{ __('ui.coach.headline') }}</h1>
<div class="mx-7 my-3 text-sm" data-weekly-plan-progress role="status" aria-live="polite" hidden>
    <p data-plan-progress-title>{{ __('ui.coach.plan_progress') }}</p><p class="text-ink-50" data-plan-progress-copy>{{ __('ui.coach.plan_progress_copy') }}</p>
    <div class="mt-2 h-[3px] overflow-hidden bg-plan-soft"><span class="analysis-progress-bar block h-full w-1/3 bg-plan"></span></div>
</div>

<section class="rd-shell" data-coach-section="goal-plan" data-selection-group="phase">
    <main class="rd-workspace">
        <div class="px-0 pb-4 pr-6"><p class="text-xs text-ink-50">{{ __('ui.coach.goal_roadmap') }} · {{ $dateLabel($goalPlan['plan_start'] ?? null) }} – {{ $dateLabel($goalPlan['plan_end'] ?? null, 'D. MMM YYYY') }}</p>
        <h2 class="mt-2 text-xl font-bold">{{ $goalPlan['goal_summary'] ?? __('ui.coach.no_goal') }}</h2></div>
        @if ($phases->isNotEmpty())
        @php
            $start = \Carbon\CarbonImmutable::parse($goalPlan['plan_start']);
            $length = max(1, $start->diffInDays($goalPlan['plan_end']));
            $progress = min(100, max(0, $start->diffInDays($today, false) / $length * 100));
        @endphp
        <div class="relative !mb-4 !mt-10 mr-3 h-[3px] bg-line-strong"><div class="h-full bg-plan" style="width: {{ $progress }}%"></div><span class="absolute -top-1 size-[11px] -translate-x-1/2 rounded-full bg-plan ring-3 ring-canvas" style="left: {{ $progress }}%"></span><span class="absolute -top-6 -translate-x-1/2 text-[11px] font-bold text-plan-text" style="left: {{ min(97,max(3,$progress)) }}%">{{ __('design.today') }}</span></div>
        <div class="rd-phases grid gap-[3px]" style="grid-template-columns: {{ $phases->map(fn ($p) => max(1, \Carbon\CarbonImmutable::parse($p['start_date'])->diffInDays($p['end_date']) + 1).'fr')->implode(' ') }}">
            @foreach ($phases as $index => $phase)
            <button type="button" class="rd-phase rd-selectable flex h-[220px] min-w-0 flex-col items-start overflow-hidden rounded-lg bg-surface p-3 text-left" data-select="{{ $index }}" aria-controls="phase-panel-{{ $index }}" aria-pressed="{{ $index === $selectedPhase ? 'true' : 'false' }}">
                <span class="block text-[11px] font-bold text-plan-text">{{ __('ui.coach.phase', ['number' => $index + 1]) }}</span>
                <span class="mt-1 block text-[11px] text-ink-50">{{ $dateLabel($phase['start_date']) }}–{{ $dateLabel($phase['end_date']) }}</span>
                <span class="mt-3 line-clamp-3 text-[13px] font-semibold">{{ $phase['name'] }}</span>
                <span class="mt-2 hidden text-xs text-ink-60 sm:line-clamp-3">{{ $phase['focus'] }}</span>
            </button>
            @endforeach
        </div>
        <div class="!mt-5 border-t border-line-strong py-3">@include('coach._context', ['showRoadmap' => false])<x-timeline-row :label="__('design.week')"><a class="rd-timeline-chip" href="#week">{{ $weeklyPlan['weekly_goal'] ?? __('design.no_plan') }} · {{ $duration($ridden) }} / {{ $duration($weeklyPlan['total_duration_minutes'] ?? 0) }}</a></x-timeline-row></div>
        <x-panel-label>{{ __('ui.coach.principles') }}</x-panel-label>
        <div class="grid gap-x-5 gap-y-2 pr-5 text-[13px] leading-relaxed text-ink-70 sm:grid-cols-2">@foreach ($goalPlan['principles'] ?? [] as $principle)<p>→ {{ $principle }}</p>@endforeach</div>
        @else
            <div class="rd-empty"><p>{{ __('design.no_plan') }}</p><p>{{ __('ui.coach.goal_copy') }}</p></div>
        @endif
    </main>
    <aside class="rd-panel rd-coach-panel">
        @foreach ($phases as $index => $phase)
        <div id="phase-panel-{{ $index }}" data-detail="{{ $index }}" @if($index !== $selectedPhase) hidden @endif>
            <p class="text-xs font-semibold text-plan-text">{{ __('ui.coach.phase', ['number' => $index + 1]) }} · {{ $dateLabel($phase['start_date']) }} – {{ $dateLabel($phase['end_date']) }}</p>
            <h2 class="mt-2">{{ $phase['name'] }}</h2>
            <x-panel-label>{{ __('design.focus') }}</x-panel-label><p class="text-[15px] leading-relaxed">{{ $phase['focus'] }}</p>
            <x-panel-label>{{ __('ui.coach.success_marker') }}</x-panel-label><p class="text-sm leading-relaxed text-ink-70">{{ $phase['success_marker'] }}</p>
            @if ($index === $currentPhase && $latestTrainingBlock)<x-info-box class="mt-4">{{ $trainingBlock['phase_name'] ?? '' }} · <a href="#block" class="font-semibold text-plan-text">{{ __('ui.coach.training_block') }} ›</a> <a href="#week" class="font-semibold text-plan-text">{{ __('design.to_week') }} ›</a></x-info-box>@endif
        </div>
        @endforeach
        <section class="rd-panel-section"><x-panel-label>{{ __('design.your_goal') }}</x-panel-label><p class="text-[15px] italic leading-relaxed">{{ $profile?->goal ?: __('ui.coach.no_goal') }}</p>
        <div class="mt-4 grid grid-cols-2 gap-4 text-[13px]">
            <div><x-panel-label>{{ __('ui.settings.goal_date') }}</x-panel-label>{{ $dateLabel($profile?->goal_date, 'D. MMM YYYY') }}</div>
            <div><x-panel-label>{{ __('ui.settings.experience') }}</x-panel-label>{{ $profile?->experience_level ? __('ui.settings.'.$profile->experience_level) : '–' }}</div>
            <div><x-panel-label>{{ __('ui.settings.hours_week') }}</x-panel-label>@if($profile?->weekly_hours){{ $profile->weekly_hours }} h @else <a class="text-plan-text" href="{{ route('settings.training') }}">{{ __('ui.coach.budget_open') }} ›</a>@endif</div>
            <div><x-panel-label>{{ __('ui.settings.constraints') }}</x-panel-label>{{ $profile?->constraints ?: '–' }}</div>
        </div></section>
        <details class="rd-panel-section text-sm text-ink-70"><summary>{{ __('design.plan_overview') }}</summary><p class="mt-3 leading-relaxed">{{ $goalPlan['overview'] ?? '' }}</p></details>
        @foreach ($goalPlan['cautions'] ?? [] as $caution)<p class="mt-3 text-[11px] text-ink-45">{{ $caution }}</p>@endforeach
    </aside>
</section>

<section class="rd-shell" data-coach-section="training-block" data-selection-group="block_week" hidden>
    <main class="rd-workspace">
        @include('coach._context', ['showBlock' => false])
        <div class="!mt-5 border-t border-line-strong pb-4 pt-4 pr-5"><p class="text-xs text-ink-50">{{ __('ui.coach.training_block') }} · {{ $dateLabel($trainingBlock['block_start'] ?? null) }} – {{ $dateLabel($trainingBlock['block_end'] ?? null) }}</p><h2 class="mt-2 text-lg font-bold">{{ $trainingBlock['block_goal'] ?? __('design.no_plan') }}</h2><p class="mt-2 text-xs text-ink-50">{{ __('ui.coach.planned_volume') }} · {{ $duration($weeks->sum('target_duration_minutes')) }}</p></div>
        @if ($weeks->isNotEmpty())
        <div class="grid gap-1.5" style="grid-template-columns: 40px repeat({{ $weeks->count() }},minmax(0,1fr))">
            <div class="relative mt-12 h-[300px] text-[11px] text-ink-40"><span class="absolute top-0">{{ round($maxBlockMinutes / 60,1) }} h</span><span class="absolute top-1/2">{{ round($maxBlockMinutes / 120,1) }} h</span><span class="absolute bottom-0">0 h</span></div>
            @foreach ($weeks as $index => $week)
            <div class="min-w-0"><div class="h-12 text-xs"><b>{{ __('ui.coach.week', ['number' => $week['week']]) }}</b><span class="mt-1 block text-ink-50">{{ $dateLabel($week['week_start'], 'D.') }}–{{ $dateLabel($week['week_end']) }}</span></div>
            <button type="button" class="rd-selectable relative h-[300px] w-full rounded-lg bg-surface text-left" data-select="{{ $index }}" aria-controls="block-panel-{{ $index }}" aria-pressed="{{ $index === $selectedWeek ? 'true' : 'false' }}">
                <span class="absolute inset-x-1 bottom-1 overflow-hidden rounded-md border-[1.5px] border-dashed border-plan-dash p-2 text-xs" style="height: {{ min(97, $week['target_duration_minutes'] / $maxBlockMinutes * 97) }}%"><b class="text-plan-text">{{ $duration($week['target_duration_minutes']) }}</b>@foreach ($week['key_sessions'] ?? [] as $session)<span class="mt-1 block truncate rounded bg-canvas p-1.5 text-ink-70" title="{{ $session }}">{{ $session }}</span>@endforeach</span>
                @if ($actualMinutes($week['week_start'], $week['week_end']) > 0)<span class="absolute bottom-1 left-1 w-3 rounded bg-plan" style="height: {{ min(97, $actualMinutes($week['week_start'], $week['week_end']) / $maxBlockMinutes * 97) }}%" title="{{ $duration($actualMinutes($week['week_start'], $week['week_end'])) }}"></span>@endif
            </button></div>
            @endforeach
        </div>
        <div class="rd-legend"><span>▱ {{ __('design.target_sessions') }}</span><span class="text-plan-text">■ {{ __('design.ridden') }}</span></div>
        @else<div class="rd-empty">{{ __('design.no_plan') }}<p>{{ __('ui.coach.block_copy') }}</p></div>@endif
    </main>
    <aside class="rd-panel rd-coach-panel">
        @foreach ($weeks as $index => $week)
        <div id="block-panel-{{ $index }}" data-detail="{{ $index }}" @if($index !== $selectedWeek) hidden @endif>
            <p class="text-xs font-semibold text-plan-text">{{ __('ui.coach.week', ['number' => $week['week']]) }} · {{ $dateLabel($week['week_start']) }}–{{ $dateLabel($week['week_end']) }}</p>
            <h2 class="mt-2">{{ __('ui.coach.'.(['recovery'=>'recovery','maintenance'=>'maintenance','build'=>'build','peak'=>'peak'][$week['load_level']] ?? 'build')) }}</h2>
            <x-panel-label>{{ __('design.focus') }}</x-panel-label><p class="text-[15px] leading-relaxed">{{ $week['focus'] }}</p>
            <x-panel-label>{{ __('design.volume') }}</x-panel-label><p class="text-sm text-ink-70">{{ $duration($week['target_duration_minutes']) }} · {{ $duration($actualMinutes($week['week_start'], $week['week_end'])) }} {{ __('design.ridden') }}</p>
            <a class="mt-2 inline-block text-sm font-semibold text-plan-text" href="{{ route('coach.index', ['week' => $week['week_start']]) }}#week">{{ __('ui.activity.open_week_plan') }} ›</a>
            <x-panel-label>{{ __('design.key_sessions') }}</x-panel-label>@foreach($week['key_sessions'] ?? [] as $session)<p class="mt-2 text-sm leading-relaxed text-ink-70">{{ $session }}</p>@endforeach
        </div>
        @endforeach
        <section class="rd-panel-section"><x-panel-label>{{ __('ui.coach.adaptation') }}</x-panel-label>@foreach ($trainingBlock['adaptation_rules'] ?? [] as $rule)<x-info-box class="mt-2">{{ $rule }}</x-info-box>@endforeach</section>
        <details class="rd-panel-section text-sm text-ink-70"><summary>{{ __('design.plan_overview') }}</summary><p class="mt-3 leading-relaxed">{{ $trainingBlock['overview'] ?? '' }}</p></details>
        @foreach ($trainingBlock['cautions'] ?? [] as $caution)<p class="mt-3 text-[11px] text-ink-45">{{ $caution }}</p>@endforeach
        @if($latestTrainingBlock)<p class="mt-5 text-[11px] text-ink-45">{{ __('ui.coach.block_footer', ['time' => $latestTrainingBlock->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}</p>@endif
    </aside>
</section>

<section class="rd-shell" data-coach-section="weekly-plan" data-selection-group="day" data-weekly-plan-panel hidden>
    <main class="rd-workspace">
        @include('coach._context')
        <div class="!my-5 flex flex-wrap items-center justify-between gap-2 border-t border-line-strong pt-4 pr-4">
            <div class="flex items-center gap-2"><x-button :href="route('coach.index', ['week' => \Carbon\CarbonImmutable::parse($weekStart)->subDays(7)->toDateString()]).'#week'" secondary aria-label="{{ __('design.previous_week') }}">‹</x-button><span class="text-[13px] font-bold">{{ $dateLabel($weekStart) }} – {{ $dateLabel($weekEnd, 'D. MMM YYYY') }}</span><x-button :href="route('coach.index', ['week' => \Carbon\CarbonImmutable::parse($weekStart)->addDays(7)->toDateString()]).'#week'" secondary aria-label="{{ __('design.next_week') }}">›</x-button></div>
            <x-button :href="route('coach.index', ['week' => $today]).'#week'" secondary>{{ __('design.today') }}</x-button>
        </div>
        @if ($days->isNotEmpty())
        <h2 class="sr-only">{{ __('ui.coach.seven_day_plan') }}</h2>
        <div class="rd-calendar mb-2 text-xs"><span class="self-end text-ink-50">min</span>@foreach ($days as $day)<span class="py-1 text-center"><span class="text-ink-50">{{ $dateLabel($day['date'],'dd') }}</span> <b>{{ $dateLabel($day['date'],'D') }}</b>@if($day['date'] === $today)<span class="mx-auto mt-1 block w-fit rounded-full bg-ink px-1 text-[10px] text-white">{{ __('design.today') }}</span>@endif</span>@endforeach</div>
        <div class="rd-calendar">
            <div class="relative text-[11px] text-ink-40"><span class="absolute top-0">0</span><span class="absolute top-1/3">{{ round($maxMinutes/3) }}</span><span class="absolute top-2/3">{{ round($maxMinutes*2/3) }}</span><span class="absolute bottom-0">{{ $maxMinutes }}</span></div>
            @foreach ($days as $day)
            @php
                $dayRides = $ridesByDay->get($day['date'], collect());
            @endphp
            <button type="button" class="rd-calendar-column rd-selectable {{ ($keyDay['date'] ?? null) === $day['date'] ? 'ring-1 ring-plan/40' : '' }}" data-select="{{ $day['date'] }}" aria-controls="day-panel-{{ $day['date'] }}" aria-pressed="{{ $day['date'] === $selectedDay ? 'true' : 'false' }}" aria-label="{{ $dateLabel($day['date'], 'dddd D. MMMM') }} · {{ $day['title'] }}">
                @if ($day['type'] === 'rest' && $dayRides->isEmpty())<span class="absolute inset-x-1 top-2 break-words text-center text-[11px] text-ink-45">{{ __('ui.coach.rest_day') }}</span>
                @else
                <span class="rd-calendar-block left-[5px] border-[1.5px] border-dashed {{ $day['type'] === 'ride' ? 'border-plan-dash text-plan-text' : 'border-border-input text-ink-60' }}" style="height: {{ min(97,max(4,($day['duration_minutes'] ?? 0)/$maxMinutes*97)) }}%; {{ $dayRides->isNotEmpty() ? 'width: calc(50% - 7px)' : 'right: 5px' }}">
                    <span class="rd-block-copy">@if($dayRides->isNotEmpty()){{ __('design.plan') }}<br>{{ $day['duration_minutes'] }}′@else<b class="block">{{ $day['title'] }}</b><span class="mt-1 block">{{ $day['duration_minutes'] }} min · {{ preg_match('/RPE\s*[0-9]+(?:[–-][0-9]+)?/u', $day['intensity'], $intensityMatch) ? $intensityMatch[0] : \Illuminate\Support\Str::limit($day['intensity'], 24) }}</span>@endif</span>
                    @if (($keyDay['date'] ?? null) === $day['date'])<span class="rd-block-copy mt-2 block text-[10px] font-bold">{{ __('design.key_session') }}</span>@include('coach._intervals', ['details' => $day['details']])@endif
                    @if($day['completed'] ?? false)<span class="block">✓</span>@endif
                </span>
                @if($dayRides->isNotEmpty())<span class="rd-calendar-block right-[5px] bg-plan text-white" style="width: calc(50% - 7px); height: {{ min(97,max(4,$dayRides->sum('moving_time_s')/60/$maxMinutes*97)) }}%"><span class="rd-block-copy">{{ __('design.actual') }}<br>{{ round($dayRides->sum('moving_time_s')/60) }}′</span></span>@endif
                @endif
            </button>
            @endforeach
        </div>
        <div class="rd-legend"><span>▱ {{ __('design.planned') }}</span><span class="text-plan-text">■ {{ __('design.ridden') }}</span><span class="ml-auto pr-3">{{ $duration($weeklyPlan['total_duration_minutes'] ?? 0) }} · {{ $duration($ridden) }} {{ __('design.ridden') }} · <b>{{ __('design.open_rides', ['open' => $openRides, 'total' => $days->where('type','ride')->count()]) }}</b></span></div>
        @else<div class="rd-empty"><p>{{ __('design.no_plan') }}</p><p>{{ __('ui.coach.week_copy') }}</p></div>@endif
    </main>
    <aside class="rd-panel rd-coach-panel">
        <div data-detail="overview" @if($selectedDay !== 'overview') hidden @endif>
            <p class="text-xs font-semibold text-plan-text">{{ __('ui.coach.seven_day_plan') }}</p><h2 class="mt-2">{{ $weeklyPlan['weekly_goal'] ?? __('design.no_plan') }}</h2><p class="mt-4 text-[15px] leading-relaxed">{{ $weeklyPlan['overview'] ?? __('ui.coach.week_copy') }}</p><x-panel-label>{{ __('ui.coach.planned_volume') }}</x-panel-label><p>{{ $duration($weeklyPlan['total_duration_minutes'] ?? 0) }}</p>
        </div>
        @foreach ($days as $day)
        <div id="day-panel-{{ $day['date'] }}" data-detail="{{ $day['date'] }}" @if($day['date'] !== $selectedDay) hidden @endif>
            <div class="flex items-start justify-between gap-2"><p class="text-xs font-semibold text-plan-text">{{ $dateLabel($day['date'], 'dddd, D. MMMM') }}</p><button type="button" class="text-xs text-ink-45" data-select="overview" aria-label="{{ __('design.close') }}">×</button></div>
            <h2 class="mt-2">{{ $day['title'] }}</h2>
            <div class="mt-3 flex flex-wrap gap-1.5">@if($day['duration_minutes'])<span class="rounded-md bg-canvas px-2 py-1 text-xs font-semibold">{{ $day['duration_minutes'] }} min</span>@endif<x-status-pill>{{ __('ui.coach.'.(['ride'=>'ride','rest'=>'rest_day','mobility'=>'mobility'][$day['type']])) }}</x-status-pill>@if(($keyDay['date'] ?? null) === $day['date'])<x-status-pill>{{ __('design.key_session') }}</x-status-pill>@endif</div>
            <x-panel-label>{{ __('design.intensity') }}</x-panel-label><p class="text-sm leading-relaxed">{{ $day['intensity'] }}</p>
            <x-panel-label>{{ __('design.details') }}</x-panel-label><p class="text-[15px] leading-relaxed">{{ $day['details'] }}</p>
            <x-panel-label>{{ __('ui.coach.why') }}</x-panel-label><p class="text-sm leading-relaxed text-ink-70">{{ $day['rationale'] }}</p>
            @if($latestTrainingBlock)<x-info-box class="mt-4"><a href="#block" class="font-semibold text-plan-text">{{ $trainingBlock['phase_name'] ?? __('ui.coach.training_block') }} · {{ __('design.to_block') }} ›</a></x-info-box>@endif
            <div class="mt-5 flex items-start gap-2">
                <form method="POST" action="{{ route('coach.days.update', [$latestWeeklyPlan->id, $day['date']]) }}" class="flex-1">@csrf @method('PATCH')<input type="hidden" name="action" value="complete"><x-button class="w-full">{{ ($day['completed'] ?? false) ? __('design.undo_complete') : __('design.complete') }}</x-button></form>
                <details class="relative"><summary class="rd-button rd-button-secondary">{{ __('design.move') }}</summary><form method="POST" action="{{ route('coach.days.update', [$latestWeeklyPlan->id, $day['date']]) }}" class="absolute right-0 z-10 mt-2 w-64 rounded-lg border border-line bg-surface p-3">@csrf @method('PATCH')<input type="hidden" name="action" value="move"><p class="mb-2 text-xs text-ink-50">{{ __('design.swap_day') }}</p><div class="grid grid-cols-3 gap-1">@foreach($days as $target)@if($target['date'] !== $day['date'])<button name="target_date" value="{{ $target['date'] }}" class="rounded-md border border-border p-2 text-xs">{{ $dateLabel($target['date'], 'dd D.') }}</button>@endif @endforeach</div></form></details>
            </div>
            @foreach($ridesByDay->get($day['date'], collect()) as $ride)<a class="mt-3 block text-[13px] font-semibold text-plan-text" href="{{ route('activities.show', $ride) }}">● {{ $ride->name }} · {{ round($ride->moving_time_s/60) }} min ›</a>@endforeach
            @if($day['type'] === 'ride' && $activities->isNotEmpty())
            <details class="mt-4 text-xs text-ink-50"><summary>{{ __('design.link_activity') }}</summary><form class="mt-2 flex gap-2" method="POST" action="{{ route('coach.days.update', [$latestWeeklyPlan->id, $day['date']]) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="link"><select name="activity_id" class="min-w-0 flex-1 rounded-lg border border-border p-2" aria-label="{{ __('design.link_activity') }}">@foreach($activities as $ride)<option value="{{ $ride->id }}">{{ $dateLabel($ride->started_at) }} · {{ $ride->name }}</option>@endforeach</select><x-button secondary>{{ __('design.link') }}</x-button></form></details>
            @endif
        </div>
        @endforeach
        @foreach ($weeklyPlan['cautions'] ?? [] as $caution)<x-info-box class="mt-4">{{ $caution }}</x-info-box>@endforeach
        @foreach ($weeklyPlan['data_gaps'] ?? [] as $gap)<p class="mt-3 text-[11px] text-ink-45">{{ $gap }}</p>@endforeach
        @if(!empty($weeklyPlan['data_gaps']))<a class="text-xs text-plan-text" href="{{ route('settings.training') }}">{{ __('ui.coach.complete_profile') }} ›</a>@endif
        @if($latestWeeklyPlan)<p class="mt-5 text-[11px] text-ink-45">{{ __('ui.common.ai_disclaimer', ['time' => $latestWeeklyPlan->completed_at?->locale(app()->getLocale())->diffForHumans()]) }}</p>@endif
        <div class="rd-panel-section space-y-3">        <details class="pr-5 text-xs text-ink-50"><summary>{{ __('ui.coach.analyze_ride') }}</summary><div class="mt-2 space-y-2">@foreach($activities as $ride)<a class="flex justify-between gap-3 rounded-lg bg-surface p-3 text-sm" href="{{ route('activities.show', $ride) }}#ai-analysis-heading"><span>{{ $ride->name }} · {{ $dateLabel($ride->started_at) }}</span>@include('activities._analysis-status', ['status' => $ride->latestAiAnalysis?->status->value])</a>@endforeach</div></details>
        @if($weeklyPlans->isNotEmpty())<details class="pr-5 text-xs text-ink-50"><summary>{{ __('design.saved_plans') }}</summary><div class="mt-2 flex flex-wrap gap-2">@foreach($weeklyPlans as $saved)<a class="rd-timeline-chip" href="{{ route('coach.index', ['plan' => $saved->id]) }}#week">{{ $dateLabel(data_get($saved->output,'days.0.date')) }} · {{ $saved->created_at->locale(app()->getLocale())->diffForHumans() }}</a>@endforeach</div></details>@endif
</div>
    </aside>
</section>
</x-layout>
