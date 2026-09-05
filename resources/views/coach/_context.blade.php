@if(($showRoadmap ?? true))
<x-timeline-row label="Roadmap">
@forelse($phases as $index => $phase)<a class="rd-timeline-chip" aria-current="{{ $index === $currentPhase ? 'true' : 'false' }}" href="{{ route('coach.index', ['phase' => $index]) }}#roadmap" title="{{ $phase['name'] }}">{{ $index + 1 }} {{ $phase['name'] }}</a>@empty<a class="rd-timeline-chip border-dashed" href="#roadmap">{{ __('design.no_plan') }}</a>@endforelse
</x-timeline-row>
@endif
@if(($showBlock ?? true))
<x-timeline-row label="Block">
@forelse($weeks as $index => $week)<a class="rd-timeline-chip" aria-current="{{ $index === $currentWeek ? 'true' : 'false' }}" href="{{ route('coach.index', ['block_week' => $index]) }}#block">W{{ $week['week'] }} {{ $duration($week['target_duration_minutes']) }}</a>@empty<a class="rd-timeline-chip border-dashed" href="#block">{{ __('design.no_plan') }}</a>@endforelse
</x-timeline-row>
@endif
