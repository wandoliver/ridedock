<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetUserLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? config('app.locale');

        if (in_array($locale, ['de', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
