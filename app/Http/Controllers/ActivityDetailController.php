<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityDetailController extends Controller
{
    public function __invoke(Request $request, string $activity): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $model = Activity::query()
            ->whereBelongsTo($user)
            ->with([
                'sources.connection',
                'streams' => fn ($query) => $query->orderBy('type'),
                'events' => fn ($query) => $query
                    ->where('type', 'shift')
                    ->orderBy('elapsed_seconds'),
            ])
            ->findOrFail($activity);
        $activityStreams = [];

        foreach ($model->streams as $stream) {
            $activityStreams[$stream->type] = array_values($stream->data);
        }

        return view('activities.show', [
            'activity' => $model,
            'activityStreams' => $activityStreams,
        ]);
    }
}
