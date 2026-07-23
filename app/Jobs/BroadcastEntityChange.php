<?php

namespace App\Jobs;

use App\Support\NotificationBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sendet ein Entity-Änderungs-Event via Pusher — als gequeueter Job, damit der
 * (best-effort, netzgebundene) Pusher-Trigger NICHT in der Request-Latenz hängt.
 * Bei QUEUE_CONNECTION=sync läuft er inline (wie zuvor), sonst asynchron über den
 * Worker. Die Nutzlast ist zum Dispatch-Zeitpunkt fertig berechnet (kein
 * Model-Zugriff im Job nötig).
 */
class BroadcastEntityChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly array $payload,
    ) {}

    public function handle(NotificationBroadcaster $broadcaster): void
    {
        $broadcaster->broadcastEntity($this->organizationId, $this->payload);
    }
}
