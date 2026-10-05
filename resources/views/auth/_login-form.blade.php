<form method="POST" action="{{ route('login.store') }}" class="space-y-5">
    @csrf
    <div>
        <label for="email" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
        @error('email')<p class="mt-2 text-sm text-ink-60">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="text-sm font-semibold text-ink-50">{{ __('ui.auth.password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-border-input bg-white px-3 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-3 focus:ring-emerald-100">
    </div>
    <label class="flex items-center gap-2 text-sm text-ink-60">
        <input name="remember" type="checkbox" value="1" class="size-4 rounded border-border-input text-plan-text focus:ring-emerald-500">
        {{ __('ui.auth.remember') }}
    </label>
    <button type="submit" class="rd-button w-full">{{ __('ui.auth.sign_in') }}</button>
</form>
