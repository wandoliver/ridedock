<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Services\TrainingZones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAthleteProfileRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrainingSettingsController extends Controller
{
    public function show(Request $request, TrainingAdvisor $trainingAdvisor): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $user->loadMissing('athleteProfile');

        return view('settings.training', [
            'zoneHistory' => $user->trainingZoneProfiles()->orderByDesc('effective_at')->orderByDesc('id')->limit(20)->get(),
            'zoneContext' => app(TrainingZones::class)->context(app(TrainingZones::class)->at($user, CarbonImmutable::now())),
            'profile' => $user->athleteProfile,
            'aiConfigured' => $trainingAdvisor->isConfigured(),
        ]);
    }

    public function update(UpdateAthleteProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        DB::transaction(function () use ($user, $request): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $validated = $request->validated();
            $user->update(['locale' => $validated['locale']]);
            unset($validated['locale']);
            $previous = $user->athleteProfile()->first();
            $profile = $user->athleteProfile()->updateOrCreate(['user_id' => $user->id], $validated);
            if ($previous?->ftp_watts !== $profile->ftp_watts || $previous?->max_heart_rate !== $profile->max_heart_rate
                || ! $user->trainingZoneProfiles()->exists()) {
                app(TrainingZones::class)->record($user, CarbonImmutable::now(), $profile->ftp_watts, $profile->max_heart_rate);
            }
        });

        app()->setLocale($user->locale);

        return to_route('settings.training')->with('status', __('ui.settings.profile_saved'));
    }
}
