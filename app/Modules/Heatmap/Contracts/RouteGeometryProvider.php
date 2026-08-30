<?php

namespace App\Modules\Heatmap\Contracts;

use App\Models\User;
use Carbon\CarbonImmutable;

interface RouteGeometryProvider
{
    /**
     * Return privacy-trimmed, provider-neutral encoded polylines for a user's rides.
     *
     * @return iterable<array{activity_id: string, started_at: string, point_count: int, polyline: string}>
     */
    public function routes(
        User $user,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): iterable;
}
