@props(['href' => null, 'secondary' => false])
@if ($href)
<a href="{{ $href }}" {{ $attributes->class(['rd-button', 'rd-button-secondary' => $secondary]) }}>{{ $slot }}</a>
@else
<button {{ $attributes->merge(['type' => 'submit'])->class(['rd-button', 'rd-button-secondary' => $secondary]) }}>{{ $slot }}</button>
@endif
