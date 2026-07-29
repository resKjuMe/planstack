<?php

namespace App\Http\Middleware;

use App\Models\Task;
use App\Support\ClaimSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nimmt das Session-Label des Aufrufers aus dem Header X-Planstack-Session
 * entgegen und hält den Heartbeat der Session aktuell.
 *
 * Zwei Aufgaben:
 *  1. handle():    Label in den request-scoped {@see ClaimSession} legen, damit ein
 *                  Claim in DIESEM Request damit gestempelt wird.
 *  2. terminate(): claim_seen_at auffrischen, wenn der Request einen Task
 *                  betrifft, den genau diese Session hält. Läuft nach der Antwort,
 *                  kostet also keine Request-Latenz.
 *
 * Der Heartbeat zählt bewusst auch LESENDE Zugriffe: eine Session, die ihren Task
 * abfragt, lebt. Aufgefrischt wird nur das eigene Lease (gleicher Nutzer UND
 * gleiches Label) — ein Blick des Menschen im Board auf denselben Task hält also
 * keine tote Session künstlich am Leben.
 *
 * Das Update läuft absichtlich über den Query-Builder: es umgeht die
 * Model-Events und damit den entity-changed-Broadcast. Ein Heartbeat ist kein
 * Board-Ereignis — sonst würde jeder API-Zugriff einen Socket-Push auslösen.
 * Für die Anzeige genügt es, dass der Client claim_seen_at beim nächsten Laden
 * sieht; „verwaist" leitet er selbst aus dem Alter ab.
 */
class TrackClaimSession
{
    public const HEADER = 'X-Planstack-Session';

    public function __construct(private readonly ClaimSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->session->set($request->header(self::HEADER));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $label = $this->session->label();

        if ($label === null) {
            return;
        }

        $task = $request->route('task');
        $userId = $request->user()?->id;

        if (! $task instanceof Task || $userId === null) {
            return;
        }

        // Frisch aus der DB lesen: die Route-Bindung hat den Task VOR dem
        // Controller geladen, der Claim kann also im selben Request entstanden
        // sein (POST .../claim). Nur die beiden Felder holen, kein ganzes Modell.
        $held = Task::whereKey($task->id)
            ->where('claimed_by_id', $userId)
            ->where('claim_session_label', $label)
            ->exists();

        if (! $held) {
            return;
        }

        Task::whereKey($task->id)->update(['claim_seen_at' => now()]);
    }
}
