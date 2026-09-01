<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;

class ActivityFeedbackController extends Controller
{
    public function __invoke(UpdateActivityRequest $request, string $activity): RedirectResponse
    {
        $model = Activity::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($activity);

        $model->update($request->safe()->only(['rpe', 'notes']));

        return redirect()
            ->route('activities.show', $model)
            ->with('status', __('ui.activity.feedback_saved'));
    }
}
