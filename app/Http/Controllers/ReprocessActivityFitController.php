<?php

namespace App\Http\Controllers;

use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReprocessActivityFitController extends Controller
{
    public function __invoke(Request $request, string $activity, ActivitySourceEnricher $enricher): RedirectResponse
    {
        $model = Activity::query()->where('user_id', $request->user()->id)->findOrFail($activity);
        $source = $model->sources()->whereNull('purged_at')->whereNotNull('source_file_path')
            ->whereNotNull('source_file_checksum')->whereHas('connection', fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByDesc('imported_at')->orderByDesc('id')->first();
        $redirect = to_route('activities.show', ['activity' => $model->id, 'return_to' => \App\Support\ActivityNavigation::returnTo($request)])->withFragment('measurements');
        if (! $source) {
            return $redirect->withErrors(['fit' => __('shifts.no_file')]);
        }
        try {
            $enricher->enrich($source);
        } catch (\Throwable) {
            return $redirect->withErrors(['fit' => __('shifts.failed')]);
        }

        return $redirect->with('status', __('shifts.updated'));
    }
}
