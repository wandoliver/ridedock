<section class="mt-4 rounded-lg border border-line bg-white p-4" aria-labelledby="gear-shifts-title">
    <h2 id="gear-shifts-title">{{ __('shifts.title') }}</h2>
    <p class="mt-2 text-sm text-ink-60">{{ __('shifts.description') }}</p>
    @if($gearShifts['total'] > 0)
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach(['total','front','rear','unknown'] as $field)<div class="rounded-lg bg-canvas p-3"><dt class="text-sm text-ink-60">{{ __('shifts.'.$field) }}</dt><dd class="mt-1 text-xl font-semibold">{{ $gearShifts[$field] }}</dd></div>@endforeach
    </dl>
    <details class="mt-4"><summary class="text-sm font-semibold">{{ __('shifts.details') }}</summary>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr class="border-b border-line">@foreach(['time','component','front_gear','rear_gear','ratio'] as $label)<th scope="col" class="whitespace-nowrap p-2 font-semibold">{{ __('shifts.'.$label) }}</th>@endforeach</tr></thead>
            <tbody>@foreach($gearShifts['rows'] as $row)<tr class="border-b border-line">
                <td class="whitespace-nowrap p-2">{{ $row['elapsed_seconds'] === null ? '–' : sprintf('%d:%02d:%02d', intdiv($row['elapsed_seconds'],3600), intdiv($row['elapsed_seconds'] % 3600,60), $row['elapsed_seconds'] % 60) }}</td>
                <td class="p-2">{{ $row['direction'] ? __('shifts.'.$row['direction']) : '–' }}</td>
                @foreach(['front','rear'] as $component)<td class="whitespace-nowrap p-2">{{ $row[$component.'_gear_number'] ? __('shifts.number', ['number' => $row[$component.'_gear_number']]) : '–' }}<span class="block text-ink-50">{{ $row[$component.'_gear_teeth'] ? __('shifts.teeth', ['number' => $row[$component.'_gear_teeth']]) : '–' }}</span></td>@endforeach
                <td class="p-2">{{ $row['ratio'] === null ? '–' : \Illuminate\Support\Number::format($row['ratio'], 2, locale: app()->getLocale()) }}</td>
            </tr>@endforeach</tbody>
        </table></div>
        @if($gearShifts['truncated'])<p class="mt-3 text-sm text-ink-60">{{ __('shifts.truncated') }}</p>@endif
    </details>
    <p class="mt-3 text-sm text-ink-60">{{ __('shifts.limits') }}</p>
    @else
    <p class="mt-4 text-sm text-ink-60">{{ __('shifts.status_'.$gearShifts['status']) }}</p>
    @if(in_array($gearShifts['status'], ['absent', 'described_only'], true))
    <p class="mt-3 text-sm text-ink-60">{{ __('shifts.recording_hint') }}</p>
    @endif
    @endif
    @if($gearShifts['ignored'])<x-info-box class="mt-3">{{ __('shifts.ignored', ['count' => $gearShifts['ignored']]) }}</x-info-box>@endif
    @if($canReprocessFit)
    <form method="POST" action="{{ route('activities.fit.reprocess', ['activity' => $activity->id, 'return_to' => \App\Support\ActivityNavigation::returnTo(request())]) }}" class="mt-4">
        @csrf
        <p class="mb-3 text-sm text-ink-60">{{ __('shifts.reprocess_hint') }}</p>
        <x-button secondary>{{ __('shifts.reprocess') }}</x-button>
    </form>
    @endif
</section>
