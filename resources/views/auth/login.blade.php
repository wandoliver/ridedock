<x-layout :title="__('ui.auth.sign_in')">
<x-topbar><x-slot:actions><x-button :href="route('register')" secondary>{{ __('ui.auth.create_account') }}</x-button></x-slot:actions></x-topbar>
<main class="rd-auth"><div><p class="text-sm font-semibold text-plan-text">{{ __('design.auth_eyebrow') }}</p><h2 class="mt-3 max-w-lg text-[44px] font-bold leading-[1.1] tracking-[-.025em]">{{ __('design.auth_title') }}</h2><ol class="mt-8 space-y-5">@foreach(['auth_import','auth_explore','auth_plan'] as $text)<li class="flex items-start gap-3 text-sm leading-relaxed text-ink-70"><span class="grid size-[26px] shrink-0 place-items-center rounded-md bg-surface text-sm font-semibold">{{ $loop->iteration }}</span><span>{{ __('design.'.$text) }}</span></li>@endforeach</ol></div>
            <section class="rd-auth-card">
                <h1 class="font-bold tracking-tight">{{ __('ui.auth.sign_in') }}</h1>

                <div class="mt-5">
                    @include('auth._login-form')
                </div>

                <p class="mt-6 text-center text-sm text-ink-50">{{ __('ui.auth.no_account') }} <a href="{{ route('register') }}" class="font-medium text-plan-text hover:text-plan-text">{{ __('ui.auth.create_account') }}</a></p>
            </section></main><footer class="flex justify-between gap-4 border-t border-line px-7 py-3.5 text-sm text-ink-45"><span>RideDock · Open Source</span><span>{{ __('ui.dashboard.footer_right') }}</span></footer></x-layout>
