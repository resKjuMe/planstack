<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Leitet API-Antworten (aktuell POST /api/events) an den WebSocket-Server
 * weiter, der sie an verbundene Browser-Clients (Header-Glocke) verteilt.
 *
 * Nur aktiv, wenn die Anfrage unter der konfigurierten Produktions-Domain
 * (planstack.websocket_forward_host) eingeht — auf allen anderen Domains
 * (lokal, Staging) passiert nichts. Die Weiterleitung ist "best effort":
 * Fehler brechen die eigentliche API-Antwort nie ab, sie werden nur geloggt.
 */
class NotificationSocketForwarder
{
    /**
     * @param  string  $host  Host der eingehenden Anfrage ($request->getHost())
     * @param  array<string, mixed>  $payload  An den Socket-Server zu sendende Nutzlast
     */
    public function forward(string $host, array $payload): void
    {
        $gate = config('planstack.websocket_forward_host');
        $url = config('planstack.websocket_send_url');

        // Nur unter der Produktions-Domain und nur mit gültiger Ziel-URL.
        if (empty($gate) || empty($url) || $host !== $gate) {
            return;
        }

        try {
            Http::asJson()
                ->withOptions(['verify' => config('planstack.websocket_verify_ssl', true)])
                ->timeout((int) config('planstack.websocket_timeout', 3))
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('WebSocket-Weiterleitung fehlgeschlagen', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
