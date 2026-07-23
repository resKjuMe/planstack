<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Standard-Broadcast-Verbindung. Für die Header-Glocke wird Pusher direkt
    | über App\Support\NotificationBroadcaster angesprochen (nicht über das
    | Event-/Queue-System), daher genügt hier die Verbindungs-Konfiguration.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                'useTLS' => true,
            ],
            // TLS-Verifikation der ausgehenden Pusher-Requests (via Guzzle-Client
            // in NotificationBroadcaster). Default true (sicher). Auf Windows
            // scheitert cURL ohne CA-Bundle mit "SSL certificate problem"
            // (Fehler 60) — korrekter Fix: curl.cainfo in php.ini setzen.
            // Notausgang für rein lokale Nutzung: PUSHER_VERIFY_SSL=false.
            'verify_ssl' => env('PUSHER_VERIFY_SSL', true),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
