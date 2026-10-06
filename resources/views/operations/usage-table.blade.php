<div class="mt-5 overflow-x-auto">
<table class="w-full text-left text-sm">
    <caption class="sr-only">{{ __('operations.usage_breakdown') }}</caption>
    <thead><tr>@isset($snapshot)<th scope="col" class="p-2">{{ __('operations.user') }}</th>@endisset<th scope="col" class="p-2">{{ __('operations.feature') }}</th><th scope="col" class="p-2">{{ __('operations.model') }}</th><th scope="col" class="p-2">{{ __('operations.attempts') }}</th><th scope="col" class="p-2">{{ __('operations.tokens') }}</th><th scope="col" class="p-2">{{ __('operations.estimated_cost') }}</th><th scope="col" class="p-2">{{ __('operations.unknown_costs') }}</th></tr></thead>
    <tbody>
    @forelse($groups as $group)
    <tr class="border-t border-line">@isset($snapshot)<td class="p-2">{{ $group->user_id }}</td>@endisset<td class="p-2">{{ __('operations.features.'.$group->feature) }}</td><td class="p-2">{{ $group->model ?? '–' }}</td><td class="p-2">{{ $group->attempts }}</td><td class="p-2">{{ $group->input_tokens ?? 0 }} / {{ $group->output_tokens ?? 0 }}</td><td class="p-2">{{ $group->cost_microusd === null ? '–' : Number::format($group->cost_microusd / 1000000, precision: 4, locale: app()->getLocale()).' USD' }}</td><td class="p-2">{{ $group->unknown_costs }}</td></tr>
    @empty<tr><td colspan="7" class="p-2">{{ __('operations.no_usage') }}</td></tr>@endforelse
    </tbody>
</table>
</div>
