<?php

namespace App\Support;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * Sendet API-Ereignisse (aktuell POST /api/events) via Pusher an den
 * Organisations-Channel `organization-{id}`, den die Browser-Clients
 * (Header-Glocke) abonnieren.
 *
 * "Best effort": Fehler brechen die eigentliche API-Antwort nie ab, sie
 * werden nur geloggt. Ist Pusher nicht konfiguriert (fehlende Credentials)
 * oder fehlt die Organisation, passiert nichts.
 */
class NotificationBroadcaster
{
    /** Event-Name, auf den die Clients lauschen. */
    public const EVENT = 'task-event';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(?int $organizationId, array $payload): void
    {
        if (! $organizationId) {
            return;
        }

        $pusher = $this->pusher();

        if (! $pusher) {
            return; // Pusher nicht konfiguriert → nichts senden.
        }

        try {
            $pusher->trigger("organization-{$organizationId}", self::EVENT, $payload);
        } catch (\Throwable $e) {
            Log::warning('Pusher-Broadcast fehlgeschlagen', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Konfigurierten Pusher-Client bauen, oder null, wenn Credentials fehlen.
     * Die TLS-Verifikation läuft über einen eigenen Guzzle-Client (Pusher v7
     * nutzt intern Guzzle) — abschaltbar via PUSHER_VERIFY_SSL für lokale
     * Windows-Umgebungen ohne CA-Bundle.
     */
    public function pusher(): ?Pusher
    {
        $config = config('broadcasting.connections.pusher');

        if (empty($config['key']) || empty($config['secret']) || empty($config['app_id'])) {
            return null;
        }

        $client = new GuzzleClient([
            'verify' => $config['verify_ssl'] ?? true,
            'timeout' => 5, // best effort — die API-Antwort nicht ausbremsen
        ]);

        return new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $config['options'] ?? [],
            $client,
        );
    }
}
