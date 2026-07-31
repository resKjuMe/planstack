<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skill-Download
    |--------------------------------------------------------------------------
    |
    | Basis-URL (inkl. /api), die in die vorausgefüllte config.json des
    | herunterladbaren Skills geschrieben wird. Default = Produktions-
    | Instanz, da der Skill die laufende Instanz fernsteuert. Für lokale Tests
    | PLANSTACK_SKILL_API_URL überschreiben.
    |
    */

    'skill_api_url' => env('PLANSTACK_SKILL_API_URL', 'https://planstack.eskju.net/api'),

    /*
    |--------------------------------------------------------------------------
    | GitHub-API (PR-Status-Sync)
    |--------------------------------------------------------------------------
    |
    | Token für den "PRs abgleichen"-Button, der den Merge-Status existierender
    | PRs von GitHub holt. Für private Repos (z. B. acme-corp/backend) ist ein
    | Token mit "repo"-Scope Pflicht; ohne Token funktioniert nur öffentliches
    | Repo (und stark rate-limitiert).
    |
    */

    'github_token' => env('GITHUB_TOKEN'),

    'github_api' => env('GITHUB_API_URL', 'https://api.github.com'),

    // TLS-Verifikation. Default true (sicher). Auf Windows scheitert cURL ohne
    // CA-Bundle mit "SSL certificate problem" (Fehler 60) — korrekter Fix:
    // curl.cainfo in php.ini auf eine cacert.pem zeigen lassen. Notausgang für
    // rein lokale Nutzung: GITHUB_VERIFY_SSL=false. Ein Pfad zu einer
    // cacert.pem ist ebenfalls erlaubt.
    'github_verify_ssl' => env('GITHUB_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | GitHub-Webhooks (POST /hooks/git)
    |--------------------------------------------------------------------------
    |
    | Optionales Secret zur Prüfung der HMAC-SHA256-Signatur eingehender
    | Webhooks (Header X-Hub-Signature-256). Ist es leer, wird die Signatur
    | nicht geprüft — sinnvoll für die reine Log-/Testphase, in Produktion
    | jedoch setzen.
    |
    */

    'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | next-action: Fix-Lease
    |--------------------------------------------------------------------------
    |
    | Wie lange (Minuten) die „fix"-Aktion von POST /projects/{p}/next-action
    | einen Task für einen Worker reserviert. Nach Ablauf gilt der Task wieder
    | als frei (ein toter Worker gibt den PR automatisch frei).
    |
    */

    'fix_lease_minutes' => (int) env('PLANSTACK_FIX_LEASE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Claim-Session: Heartbeat-TTL
    |--------------------------------------------------------------------------
    |
    | Nach wie vielen Minuten ohne Lebenszeichen (claim_seen_at) die Session,
    | die einen Task hält, als verwaist gilt — z. B. weil der Worker hart
    | gekillt wurde. Der Claim selbst bleibt bestehen (nur ein explizites
    | release gibt ihn frei); das Board markiert ihn lediglich als verwaist.
    | Großzügiger als das fix-Lease, da ein einzelner Task-Lauf lange ohne
    | API-Zugriff arbeiten kann.
    |
    */

    'claim_session_ttl_minutes' => (int) env('PLANSTACK_CLAIM_SESSION_TTL_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Aktive Session — Anzeigedauer ohne Lebenszeichen
    |--------------------------------------------------------------------------
    |
    | Deutlich kürzer als die Claim-TTL, weil beide Marken verschiedene Fragen
    | beantworten: ein Claim ist eine Reservierung, die bestehen bleibt, bis
    | jemand freigibt — „arbeitet gerade daran" ist dagegen sofort falsch, sobald
    | die Session weg ist.
    |
    | Regulär endet der Vermerk explizit (Ende-Signal am Abschluss der
    | Arbeitseinheit, s. TrackClaimSession). Diese TTL ist nur das Auffangnetz für
    | abgebrochene Läufe. Nicht zu knapp wählen: ein Lauf darf während eines
    | Testdurchlaufs oder einer CI-Wartezeit einige Minuten ohne API-Aufruf
    | bleiben, ohne dass die Karte ihn für beendet erklärt.
    |
    */
    'active_session_ttl_minutes' => (int) env('PLANSTACK_ACTIVE_SESSION_TTL_MINUTES', 10),

];

