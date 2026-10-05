<?php

namespace App\Support;

use Illuminate\Http\Request;

class ActivityNavigation
{
    public static function returnTo(Request $request): ?string
    {
        $value = $request->input('return_to');

        if (! is_string($value) || strlen($value) > 2048 || ! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['host']) || preg_match('/[\x00-\x20\\\\]/', $value)) {
            return null;
        }

        return in_array($parts['path'] ?? '', ['/', '/activities', '/coach', '/insights'], true) ? $value : null;
    }

    /** @return array<string, string> */
    public static function detailParameters(Request $request, string $activity): array
    {
        $parameters = ['activity' => $activity];
        $returnTo = self::returnTo($request);

        if ($returnTo !== null) {
            $parameters['return_to'] = $returnTo;
        }

        return $parameters;
    }

    public static function detailUrl(Request $request, string $activity): string
    {
        $url = route('activities.show', self::detailParameters($request, $activity));
        $section = $request->input('section');

        return in_array($section, ['overview', 'measurements', 'coach', 'notes'], true) ? $url.'#'.$section : $url;
    }
}
