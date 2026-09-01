<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('ui.auth.register_meta') }}">
    <title>{{ __('ui.auth.create_account') }} · RideDock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-950 antialiased">
    <main class="grid min-h-screen place-items-center px-5 py-12">
        <div class="w-full max-w-md">
            <a href="{{ route('dashboard') }}" class="mx-auto flex w-fit items-center gap-3" aria-label="{{ __('ui.common.back_to_overview') }}">
                <span class="grid size-10 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                    <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><path d="M5 17.5 9.2 6h5.6L19 17.5M7 13h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-xl font-semibold tracking-tight">RideDock</span>
            </a>

            <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-7 shadow-sm shadow-slate-950/[0.03] sm:p-9">
                <p class="text-sm font-medium text-emerald-700">{{ __('ui.auth.data_account') }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('ui.auth.create_account') }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.auth.register_copy') }}</p>

                <form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="text-sm font-medium text-slate-700">{{ __('ui.auth.name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
                        @error('name')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="text-sm font-medium text-slate-700">{{ __('ui.auth.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
                        @error('email')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="text-sm font-medium text-slate-700">{{ __('ui.auth.password') }}</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
                        <p class="mt-2 text-xs text-slate-400">{{ __('ui.auth.password_hint') }}</p>
                        @error('password')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-sm font-medium text-slate-700">{{ __('ui.auth.confirm_password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">{{ __('ui.auth.create_account') }}</button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">{{ __('ui.auth.already_registered') }} <a href="{{ route('login') }}" class="font-medium text-emerald-700 hover:text-emerald-900">{{ __('ui.auth.sign_in') }}</a></p>
            </section>
        </div>
    </main>
</body>
</html>
