<x-layout :title="__('operations.title')">
<x-page-header :title="__('operations.title')" :description="__('operations.month', ['month' => $snapshot['usage']['month']])" :back="route('admin.users.index')" :parent="__('accounts.title')" />
<main class="rd-workspace space-y-6">
    @if($snapshot['usage']['budget_warning'])<div role="alert" class="rd-surface p-4">{{ __('operations.budget_warning') }}</div>@endif
    <section class="rd-surface p-5">
        <h2>{{ __('operations.costs') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div><dt>{{ __('operations.estimated_cost') }}</dt><dd>{{ Number::format($snapshot['usage']['cost_usd'], precision: 4, locale: app()->getLocale()) }} USD</dd></div>
            <div><dt>{{ __('operations.budget') }}</dt><dd>{{ $snapshot['usage']['budget_usd'] > 0 ? Number::format($snapshot['usage']['budget_usd'], precision: 2, locale: app()->getLocale()).' USD' : __('operations.unconfigured') }}</dd></div>
            <div><dt>{{ __('operations.failed_attempts') }}</dt><dd>{{ $snapshot['failed_last_day'] }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-ink-60">{{ __('operations.cost_help', ['count' => $snapshot['usage']['unknown_costs']]) }}</p>
        @include('operations.usage-table')
        <div class="mt-4">{{ $groups->links() }}</div>
    </section>
    <section class="rd-surface p-5">
        <h2>{{ __('operations.capacity') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-4">
            <div><dt>{{ __('operations.pending') }}</dt><dd>{{ $snapshot['pending'] }} / {{ config('operations.ai.pending_limit') }}</dd></div>
            <div><dt>{{ __('operations.running') }}</dt><dd>{{ $snapshot['running'] }} / {{ config('operations.ai.concurrency') }}</dd></div>
            <div><dt>{{ __('operations.oldest') }}</dt><dd>{{ $snapshot['oldest_pending_seconds'] }} s</dd></div>
            <div><dt>{{ __('operations.duration') }}</dt><dd>{{ $snapshot['mean_attempt_seconds'] }} s</dd></div>
        </dl>
        @if($snapshot['oldest_pending_seconds'] > config('operations.stale_minutes') * 60)<p role="alert" class="mt-4">{{ __('operations.queue_stalled') }}</p>@endif
    </section>
    <section class="rd-surface p-5">
        <h2>{{ __('operations.providers') }}</h2>
        <p class="mt-2">{{ __('operations.provider_summary', ['errors' => $snapshot['provider_errors'], 'waiting' => $snapshot['provider_waiting'], 'stale' => $snapshot['provider_stale']]) }}</p>
        @foreach($cooldowns as $provider => $seconds)
            <div class="mt-4 border-t border-line pt-3"><h3>{{ ucfirst($provider) }}</h3><p>{{ __('operations.cooldown', ['seconds' => $seconds]) }}</p><p class="text-sm">{{ __('operations.approval') }}: {{ config('activity-providers.'.$provider.'.hosted_approved') && config('activity-providers.'.$provider.'.terms_reviewed_at') ? __('operations.confirmed') : __('operations.pending_approval') }}</p></div>
        @endforeach
        <ul class="mt-4 space-y-2">
            @foreach($connections as $connection)<li>{{ __('operations.user') }} {{ $connection->user_id }} · {{ ucfirst($connection->provider->value) }} · {{ __('operations.statuses.'.$connection->status->value) }} @if($connection->sync_retry_at)· {{ $connection->sync_retry_at->locale(app()->getLocale())->isoFormat('lll') }}@endif</li>@endforeach
        </ul>
    </section>
    <section class="rd-surface p-5">
        <h2>{{ __('operations.events') }}</h2>
        <ul class="mt-4 space-y-2">@forelse($events as $event)<li>{{ $event->created_at }} · {{ $event->provider ? ucfirst($event->provider).' · ' : '' }}{{ __('operations.codes.'.$event->code) }} @if($event->user_id)· {{ __('operations.user') }} {{ $event->user_id }}@endif</li>@empty<li>{{ __('operations.no_events') }}</li>@endforelse</ul>
    </section>
</main>
</x-layout>
