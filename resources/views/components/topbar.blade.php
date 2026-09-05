<header {{ $attributes->class(['rd-topbar']) }}>
<div class="flex min-w-0 flex-wrap items-center gap-5"><x-logo />{{ $context ?? '' }}</div>
<div class="rd-topbar-center">{{ $center ?? '' }}</div>
<div class="flex flex-wrap items-center justify-end gap-2.5">{{ $actions ?? '' }}</div>
</header>
