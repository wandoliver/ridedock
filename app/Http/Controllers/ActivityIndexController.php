<?php

namespace App\Http\Controllers;

use App\Domain\Activities\Enums\ActivityPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $now = CarbonImmutable::now();
        $period = ActivityPeriod::resolve($request->query('period'));
        $range = $period->range($now);
        $activities = $user->activities()
            ->when($range['from'], fn ($query, $from) => $query->where('started_at', '>=', $from))
            ->when($range['to'], fn ($query, $to) => $query->where('started_at', '<=', $to))
            ->latest('started_at')
            ->orderByDesc('id')
            ->paginate(25, [
                'id', 'user_id', 'name', 'type', 'started_at', 'distance_m',
                'moving_time_s', 'elevation_gain_m', 'average_power', 'rpe',
            ])
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'period' => $period,
            'periods' => ActivityPeriod::cases(),
            'periodLabel' => $period->label($now),
            'now' => $now,
        ]);
    }
}
