<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\ActivityNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityComparisonController extends Controller
{
    public function __invoke(Request $request, string $activity): RedirectResponse
    {
        $model = Activity::query()->where('user_id', $request->user()->id)->findOrFail($activity);
        $data = $request->validate(['comparison_group' => ['nullable', 'string', 'max:80']]);
        $model->update(['comparison_group' => $data['comparison_group'] ?? null]);
        return redirect(ActivityNavigation::detailUrl($request, $model->id))->with('status', __('efficiency.saved'));
    }
}
