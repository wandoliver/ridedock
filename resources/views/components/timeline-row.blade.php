@props(['label'])
<div {{ $attributes->class(['rd-timeline-row']) }}><span class="text-xs font-semibold text-ink-50">{{ $label }}</span><div class="flex min-w-0 gap-[3px]">{{ $slot }}</div></div>
