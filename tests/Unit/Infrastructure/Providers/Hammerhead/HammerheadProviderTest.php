<?php

namespace Tests\Unit\Infrastructure\Providers\Hammerhead;

use App\Infrastructure\Providers\Hammerhead\HammerheadProvider;
use Tests\TestCase;

class HammerheadProviderTest extends TestCase
{
    public function test_converts_hammerhead_metres_and_milliseconds_to_si_units(): void
    {
        config()->set('activity-providers.hammerhead.distance_multiplier_to_meters', 1);
        config()->set('activity-providers.hammerhead.duration_multiplier_to_seconds', 0.001);

        $activity = (new HammerheadProvider)->mapActivity([
            'id' => '1000.activity.abcd',
            'name' => 'My Epic Ride',
            'createdAt' => '2026-08-30T06:00:00Z',
            'updatedAt' => '2026-08-30T08:00:00Z',
            'duration' => 7200000,
            'distance' => 64200,
            'activityType' => 'RIDE',
            'polyline' => 'encoded',
        ]);

        $this->assertSame(64200.0, $activity->distanceM);
        $this->assertSame(7200, $activity->movingTimeS);
        $this->assertSame(8.916666666666666, $activity->averageSpeedMps);
        $this->assertSame('ride', $activity->type);
    }
}
