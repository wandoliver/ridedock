<x-layout :title="__('accounts.activate')">
<x-topbar><x-slot:actions><x-button :href="route('login')" secondary>{{ __('ui.auth.sign_in') }}</x-button></x-slot:actions></x-topbar>
<main class="mx-auto max-w-lg px-5 py-12">
    <section class="rd-auth-card">
        <h1>{{ __('accounts.activate') }}</h1>
        <p class="mt-3 text-sm text-ink-60">{{ __('accounts.activate_hint') }}</p>
        <p class="mt-4 text-sm"><strong>{{ $invitation->user->name }}</strong><br>{{ $invitation->email }}</p>
        <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="mt-5 space-y-4">
            @csrf
            <label class="block text-sm font-semibold">{{ __('ui.auth.password') }}
                <input name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2">
            </label>
            <p class="text-sm text-ink-60">{{ __('ui.auth.password_hint') }}</p>
            @error('password')<p class="text-sm text-ink-60" role="alert">{{ $message }}</p>@enderror
            <label class="block text-sm font-semibold">{{ __('ui.auth.confirm_password') }}
                <input name="password_confirmation" type="password" required minlength="12" maxlength="255" autocomplete="new-password" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2">
            </label>
            <x-button class="w-full">{{ __('accounts.activate') }}</x-button>
        </form>
    </section>
</main>
</x-layout>
