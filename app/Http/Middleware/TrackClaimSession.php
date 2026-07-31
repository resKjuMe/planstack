<?php

namespace App\Http\Middleware;

use App\Models\Task;
use App\Models\User;
use App\Support\ClaimSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nimmt das Session-Label des Aufrufers aus dem Header X-Planstack-Session
 * entgegen und hält den Heartbeat der Session aktuell.
 *
 * Drei Aufgaben:
 *  1. handle():    Label in den request-scoped {@see ClaimSession} legen, damit ein
 *                  Claim in DIESEM Request damit gestempelt wird.
 *  2. terminate(): claim_seen_at auffrischen, wenn der Request einen Task
 *                  betrifft, den genau diese Session hält. Läuft nach der Antwort,
 *                  kostet also keine Request-Latenz.
 *  3. terminate(): den Task mit der AKTIVEN Session stempeln — für jede Ausführung,
 *                  auch ohne Claim (s. u.).
 *
 * Der Heartbeat zählt bewusst auch LESENDE Zugriffe: eine Session, die ihren Task
 * abfragt, lebt. Aufgefrischt wird nur das eigene Lease (gleicher Nutzer UND
 * gleiches Label) — ein Blick des Menschen im Board auf denselben Task hält also
 * keine tote Session künstlich am Leben.
 *
 * **Aktive Session (active_session_label/…_seen_at):** Das Claim-Lease reicht als
 * Vermerk nicht, weil es am Claim hängt. `fix` claimt nie (es arbeitet am PR einer
 * Aufgabe, die oft jemand anderes hält), `review` reserviert über `reviewed_by`, und
 * `work` auf einem bereits geclaimten Task claimt nicht erneut. Diese Ausführungen
 * blieben im Board unsichtbar. Deshalb wird hier bei JEDEM task-bezogenen Request
 * mit Session-Header festgehalten, welche Session ihn gerade anfasst — unabhängig
 * von Claim, Reviewer oder Lease und ohne das fremde Claim-Lease zu überschreiben.
 * Der Skill muss dafür nichts melden; es gibt damit keinen Pfad, auf dem der
 * Vermerk vergessen wird (auf freiwillige Meldungen war kein Verlass — die
 * Fix-Anleitung etwa setzt nur `POLISHING` ohne Zusatzangaben ab).
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

        $now = now();

        // Beendet dieser Request die Arbeitseinheit (Merge, Freigabe, erfasstes
        // Review, Concern, abschliessendes Fortschritts-Event), dann wird der Vermerk
        // GERAEUMT statt gestempelt — sonst blieb „arbeitet daran" bis zum Ablauf der
        // TTL stehen, obwohl die Session fertig ist. Das Raeumen gehoert hierher und
        // nicht in die Aktion selbst: terminate() laeuft danach und wuerde ein dort
        // gesetztes null im selben Request wieder ueberschreiben.
        $attrs = $this->session->finished()
            ? ['active_session_label' => null, 'active_session_seen_at' => null]
            // Aktive Session: gilt fuer jede Ausfuehrung, auch ohne Claim. Bewusst
            // bedingungslos (jeder task-bezogene Request dieser Session) — genau das
            // macht `fix`/`review`/Weiterarbeit sichtbar, die nie claimen.
            //
            // Mit Initialen des Betreibers davor („CM fix TXSAFE/GAP-MassRun"):
            // mehrere Personen fahren Worker unter gleich aufgebauten Labels, ohne
            // das Praefix waeren sie im Board nicht unterscheidbar. Es kommt vom
            // Server, nicht aus dem Header — der Nutzer ist hier bekannt, und ein
            // Client soll sich das nicht selbst zusammensetzen (koennte es auch falsch).
            : [
                'active_session_label' => self::withInitials($label, $request->user()),
                'active_session_seen_at' => $now,
            ];

        // Frisch aus der DB lesen: die Route-Bindung hat den Task VOR dem
        // Controller geladen, der Claim kann also im selben Request entstanden
        // sein (POST .../claim). Nur die beiden Felder holen, kein ganzes Modell.
        $held = Task::whereKey($task->id)
            ->where('claimed_by_id', $userId)
            ->where('claim_session_label', $label)
            ->exists();

        if ($held) {
            $attrs['claim_seen_at'] = $now;
        }

        Task::whereKey($task->id)->update($attrs);
    }

    /**
     * Label mit den Initialen des Betreibers davor („CM fix TXSAFE/GAP-MassRun").
     *
     * Auf die Spaltenbreite (60) gekuerzt, und zwar am LABEL, nicht am Praefix: die
     * Initialen sind der Teil, der die Sessions unterscheidbar macht, und wuerden
     * beim Abschneiden von rechts als Erstes wegfallen.
     */
    private static function withInitials(string $label, ?User $user): string
    {
        $initials = $user?->initials() ?? '';

        if ($initials === '') {
            return mb_substr($label, 0, 60);
        }

        return mb_substr($initials.' '.$label, 0, 60);
    }
}
