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

    /**
     * Fortschritt als Header, huckepack auf jedem ohnehin stattfindenden Aufruf.
     *
     * Warum zusaetzlich zum Fortschritts-Event: die Praxis zeigt, dass ein separat
     * abzusetzendes Event vergessen wird, waehrend der Session-Header lueckenlos
     * mitfaehrt — er wird EINMAL im AUTH-Array eingerichtet und ist danach an jedem
     * Aufruf dabei. Was einmal eingerichtet wird, haelt; was man sich bei jedem
     * Schritt merken muss, driftet ab. Also wandert der Fortschritt auf denselben
     * Weg: der Client haengt Schritt und Prozentzahl an seinen Curl-Wrapper, und
     * jeder Claim, Status-Call oder Task-Read bringt den aktuellen Stand mit.
     *
     * Das Event bleibt trotzdem der Hauptweg — es ist der einzige Aufruf, der auch
     * dann passiert, wenn gerade sonst nichts mit dem Server zu bereden ist.
     */
    public const STEP_HEADER = 'X-Planstack-Step';

    public const PROGRESS_HEADER = 'X-Planstack-Progress';

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

        // Stand VOR dem Schreiben — die Route-Bindung hat den Task zu Beginn des
        // Requests geladen, das ist genau der Vergleichswert für „hat sich das Label
        // geändert?" weiter unten.
        $previousLabel = $task->active_session_label;

        // Beendet dieser Request die Arbeitseinheit (Merge, Freigabe, erfasstes
        // Review, Concern, abschliessendes Fortschritts-Event), dann wird der Vermerk
        // GERAEUMT statt gestempelt — sonst blieb „arbeitet daran" bis zum Ablauf der
        // TTL stehen, obwohl die Session fertig ist. Das Raeumen gehoert hierher und
        // nicht in die Aktion selbst: terminate() laeuft danach und wuerde ein dort
        // gesetztes null im selben Request wieder ueberschreiben.
        $attrs = $this->session->finished()
            // Mit dem Vermerk geht auch der Fortschritt: „4/9 Dateien, 44 %" beschreibt
            // eine laufende Arbeit. Bleibt er stehen, zeigt eine fertige Karte fuer
            // immer einen halben Balken. Die Historie geht nicht verloren — jedes
            // Fortschritts-Event steht mit detail/progress in task_events.
            ? [
                'active_session_label' => null,
                'active_session_seen_at' => null,
                'progress_detail' => null,
                'progress_percent' => null,
                'progress_at' => null,
            ]
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

        // Fortschritt aus den Headern — gilt als „dieser Request hat Fortschritt
        // gemeldet", genau wie ein Event mit detail/progress.
        $headerProgress = self::progressFrom($request);

        if ($headerProgress !== [] && ! $this->session->finished()) {
            $attrs = [...$attrs, ...$headerProgress, 'progress_at' => $now];
            $this->session->markProgressReported();
        }

        // Uebernimmt eine ANDERE Session den Task, gehoert der Fortschritt der
        // Vorgaengerin nicht mehr dazu: sonst zeigt die Karte die neue Session mit
        // dem alten Stand („4/9 Dateien"), bis deren erstes eigenes Event kommt.
        //
        // Nur beim echten Wechsel (vorher stand ein anderes Label) — und nicht, wenn
        // dieser Request selbst schon Fortschritt geschrieben hat, sonst wuerde das
        // erste Event einer neuen Session seinen eigenen Wert loeschen.
        $tookOver = $previousLabel !== null
            && ($attrs['active_session_label'] ?? null) !== $previousLabel;

        if ($tookOver && ! $this->session->finished() && ! $this->session->progressReported()) {
            $attrs['progress_detail'] = null;
            $attrs['progress_percent'] = null;
            $attrs['progress_at'] = null;
        }

        // Frisch aus der DB lesen: die Route-Bindung hat den Task VOR dem
        // Controller geladen, der Claim kann also im selben Request entstanden
        // sein (POST .../claim). Nur die nötigen Felder holen, kein ganzes Modell.
        $current = Task::whereKey($task->id)
            ->first(['id', 'claimed_by_id', 'claim_session_label', 'fix_leased_by']);

        $held = $current !== null
            && $current->claimed_by_id === $userId
            && $current->claim_session_label === $label;

        if ($held) {
            $attrs['claim_seen_at'] = $now;
        }

        // **Fix-Lease mitziehen.** Das Lease von `next-action(s)` läuft nach
        // fix_lease_minutes ab, damit ein toter Worker den PR freigibt. Ein LEBENDER
        // Worker verliert es damit aber mitten in der Arbeit — bei parallelen Workern
        // heißt das: ein zweiter bekommt denselben PR. Also verlängert jeder Zugriff
        // dieser Session das Lease, genau wie beim Claim-Heartbeat. Umgekehrt gibt das
        // Ende der Arbeitseinheit es sofort frei, statt es ablaufen zu lassen — der
        // nächste Worker kann den PR dann direkt übernehmen.
        if ($current?->fix_leased_by === $userId) {
            $attrs['fix_lease_expires_at'] = $this->session->finished()
                ? null
                : $now->copy()->addMinutes(max(1, (int) config('planstack.fix_lease_minutes', 15)));

            if ($this->session->finished()) {
                $attrs['fix_leased_by'] = null;
            }
        }

        Task::whereKey($task->id)->update($attrs);

        // Erscheinen, Wechsel und Verschwinden des Vermerks gehören live aufs Board —
        // sonst sähe man erst beim nächsten Reload, dass (nicht mehr) daran gearbeitet
        // wird. Der Query-Builder oben umgeht die Model-Events, also hier explizit.
        //
        // Nur bei einer ECHTEN Änderung des Labels: der reine Heartbeat (gleiche
        // Session, nur ein neues seen_at) läuft bei jedem API-Zugriff und würde sonst
        // einen Broadcast-Sturm ohne Neuigkeit auslösen.
        if (($attrs['active_session_label'] ?? null) !== $previousLabel) {
            $task->emitEntityChange('update');
        }
    }

    /**
     * Fortschritt aus den Headern, auf die Spaltenbreite bzw. 0–100 gebracht.
     *
     * Bewusst nachsichtig statt validierend: die Angabe faehrt huckepack auf einem
     * Aufruf, der etwas ganz anderes tut (Claim, Status, Task-Read). Ein krummer
     * Wert darf diesen Aufruf nicht mit einem 422 abschiessen — er wird gekappt
     * bzw. ignoriert. Ein leerer Header bedeutet „nichts Neues zu melden" und
     * laesst den letzten Stand stehen.
     *
     * @return array<string, string|int>
     */
    private static function progressFrom(Request $request): array
    {
        $out = [];

        $step = trim((string) $request->header(self::STEP_HEADER, ''));

        if ($step !== '') {
            $out['progress_detail'] = mb_substr($step, 0, 200);
        }

        $percent = trim((string) $request->header(self::PROGRESS_HEADER, ''));

        if ($percent !== '' && is_numeric($percent)) {
            $out['progress_percent'] = max(0, min(100, (int) $percent));
        }

        return $out;
    }

    /**
     * Label mit den Initialen des Betreibers davor („CM fix TXSAFE/GAP-MassRun").
     *
     * Liegt in {@see ClaimSession}, weil auch der NextActionResolver stempelt (er
     * reserviert Tasks fuer parallele Worker) — beide Wege muessen dasselbe Format
     * erzeugen, sonst wechselt der Vermerk beim ersten eigenen Aufruf des Workers
     * scheinbar die Session.
     */
    private static function withInitials(string $label, ?User $user): string
    {
        return ClaimSession::withInitials($label, $user);
    }
}
