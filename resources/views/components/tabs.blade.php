@props(['items', 'label', 'default' => null])
<nav {{ $attributes->class(['rd-tabs']) }} aria-label="{{ $label }}" data-page-tabs @if($errors->any()) data-tab-errors @endif data-default-tab="{{ $default ?? array_key_first($items) }}">
    @foreach($items as $key => $text)
        <a href="#{{ $key }}" data-page-tab="{{ $key }}" @if($key === ($default ?? array_key_first($items))) aria-current="page" @endif>{{ $text }}</a>
    @endforeach
</nav>
