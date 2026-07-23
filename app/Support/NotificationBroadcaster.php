<?php

namespace App\Support;

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

        $config = config('broadcasting.connections.pusher');

        if (empty($config['key']) || empty($config['secret']) || empty($config['app_id'])) {
            return; // Pusher nicht konfiguriert → nichts senden.
        }

        try {
            $pusher = new Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                $config['options'] ?? [],
            );

            $pusher->trigger("organization-{$organizationId}", self::EVENT, $payload);
        } catch (\Throwable $e) {
            Log::warning('Pusher-Broadcast fehlgeschlagen', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
