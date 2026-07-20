<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated user's stored UI language to the current request.
 *
 * Appended to the "web" group after the auth middleware, so $request->user() is
 * already resolved. Guests and users with an unexpected value fall back to the
 * application default (config('app.locale')).
 */
class SetLocale
{
    /** Languages the UI is translated into. */
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
