<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt den Zugriff auf die eigentliche Anwendung (Projekte, Teams, Tasks …),
 * solange der User keiner Organisation angehört. Betroffene werden auf die
 * Organisationsseite geleitet, wo sie eine gründen oder einer beitreten können.
 */
class EnsureUserHasOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->organization_id === null) {
            return redirect()->route('organization.index')
                ->with('status', __('flash.organization_required'));
        }

        return $next($request);
    }
}
