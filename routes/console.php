<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping: falls ein Lauf mal länger als eine Minute braucht (GitHub
// langsam/Retry), keinen zweiten parallel starten. onOneServer: bei mehreren
// App-Servern nur auf einem laufen lassen — sonst doppelte Requests.
Schedule::command('planstack:sync-prs')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
