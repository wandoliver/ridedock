@props(['name'])
<span {{ $attributes->class(['rd-icon']) }} style="--icon: url('{{ asset('icons/'.$name.'.svg') }}')" aria-hidden="true"></span>
