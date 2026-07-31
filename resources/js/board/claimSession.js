/**
 * Zustand der Worker-Session, die einen Task hält — abgeleitet aus dem Alter des
 * Heartbeats (claim_seen_at) gegen die TTL. null, wenn keine Session dahinter
 * steht (Claim per Board-Klick durch einen Menschen).
 *
 * „stale" heißt NICHT, dass der Claim weg ist: er bleibt bestehen, bis ihn
 * jemand explizit freigibt. Es heißt, dass sich die Session nicht mehr gemeldet
 * hat — typischerweise ein hart gekillter Worker, der den Task sonst unsichtbar
 * für immer belegen würde.
 *
 * Geteilt von Board-Karte und Task-Detailseite, damit beide dieselbe Grenze
 * ziehen; das serverseitige Gegenstück ist ClaimSession::isStale() (nötig, weil
 * das Entfernen des Vermerks auf der Detailseite dieselbe Bedingung prüft).
 *
 * @param {{label: ?string, seenAt: ?string, ttlMinutes: ?number}} lease
 * @param {number} now  Zeitstempel in ms (siehe useNow)
 * @returns {?{label: string, stale: boolean, seenAt: ?number}}
 */
export function claimSessionState(lease, now) {
    if (! lease?.label) {
        return null;
    }

    const seenAt = lease.seenAt ? new Date(lease.seenAt).getTime() : null;
    const ttlMs = Math.max(1, Number(lease.ttlMinutes) || 30) * 60_000;
    // Ohne Heartbeat (Alt-Claim von vor dieser Funktion) lieber „verwaist" zeigen
    // als Aktivität behaupten, die niemand bestätigt hat.
    const stale = seenAt === null || now - seenAt > ttlMs;

    return { label: lease.label, stale, seenAt };
}
