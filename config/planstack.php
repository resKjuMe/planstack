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
    | WebSocket-Weiterleitung (POST /api/events → WebSocket-Server)
    |--------------------------------------------------------------------------
    |
    | Läuft die Instanz unter der Produktions-Domain (websocket_forward_host),
    | wird die Antwort von POST /api/events zusätzlich an den WebSocket-Server
    | geschickt (HTTP POST auf websocket_send_url), der sie an verbundene
    | Browser-Clients (Header-Glocke) verteilt. Auf allen anderen Domains
    | passiert nichts. Fehler brechen die API-Antwort nie ab (nur Log).
    |
    | Hinweis: Der Server spricht den /send-Endpunkt per HTTP an — daher https
    | (nicht das Browser-Schema wss); Host/Port/Pfad sind identisch.
    |
    */

    'websocket_forward_host' => env('PLANSTACK_WEBSOCKET_HOST', 'planstack.eskju.net'),

    'websocket_send_url' => env('PLANSTACK_WEBSOCKET_SEND_URL', 'https://websocket.eskju.net:8443/send'),

    // Timeout (Sekunden) für die Weiterleitung — kurz halten, damit die
    // API-Antwort nicht spürbar verzögert wird, falls der Socket-Server hängt.
    'websocket_timeout' => env('PLANSTACK_WEBSOCKET_TIMEOUT', 3),

    // TLS-Verifikation der Weiterleitung (analog github_verify_ssl). Für einen
    // selbstsignierten Socket-Server ggf. auf false setzen.
    'websocket_verify_ssl' => env('PLANSTACK_WEBSOCKET_VERIFY_SSL', true),

];

