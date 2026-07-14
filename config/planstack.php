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

];

