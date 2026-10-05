<x-layout :title="__('ui.auth.create_account')">
<x-topbar><x-slot:actions><x-button :href="route('login')" secondary>{{ __('ui.auth.sign_in') }}</x-button></x-slot:actions></x-topbar>
<main class="rd-auth"><div><p class="text-sm font-semibold text-plan-text">{{ __('design.auth_eyebrow') }}</p><h2 class="mt-3 max-w-lg text-[44px] font-bold leading-[1.1] tracking-[-.025em]">{{ __('design.auth_title') }}</h2><ol class="mt-8 space-y-5">@foreach(['auth_import','auth_explore','auth_plan'] as $text)<li class="flex items-start gap-3 text-sm leading-relaxed text-ink-70"><span class="grid size-[26px] shrink-0 place-items-center rounded-md bg-surface text-sm font-semibold">{{ $loop->iteration }}</span><span>{{ __('design.'.$text) }}</span></li>@endforeach</ol></div>
            <section class="rd-auth-card">
                <h1 class="font-bold tracking-tight">{{ __('ui.auth.create_account') }}</h1>

                <form method="POST" action="{{ route('register.store') }}" class="mt-5 space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
                        @error('name')<p class="mt-2 text-sm text-ink-60">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
                        @error('email')<p class="mt-2 text-sm text-ink-60">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.password') }}</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
                        <p class="mt-2 text-sm text-ink-45">{{ __('ui.auth.password_hint') }}</p>
                        @error('password')<p class="mt-2 text-sm text-ink-60">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.confirm_password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
                    </div>
                    <button type="submit" class="rd-button w-full">{{ __('ui.auth.create_account') }}</button>
                </form>

                <p class="mt-6 text-center text-sm text-ink-50">{{ __('ui.auth.already_registered') }} <a href="{{ route('login') }}" class="font-medium text-plan-text hover:text-plan-text">{{ __('ui.auth.sign_in') }}</a></p>
            </section></main><footer class="flex justify-between gap-4 border-t border-line px-7 py-3.5 text-sm text-ink-45"><span>RideDock · Open Source</span><span>{{ __('ui.dashboard.footer_right') }}</span></footer></x-layout>
