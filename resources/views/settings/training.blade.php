<x-layout :title="__('ui.settings.training_title')">
<x-topbar>
<x-slot:context><span class="text-[13px] font-semibold">{{ __('ui.common.settings') }}</span></x-slot:context>
<x-slot:center><x-settings-nav /></x-slot:center>
<x-slot:actions><x-button :href="route('settings.training')" secondary>{{ __('design.discard') }}</x-button><x-button form="training-profile">{{ __('ui.settings.save_profile') }}</x-button></x-slot:actions>
</x-topbar>
<x-flash />
<div class="rd-shell">
<main class="rd-workspace"><h1 class="sr-only">{{ __('ui.settings.training_profile') }}</h1>
@php
$days = ['monday'=>__('ui.settings.monday'),'tuesday'=>__('ui.settings.tuesday'),'wednesday'=>__('ui.settings.wednesday'),'thursday'=>__('ui.settings.thursday'),'friday'=>__('ui.settings.friday'),'saturday'=>__('ui.settings.saturday'),'sunday'=>__('ui.settings.sunday')];
$selectedDays = (array) old('available_days', $profile?->available_days ?? []);
@endphp
<form id="training-profile" method="POST" action="{{ route('settings.training.update') }}" class="space-y-2">
@csrf @method('PATCH')
<section class="rd-form-group"><h2>{{ __('design.goal') }}</h2>
<label class="block">
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.training_goal') }}</span>
                        <textarea name="goal" rows="2" maxlength="500" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15" placeholder="{{ __('ui.settings.training_goal_placeholder') }}">{{ old('goal', $profile?->goal) }}</textarea>
                    </label><div class="grid gap-3 sm:grid-cols-3"><label>
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.goal_date') }}</span>
                        <input type="date" name="goal_date" value="{{ old('goal_date', $profile?->goal_date?->toDateString()) }}" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                    </label><label>
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.experience') }}</span>
                        <select name="experience_level" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <option value="">{{ __('ui.settings.not_specified') }}</option>
                            @foreach (['beginner' => __('ui.settings.beginner'), 'intermediate' => __('ui.settings.intermediate'), 'advanced' => __('ui.settings.advanced')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('experience_level', $profile?->experience_level) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>                    <label class="block">
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.language.title') }}</span>
                        <select name="locale" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <option value="en" @selected(old('locale', auth()->user()->locale) === 'en')>{{ __('ui.language.english') }}</option>
                            <option value="de" @selected(old('locale', auth()->user()->locale) === 'de')>{{ __('ui.language.german') }}</option>
                        </select>
                    </label></div></section>
<section class="rd-form-group"><h2>{{ __('design.thresholds') }} <span class="font-normal text-ink-45">{{ __('ui.common.optional') }}</span></h2><div class="grid gap-3 sm:grid-cols-3"><label>
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.hours_week') }} <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <input type="number" name="weekly_hours" min="1" max="40" step="0.5" value="{{ old('weekly_hours', $profile?->weekly_hours) }}" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                        <span class="mt-2 block text-xs text-ink-50">{{ __('ui.settings.hours_hint') }}</span>
                    </label><label>
                        <span class="text-xs font-semibold text-ink-50">FTP <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="ftp_watts" min="50" max="600" value="{{ old('ftp_watts', $profile?->ftp_watts) }}" class="block w-full rounded-lg border border-border-input bg-white px-3.5 py-3 pr-12 text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-45">W</span>
                        </div>
                        <span class="mt-2 block text-xs text-ink-50">{{ __('ui.settings.ftp_hint') }}</span>
                    </label><label>
                        <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.max_hr') }} <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="max_heart_rate" min="100" max="230" value="{{ old('max_heart_rate', $profile?->max_heart_rate) }}" class="block w-full rounded-lg border border-border-input bg-white px-3.5 py-3 pr-16 text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-45">bpm</span>
                        </div>
                        <span class="mt-2 block text-xs text-ink-50">{{ __('ui.settings.hr_hint') }}</span>
                    </label></div></section>
<section class="rd-form-group"><h2>{{ __('design.availability') }}</h2>                <fieldset>
                    <legend class="text-xs font-semibold text-ink-50">{{ __('ui.settings.available_days') }}</legend>
                    <div class="mt-3 grid grid-cols-7 gap-1.5">
                        @foreach ($days as $value => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="available_days[]" value="{{ $value }}" class="peer sr-only" @checked(in_array($value, $selectedDays, true))>
                                <span class="block rounded-lg border border-line px-1 py-2.5 text-center text-[13px] text-ink-60 transition peer-focus-visible:outline-2 peer-focus-visible:outline-plan peer-checked:border-plan peer-checked:bg-plan-soft peer-checked:text-plan-text"><abbr class="no-underline" title="{{ $label }}">{{ mb_substr($label, 0, 2) }}</abbr></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="block">
                    <span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.constraints') }}</span>
                    <textarea name="constraints" rows="2" maxlength="2000" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15" placeholder="{{ __('ui.settings.constraints_placeholder') }}">{{ old('constraints', $profile?->constraints) }}</textarea>
                    <span class="mt-2 block text-xs leading-5 text-ink-50">{{ __('ui.settings.constraints_hint') }}</span>
                </label>

</section>
</form></main>
<aside class="rd-panel">
<div class="flex items-center justify-between gap-3"><span class="text-xs font-semibold text-ink-50">{{ __('ui.settings.ai_status') }}</span><span class="rounded-md px-2 py-1 text-[11px] {{ $aiConfigured ? 'bg-plan-soft text-plan-text' : 'bg-canvas text-ink-50' }}">{{ $aiConfigured ? __('ui.settings.configured') : __('ui.settings.api_missing') }}</span></div>
<h1 class="mt-3 text-[17px] font-bold">{{ __('ui.settings.ride_analysis') }}</h1><p class="mt-2 text-[13px] leading-relaxed text-ink-70">{{ __('ui.settings.privacy_copy') }}</p>
@unless($aiConfigured)<x-info-box class="mt-3">{!! __('ui.settings.api_hint') !!}</x-info-box>@endunless
<section class="rd-panel-section"><x-panel-label>{{ __('design.values_usage') }}</x-panel-label>
@foreach(['goal_usage','hours_usage','zones_usage','days_usage'] as $explanation)<x-info-box class="mt-2">{{ __('design.'.$explanation) }}</x-info-box>@endforeach
</section><p class="mt-5 text-[11px] leading-relaxed text-ink-45">{{ __('design.changes_next') }}</p>
</aside></div></x-layout>
