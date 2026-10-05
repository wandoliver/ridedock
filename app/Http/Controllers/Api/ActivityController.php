<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityIndexRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function index(ActivityIndexRequest $request): AnonymousResourceCollection
    {
        $activities = Activity::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('sources', fn ($query) => $query->whereNull('deleted_at_provider'))
            ->with('sources.connection')
            ->when($request->boolean('include_route'), fn ($query) => $query->with(['streams' => fn ($query) => $query->where('type', 'latlng'), 'user.privacyZones']))
            ->when($request->date('from'), fn ($query, $from) => $query->where('started_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($query, $to) => $query->where('started_at', '<=', $to->endOfDay()))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return ActivityResource::collection($activities);
    }

    public function show(ActivityIndexRequest $request, string $activity): ActivityResource
    {
        $model = Activity::query()
            ->where('user_id', $request->user()->id)
            ->with(['sources.connection', 'streams', 'events'])
            ->findOrFail($activity);

        return new ActivityResource($model);
    }

    public function update(UpdateActivityRequest $request, string $activity): ActivityResource
    {
        $model = Activity::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($activity);

        $model->update($request->safe()->only(['rpe', 'notes']));

        return new ActivityResource($model->fresh('sources.connection'));
    }
}
