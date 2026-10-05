<x-layout :title="__('ui.settings.training_title')">
<x-page-header :title="__('ui.settings.training_profile')" :description="__('ux.settings_description')" :back="route('dashboard')" :parent="__('ui.common.settings')">
<x-slot:actions><div class="flex flex-wrap gap-3" data-page-actions="profile availability thresholds"><x-button :href="route('settings.training')" secondary>{{ __('design.discard') }}</x-button><x-button form="training-profile">{{ __('ui.settings.save_profile') }}</x-button></div></x-slot:actions>
<x-slot:navigation><x-settings-nav /></x-slot:navigation>
</x-page-header>
<x-flash />
<main class="rd-content">
<x-tabs :items="['profile' => __('ux.profile'), 'availability' => __('ux.availability'), 'thresholds' => __('ux.thresholds'), 'ai' => __('ux.ai')]" :label="__('ui.settings.training_profile')" :default="$errors->hasAny(['weekly_hours','available_days','constraints']) ? 'availability' : ($errors->hasAny(['ftp_watts','max_heart_rate','effective_at']) ? 'thresholds' : 'profile')" />
@php
$days = ['monday'=>__('ui.settings.monday'),'tuesday'=>__('ui.settings.tuesday'),'wednesday'=>__('ui.settings.wednesday'),'thursday'=>__('ui.settings.thursday'),'friday'=>__('ui.settings.friday'),'saturday'=>__('ui.settings.saturday'),'sunday'=>__('ui.settings.sunday')];
$selectedDays = (array) old('available_days', $profile?->available_days ?? []);
@endphp
<form id="training-profile" method="POST" action="{{ route('settings.training.update') }}" class="rd-settings-form">
@csrf @method('PATCH')<input type="hidden" name="section" value="profile" data-page-section>
<section id="profile" data-page-panel="profile" class="rd-form-group"><h2>{{ __('design.goal') }}</h2>
<label class="block">
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.training_goal') }}</span>
                        <textarea name="goal" rows="2" maxlength="500" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15" placeholder="{{ __('ui.settings.training_goal_placeholder') }}">{{ old('goal', $profile?->goal) }}</textarea>
                    </label><div class="grid gap-5 sm:grid-cols-2"><label>
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.goal_date') }}</span>
                        <input type="date" name="goal_date" value="{{ old('goal_date', $profile?->goal_date?->toDateString()) }}" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                    </label><label>
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.experience') }}</span>
                        <select name="experience_level" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <option value="">{{ __('ui.settings.not_specified') }}</option>
                            @foreach (['beginner' => __('ui.settings.beginner'), 'intermediate' => __('ui.settings.intermediate'), 'advanced' => __('ui.settings.advanced')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('experience_level', $profile?->experience_level) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>                    </div><div class="mt-5 max-w-sm"><h3>{{ __('ux.language') }}</h3><label class="block">
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.language.title') }}</span>
                        <select name="locale" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <option value="en" @selected(old('locale', auth()->user()->locale) === 'en')>{{ __('ui.language.english') }}</option>
                            <option value="de" @selected(old('locale', auth()->user()->locale) === 'de')>{{ __('ui.language.german') }}</option>
                        </select>
                    </label></div></section>
<section id="thresholds" data-page-panel="thresholds" class="rd-form-group"><h2>{{ __('design.thresholds') }} <span class="font-normal text-ink-45">{{ __('ui.common.optional') }}</span></h2><div class="grid gap-5 sm:grid-cols-2"><label>
                        <span class="text-sm font-semibold text-ink-50">FTP <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="ftp_watts" min="50" max="600" value="{{ old('ftp_watts', $profile?->ftp_watts) }}" class="block w-full rounded-lg border border-border-input bg-white px-3.5 py-3 pr-12 text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-45">W</span>
                        </div>
                        <span class="mt-2 block text-sm text-ink-50">{{ __('ui.settings.ftp_hint') }}</span>
                    </label><label>
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.max_hr') }} <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <div class="relative mt-2">
                            <input type="number" name="max_heart_rate" min="100" max="230" value="{{ old('max_heart_rate', $profile?->max_heart_rate) }}" class="block w-full rounded-lg border border-border-input bg-white px-3.5 py-3 pr-16 text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-45">bpm</span>
                        </div>
                        <span class="mt-2 block text-sm text-ink-50">{{ __('ui.settings.hr_hint') }}</span>
                    </label></div></section>
<section id="availability" data-page-panel="availability" class="rd-form-group"><h2>{{ __('design.availability') }}</h2><div class="max-w-sm"><label>
                        <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.hours_week') }} <span class="font-normal text-ink-45">({{ __('ui.common.optional') }})</span></span>
                        <input type="number" name="weekly_hours" min="1" max="40" step="0.5" value="{{ old('weekly_hours', $profile?->weekly_hours) }}" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15">
                        <span class="mt-2 block text-sm text-ink-50">{{ __('ui.settings.hours_hint') }}</span>
                    </label></div>                <fieldset>
                    <legend class="text-sm font-semibold text-ink-50">{{ __('ui.settings.available_days') }}</legend>
                    <div class="rd-days-select mt-3 grid grid-cols-7 gap-1.5">
                        @foreach ($days as $value => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="available_days[]" value="{{ $value }}" class="peer sr-only" @checked(in_array($value, $selectedDays, true))>
                                <span class="block rounded-lg border border-line px-1 py-2.5 text-center text-sm text-ink-60 transition peer-focus-visible:outline-2 peer-focus-visible:outline-plan peer-checked:border-plan peer-checked:bg-plan-soft peer-checked:text-plan-text"><abbr class="no-underline" title="{{ $label }}">{{ mb_substr($label, 0, 2) }}</abbr></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="block">
                    <span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.constraints') }}</span>
                    <textarea name="constraints" rows="2" maxlength="2000" class="mt-2 block w-full rounded-lg border border-border-input bg-white px-3.5 py-[9px] text-sm outline-none transition focus:border-plan-dash focus:ring-2 focus:ring-emerald-600/15" placeholder="{{ __('ui.settings.constraints_placeholder') }}">{{ old('constraints', $profile?->constraints) }}</textarea>
                    <span class="mt-2 block text-sm leading-5 text-ink-50">{{ __('ui.settings.constraints_hint') }}</span>
                </label>

</section>
</form>
<section data-page-panel="thresholds">@include('settings._zones')</section>
<section id="ai" data-page-panel="ai" class="rd-form-group">
<div class="flex items-center justify-between gap-3"><span class="text-sm font-semibold text-ink-50">{{ __('ui.settings.ai_status') }}</span><span class="rounded-md px-2 py-1 text-sm {{ $aiConfigured ? 'bg-plan-soft text-plan-text' : 'bg-canvas text-ink-50' }}">{{ $aiConfigured ? __('ui.settings.configured') : __('ui.settings.api_missing') }}</span></div>
<h2 class="mt-3">{{ __('ui.settings.ride_analysis') }}</h2><p class="mt-2 text-sm leading-relaxed text-ink-70">{{ __('ui.settings.privacy_copy') }}</p>
@unless($aiConfigured)<x-info-box class="mt-3">{!! __('ui.settings.api_hint') !!}</x-info-box>@endunless
<section class="rd-panel-section"><x-panel-label>{{ __('design.values_usage') }}</x-panel-label>
@foreach(['goal_usage','hours_usage','zones_usage','days_usage'] as $explanation)<x-info-box class="mt-2">{{ __('design.'.$explanation) }}</x-info-box>@endforeach
</section><p class="mt-5 text-sm leading-relaxed text-ink-45">{{ __('design.changes_next') }}</p>
</section></main></x-layout>
