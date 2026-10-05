@props(['title', 'description' => null, 'back' => null, 'backLabel' => null, 'parent' => null])
<header {{ $attributes->class(['rd-page-header']) }}>
    @if ($back)
        <a href="{{ $back }}" class="rd-back" data-context-back><x-icon name="arrow-left" />{{ $backLabel ?? __('ux.back') }}</a>
    @endif
    @if ($parent)
        <nav class="rd-breadcrumb" aria-label="{{ __('ux.breadcrumb') }}"><span>{{ $parent }}</span><span aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span></nav>
    @endif
    <div class="rd-page-heading">
        <div class="min-w-0"><h1>{{ $title }}</h1>@if($description)<p class="rd-page-description">{{ $description }}</p>@endif</div>
        @isset($actions)<div class="rd-page-actions">{{ $actions }}</div>@endisset
    </div>
    @isset($navigation)<div class="rd-page-navigation">{{ $navigation }}</div>@endisset
</header>
