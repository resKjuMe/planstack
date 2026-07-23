<?php

namespace App\Http\Middleware;

use App\Support\PageEnvelope;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wandelt Antworten bestehender Blade-Seiten (die via <x-app-layout> nur noch
 * einen PageEnvelope-Marker ausgeben) in Inertia-Antworten der Komponente
 * „BladePage" um. Alles andere (Redirects, Downloads, JSON, Fehler) bleibt
 * unangetastet. Läuft innerhalb von HandleInertiaRequests, damit die
 * Inertia-Antwort noch dessen Versions-/Redirect-Behandlung durchläuft.
 */
class BladeToInertia
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Nur einfache, erfolgreiche HTML-Antworten betrachten. Redirects,
        // Streams, JSON-/Binär-Downloads etc. haben keinen Marker.
        $contentType = (string) $response->headers->get('Content-Type', '');
        $isHtml = $contentType === '' || str_contains($contentType, 'text/html');

        if ($response->getStatusCode() === 200 && $isHtml) {
            $content = $response->getContent();
            if (is_string($content) && ($fragments = PageEnvelope::unwrap($content)) !== null) {
                return Inertia::render('BladePage', $fragments)->toResponse($request);
            }
        }

        return $response;
    }
}
