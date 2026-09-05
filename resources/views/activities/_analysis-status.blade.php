@if (in_array($status, ['pending', 'processing'], true))
    <span class="rounded-full bg-canvas px-2 py-0.5 text-xs font-medium text-ink-60">{{ __('ui.analysis_status.running') }}</span>
@elseif ($status === 'completed')
    <span class="rounded-full bg-plan-soft px-2 py-0.5 text-xs font-medium text-plan-text">{{ __('ui.analysis_status.analyzed') }}</span>
@elseif ($status === 'failed')
    <span class="rounded-full bg-canvas px-2 py-0.5 text-xs font-medium text-ink-60">{{ __('ui.analysis_status.failed') }}</span>
@endif
