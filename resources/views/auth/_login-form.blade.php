<form method="POST" action="{{ route('login.store') }}" class="space-y-5">
    @csrf
    <div>
        <label for="email" class="text-sm font-medium text-slate-700">{{ __('ui.auth.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
        @error('email')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="text-sm font-medium text-slate-700">{{ __('ui.auth.password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-3 focus:ring-emerald-100">
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input name="remember" type="checkbox" value="1" class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
        {{ __('ui.auth.remember') }}
    </label>
    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">{{ __('ui.auth.sign_in') }}</button>
</form>
