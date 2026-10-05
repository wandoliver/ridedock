<nav class="rd-tabs" aria-label="{{ __('ui.settings.areas') }}">
<a href="{{ route('settings.training') }}" @if(request()->routeIs('settings.training')) aria-current="page" @endif>{{ __('ui.common.training_ai') }}</a>
<a href="{{ route('settings.connections') }}" @if(request()->routeIs('settings.connections')) aria-current="page" @endif>{{ __('ui.common.connections') }}</a>
<a href="{{ route('settings.privacy') }}" @if(request()->routeIs('settings.privacy*')) aria-current="page" @endif>{{ __('privacy.title') }}</a>
</nav>
