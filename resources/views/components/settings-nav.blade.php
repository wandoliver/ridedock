<x-segment aria-label="{{ __('ui.settings.areas') }}">
<a href="{{ route('settings.training') }}" @if(request()->routeIs('settings.training')) aria-current="page" @endif>{{ __('ui.common.training_ai') }}</a>
<a href="{{ route('settings.connections') }}" @if(request()->routeIs('settings.connections')) aria-current="page" @endif>{{ __('ui.common.connections') }}</a>
</x-segment>
