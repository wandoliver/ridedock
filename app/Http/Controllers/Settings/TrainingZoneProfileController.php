<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Training\Services\TrainingZones;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrainingZoneProfileController extends Controller
{
    public function __invoke(Request $request, TrainingZones $zones): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);
        $data = $request->validate([
            'effective_at' => ['required', 'date_format:Y-m-d\\TH:i', 'before_or_equal:now', 'after_or_equal:1900-01-01'],
            'ftp_watts' => ['nullable', 'integer', 'between:50,600'],
            'max_heart_rate' => ['nullable', 'integer', 'between:100,230'],
        ]);
        $zones->record(
            $user,
            CarbonImmutable::parse($data['effective_at'], config('app.timezone')),
            isset($data['ftp_watts']) ? (int) $data['ftp_watts'] : null,
            isset($data['max_heart_rate']) ? (int) $data['max_heart_rate'] : null
        );
        $redirect = to_route('settings.training')->with('status', __('zones.saved'));
        if ($request->input('section') === 'thresholds') {
            $redirect->withFragment('thresholds');
        }
        return $redirect;
    }
}
