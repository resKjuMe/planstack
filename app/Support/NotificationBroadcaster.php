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
    /** Event-Name für menschenlesbare Fortschritts-Meldungen (Header-Glocke). */
    public const EVENT = 'task-event';

    /**
     * Event-Name für generische Entity-Änderungen (Task/Phase created/updated/
     * deleted). Die React-Clients laden die betroffene Entity daraufhin partiell
     * nach; die Header-Glocke ignoriert dieses Event bewusst (kein Zähler).
     */
    public const EVENT_ENTITY = 'entity-changed';

    /**
     * Menschenlesbares Fortschritts-Event senden (Header-Glocke).
     *
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(?int $organizationId, array $payload): void
    {
        $this->trigger($organizationId, self::EVENT, $payload);
    }

    /**
     * Generische Entity-Änderung senden — Auslöser für das partielle Nachladen
     * im geteilten Client-Store (resources/js/data/projectStore.js).
     *
     * @param  array<string, mixed>  $payload
     */
    public function broadcastEntity(?int $organizationId, array $payload): void
    {
        $this->trigger($organizationId, self::EVENT_ENTITY, $payload);
    }

    /**
     * Ein Ereignis an den Organisations-Channel triggern. "Best effort": Fehler
     * brechen die eigentliche Antwort nie ab, sie werden nur geloggt. Ohne
     * Pusher-Credentials oder Organisation passiert nichts.
     *
     * @param  array<string, mixed>  $payload
     */
    private function trigger(?int $organizationId, string $event, array $payload): void
    {
        if (! $organizationId) {
            return;
        }

        $pusher = $this->pusher();

        if (! $pusher) {
            return; // Pusher nicht konfiguriert → nichts senden.
        }

        try {
            $pusher->trigger("organization-{$organizationId}", $event, $payload);
            // Diagnose: bestätigt, dass (und an welchen Channel) gesendet wurde.
            Log::debug('Pusher-Broadcast gesendet', [
                'channel' => "organization-{$organizationId}",
                'event' => $event,
                'entity' => $payload['entity'] ?? null,
                'id' => $payload['id'] ?? null,
                'action' => $payload['action'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pusher-Broadcast fehlgeschlagen', [
                'organization_id' => $organizationId,
                'event' => $event,
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
