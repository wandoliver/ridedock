@php
    $items = [
        ['dashboard', 'ui.common.overview', 'home', request()->routeIs('dashboard')],
        ['activities.index', 'ui.activities.title', 'activity', request()->routeIs('activities.*')],
        ['analytics', 'ux.analytics', 'chart-bar', request()->routeIs('analytics')],
        ['coach.index', 'ux.coach', 'compass', request()->routeIs('coach.*')],
        ['settings.training', 'ui.common.settings', 'settings', request()->routeIs('settings.*')],
    ];
@endphp
<aside class="rd-sidebar">
    <x-logo />
    <button type="button" class="rd-button rd-button-secondary rd-menu-toggle" data-navigation-toggle aria-expanded="false" aria-controls="site-navigation"><x-icon name="menu-2" />{{ __('ux.menu') }}</button>
    <div id="site-navigation" class="rd-navigation-body">
    <nav class="rd-main-navigation" aria-label="{{ __('ux.navigation') }}">
        @foreach($items as [$route, $label, $icon, $active])
            <a href="{{ route($route) }}" @if($active) aria-current="page" @endif><x-icon :name="$icon" /><span>{{ __($label) }}</span></a>
        @endforeach
        @can('manage-users')
            <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif><x-icon name="settings" /><span>{{ __('accounts.title') }}</span></a>
            <a href="{{ route('admin.operations') }}" @if(request()->routeIs('admin.operations')) aria-current="page" @endif><x-icon name="chart-bar" /><span>{{ __('operations.title') }}</span></a>
        @endcan
    </nav>
    <div class="rd-sidebar-footer"><p>{{ auth()->user()->name }}</p><form method="POST" action="{{ route('logout') }}">@csrf<x-button secondary type="submit"><x-icon name="logout" />{{ __('ui.dashboard.sign_out') }}</x-button></form></div>
    </div>
</aside>
