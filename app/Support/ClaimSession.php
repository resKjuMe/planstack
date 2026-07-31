<?php

namespace App\Support;

use App\Http\Middleware\TrackClaimSession;
use App\Models\Task;

/**
 * Die Session, die den aktuellen Request stellt — request-scoped im Container
 * (siehe AppServiceProvider), gefüllt von {@see TrackClaimSession}
 * aus dem Header X-Planstack-Session.
 *
 * Hintergrund: alle Reservierungen in Planstack hängen am Nutzer
 * (claimed_by_id, reviewed_by, fix_leased_by). Arbeiten mehrere Agenten-Sessions
 * unter demselben Token, ist nicht mehr erkennbar, WER von ihnen einen Task hält.
 * Das Label schließt diese Lücke, ohne am Identitätsmodell zu drehen: es ist
 * reine Anzeige-Information und wird nie für Autorisierung benutzt.
 */
class ClaimSession
{
    private ?string $label = null;

    private bool $finished = false;

    /**
     * Setzt das Label. Leerstrings werden zu null (ein leerer Header ist „keine
     * Session"), zu lange Werte hart gekürzt — die Spalte hält 60 Zeichen und ein
     * Client soll sich daran nicht mit einem 500er stoßen.
     */
    public function set(?string $label): void
    {
        $label = trim((string) $label);

        $this->label = $label === '' ? null : mb_substr($label, 0, 60);
    }

    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * Meldet, dass DIESER Request die Arbeitseinheit an der Aufgabe beendet — Merge,
     * Freigabe, erfasstes Review, Concern oder ein abschliessendes Fortschritts-Event.
     *
     * Warum als Flag und nicht direkt als Update: der Vermerk „arbeitet gerade daran"
     * wird von {@see TrackClaimSession::terminate()} NACH der Antwort geschrieben.
     * Wuerde eine Controller-Aktion ihn selbst raeumen, stempelte terminate() ihn im
     * selben Request sofort wieder — die aktive Session verschwaende also nie.
     * Deshalb entscheidet terminate() anhand dieses Flags, ob es stempelt oder raeumt.
     */
    public function finish(): void
    {
        $this->finished = true;
    }

    /** Endet die Arbeitseinheit mit diesem Request? */
    public function finished(): bool
    {
        return $this->finished;
    }

    /**
     * Ergänzt einen Task-Attribut-Array um das Session-Lease, PASSEND zur
     * Claim-Änderung darin:
     *
     *  - Claim wird gesetzt  → Label + Heartbeat stempeln
     *  - Claim wird geräumt  → beides mit-räumen
     *  - Claim unberührt     → Array unverändert
     *
     * Damit hängt das Lease am Claim selbst statt an einzelnen Endpunkten: jeder
     * Statuswechsel, dessen On-Enter-Effekte den Assignee räumen (z. B. PICKABLE),
     * räumt das Lease automatisch mit — auch wenn die Effekte je Organisation
     * frei konfiguriert sind.
     *
     * Ein Claim OHNE Session-Label (Board-Klick im Browser) räumt die Felder
     * ebenfalls: der Task gehört dann einem Menschen, nicht einer Session.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    public function stamp(array $attrs): array
    {
        if (! array_key_exists('claimed_by_id', $attrs)) {
            return $attrs;
        }

        $claimed = $attrs['claimed_by_id'] !== null;

        return array_merge($attrs, [
            'claim_session_label' => $claimed ? $this->label : null,
            'claim_seen_at' => $claimed && $this->label !== null ? now() : null,
        ]);
    }

    /**
     * Gilt das Session-Lease des Tasks als verwaist — also: es gibt eines, und die
     * Session hat sich länger als die TTL nicht gemeldet?
     *
     * Serverseitiges Gegenstück zur Anzeige-Ableitung im Client
     * (claimSessionState in resources/js/board/claimSession.js). Nötig, weil das
     * Entfernen des Vermerks auf der Task-Detailseite genau diese Bedingung
     * prüft: eine LEBENDE Session darf ihr Lease nicht unter den Füßen verlieren,
     * sonst behauptet das Board „niemand arbeitet daran", während ein Worker läuft.
     */
    public static function isStale(Task $task): bool
    {
        if ($task->claim_session_label === null) {
            return false;
        }

        $ttl = max(1, (int) config('planstack.claim_session_ttl_minutes', 30));

        return $task->claim_seen_at === null
            || $task->claim_seen_at->lt(now()->subMinutes($ttl));
    }
}
