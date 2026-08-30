<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Training\Contracts\TrainingAdvisor;
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

        $user->athleteProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        return to_route('settings.training')->with('status', 'Trainingsprofil gespeichert.');
    }
}
