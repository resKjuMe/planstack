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

// PR-Zustand (CI, unresolved Threads, Review-Entscheidung, letzter Commit) der 100
// zuletzt aktualisierten offenen PRs je Repo via GraphQL nach github_pull_requests
// spiegeln — Grundlage der serverseitigen „fix"-Erkennung. Gleiche Schutzflags wie
// oben: kein paralleler Zweitlauf, bei mehreren App-Servern nur auf einem.
Schedule::command('planstack:sync-pr-status --repo=clockodo-intern/main')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
