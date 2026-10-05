<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Support\ActivityNavigation;
use Illuminate\Http\RedirectResponse;

class ActivityFeedbackController extends Controller
{
    public function __invoke(UpdateActivityRequest $request, string $activity): RedirectResponse
    {
        $model = Activity::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($activity);

        $feedback = $request->safe()->only(['rpe', 'notes']);
        $model->update($feedback);
        $status = count($feedback) === 1 ? __('ux.'.(array_key_exists('rpe', $feedback) ? 'rating_saved' : 'notes_saved')) : __('ui.activity.feedback_saved');

        return redirect(ActivityNavigation::detailUrl($request, $model->id))
            ->with('status', $status);
    }
}
