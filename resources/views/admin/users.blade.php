<x-layout :title="__('accounts.title')">
<x-page-header :title="__('accounts.title')" :description="__('accounts.description')" :back="route('dashboard')" />
<x-flash />
<main class="rd-content space-y-8">
    @if($activationUrl)
    <section class="rounded-lg border border-plan-dash bg-white p-5" aria-labelledby="activation-link-heading">
        <h2 id="activation-link-heading">{{ __('accounts.link_title') }}</h2>
        <p class="mt-2 text-sm text-ink-60">{{ __('accounts.link_hint') }}</p>
        <label class="mt-4 block text-sm font-semibold">{{ __('accounts.link_label') }}
            <input readonly value="{{ $activationUrl }}" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2" autocomplete="off">
        </label>
        <x-button :href="route('admin.users.index')" secondary class="mt-4">{{ __('accounts.link_done') }}</x-button>
    </section>
    @endif
    <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="create-user-heading">
        <h2 id="create-user-heading">{{ __('accounts.create') }}</h2>
        <p class="mt-2 text-sm text-ink-60">{{ __('accounts.create_hint') }}</p>
        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-5 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('ui.auth.name') }}<input name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="off" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2"></label>
                <label class="text-sm font-semibold">{{ __('ui.auth.email') }}<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off" class="mt-2 block w-full rounded-lg border border-border-input px-3 py-2"></label>
                <label class="text-sm font-semibold">{{ __('accounts.language') }}
                    <select name="locale" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3 py-2">
                        <option value="de" @selected(old('locale', app()->getLocale()) === 'de')>Deutsch</option>
                        <option value="en" @selected(old('locale', app()->getLocale()) === 'en')>English</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">{{ __('accounts.delivery') }}
                    <select name="delivery" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3 py-2">
                        <option value="manual">{{ __('accounts.manual') }}</option>
                        @if($mailAvailable)<option value="email" @selected(old('delivery') === 'email')>{{ __('accounts.email') }}</option>@endif
                    </select>
                </label>
            </div>
            @if(!$mailAvailable)<p class="text-sm text-ink-60">{{ __('accounts.mail_unavailable') }}</p>@endif
            <x-button>{{ __('accounts.create') }}</x-button>
        </form>
    </section>
    <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="users-heading">
        <h2 id="users-heading">{{ __('accounts.users') }}</h2>
        <ul class="mt-4 divide-y divide-line">
            @foreach($users as $user)
            <li class="flex flex-wrap items-start justify-between gap-4 py-4">
                <div class="min-w-0 break-words">
                    <p class="text-sm font-semibold">{{ $user->name }}</p><p class="mt-1 text-sm text-ink-60">{{ $user->email }}</p>
                    <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <div><dt class="inline text-ink-60">{{ __('accounts.role') }}:</dt> <dd class="inline">{{ __('accounts.'.$user->role->value) }}</dd></div>
                        <div><dt class="inline text-ink-60">{{ __('accounts.status') }}:</dt> <dd class="inline">{{ __('accounts.'.$user->status->value) }}</dd></div>
                    </dl>
                    @if($user->status === \App\Domain\Accounts\Enums\UserStatus::Pending && $user->latestInvitation)
                        @php($invitation = $user->latestInvitation)
                        <p class="mt-2 text-sm text-ink-60">{{ $invitation->revoked_at ? __('accounts.invitation_revoked') : ($invitation->expires_at->isPast() ? __('accounts.invitation_expired') : __('accounts.invitation_pending', ['date' => $invitation->expires_at->format('d.m.Y H:i')])) }}</p>
                    @endif
                </div>
                @if($user->status === \App\Domain\Accounts\Enums\UserStatus::Pending)
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('accounts.actions') }}: {{ $user->email }}">
                    <form method="POST" action="{{ route('admin.users.invitation.reissue', $user) }}">@csrf<input type="hidden" name="delivery" value="manual"><x-button secondary>{{ __('accounts.renew_manual') }}</x-button></form>
                    @if($mailAvailable)<form method="POST" action="{{ route('admin.users.invitation.reissue', $user) }}">@csrf<input type="hidden" name="delivery" value="email"><x-button secondary>{{ __('accounts.renew_email') }}</x-button></form>@endif
                    @if($user->latestInvitation?->isUsable())<form method="POST" action="{{ route('admin.users.invitation.revoke', $user) }}">@csrf @method('DELETE')<x-button secondary>{{ __('accounts.revoke') }}</x-button></form>@endif
                </div>
                @endif
            </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $users->links() }}</div>
    </section>
</main>
</x-layout>
