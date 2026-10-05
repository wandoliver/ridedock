<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Activities\Services\DataRetention;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PrivacySettingsController extends Controller
{
    public function show(Request $request, DataRetention $retention): View
    {
        $mapRoutes = [];
        foreach (app(\App\Modules\Heatmap\Contracts\RouteGeometryProvider::class)->routes($request->user()) as $route) {
            $mapRoutes[] = $route;
            if (count($mapRoutes) >= 100) {
                break;
            }
        }
        return view('settings.privacy', [
            'user' => $request->user(),
            'zones' => $request->user()->privacyZones()->orderBy('id')->get(),
            'preview' => $retention->preview($request->user()),
            'cleanupSnapshot' => $retention->snapshot($request->user()),
            'mapRoutes' => $mapRoutes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'activity_retention_days' => ['present', 'nullable', 'integer', Rule::in([30, 90, 180, 365, 730])],
            'source_retention_days' => ['present', 'nullable', 'integer', Rule::in([30, 90, 180, 365, 730])],
        ]);
        DB::transaction(function () use ($request, $values): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $user->forceFill($values)->save();
        });

        return to_route('settings.privacy')->with('status', __('privacy.saved'));
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:100,5000'],
        ]);
        DB::transaction(function () use ($request, $values): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->privacyZones()->count() >= 20, 422, __('privacy.zone_limit'));
            $user->privacyZones()->create($values);
        });

        $request->user()->unsetRelation('privacyZones');
        return to_route('settings.privacy')->with('status', __('privacy.zone_saved'));
    }

    public function destroyZone(Request $request, string $zone): RedirectResponse
    {
        $request->user()->privacyZones()->findOrFail($zone)->delete();
        $request->user()->unsetRelation('privacyZones');

        return to_route('settings.privacy')->with('status', __('privacy.zone_deleted'));
    }

    public function purge(Request $request, DataRetention $retention): RedirectResponse
    {
        $values = $request->validate([
            'confirm_cleanup' => ['required', 'accepted'],
            'cleanup_snapshot' => ['required', 'string', 'size:64'],
        ]);
        $retention->purge($request->user(), $values['cleanup_snapshot']);

        return to_route('settings.privacy')->with('status', __('privacy.cleaned'));
    }
}
