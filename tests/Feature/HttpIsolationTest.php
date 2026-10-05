<?php

namespace Tests\Feature;

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpIsolationTest extends TestCase
{
    public function test_providers_are_disabled_without_inherited_oauth_credentials(): void
    {
        foreach (['hammerhead', 'wahoo'] as $provider) {
            $this->assertFalse(config("activity-providers.$provider.enabled"));
            foreach (['client_id', 'client_secret', 'redirect_uri'] as $field) {
                $this->assertEmpty(config("activity-providers.$provider.$field"));
            }
        }
    }

    public function test_unmocked_http_requests_are_blocked_by_default(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://unmocked.example.test/activities');
    }

    public function test_specific_http_fakes_are_allowed_without_allowing_unmatched_requests(): void
    {
        Http::fake(['https://provider.example.test/activities' => Http::response(['activities' => []])]);

        $this->assertSame(['activities' => []], Http::get('https://provider.example.test/activities')->json());
        $this->expectException(StrayRequestException::class);

        Http::get('https://provider.example.test/other');
    }
}
