<x-layout :title="__('operations.personal_title')">
<x-page-header :title="__('operations.personal_title')" :description="__('operations.month', ['month' => $usage['month']])" :back="route('dashboard')" :parent="__('ui.common.settings')"><x-slot:navigation><x-settings-nav /></x-slot:navigation></x-page-header>
<x-flash />
<main class="rd-workspace space-y-6">
    <section class="rd-surface p-5">
        <h2>{{ __('operations.quotas') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt>{{ __('operations.activity') }}</dt><dd>{{ $usage['activities'] }} / {{ $activityQuota }}</dd></div>
            <div><dt>{{ __('operations.plans') }}</dt><dd>{{ $usage['plans'] }} / {{ $planQuota }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-ink-60">{{ __('operations.quota_help') }}</p>
    </section>
    <section class="rd-surface p-5">
        <h2>{{ __('operations.costs') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div><dt>{{ __('operations.attempts') }}</dt><dd>{{ $usage['attempts'] }}</dd></div>
            <div><dt>{{ __('operations.tokens') }}</dt><dd>{{ $usage['input_tokens'] }} / {{ $usage['output_tokens'] }}</dd></div>
            <div><dt>{{ __('operations.estimated_cost') }}</dt><dd>{{ Number::format($usage['cost_usd'], precision: 4, locale: app()->getLocale()) }} USD</dd></div>
        </dl>
        <p class="mt-4 text-sm text-ink-60">{{ __('operations.cost_help', ['count' => $usage['unknown_costs']]) }}</p>
        @include('operations.usage-table')
    </section>
</main>
</x-layout>
