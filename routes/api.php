<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Api\NextActionController;
use App\Http\Controllers\Api\PhaseController;
use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\ProjectConfigController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\StatusConfigController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-Routen (Token-Auth via Sanctum)
|--------------------------------------------------------------------------
|
| Alle Routen sind mit dem Präfix /api gebunden. Authentifizierung erfolgt
| über Bearer-Personal-Access-Tokens (Sanctum). Ohne gültiges Token → 401.
| Projekte werden über ihren Alias gebunden, Tasks über die id.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Wer bin ich? (Smoke-Test für Token-Auth)
    Route::get('/user', fn (Request $request) => $request->user());

    // Fortschritts-Events (task_id + event); wendet die je Event konfigurierte
    // Automation an und protokolliert das Event. Siehe docs/event-api.md.
    Route::post('events', [EventController::class, 'store']);

    // Projekte
    Route::get('projects', [ProjectController::class, 'index']);
    // Kompakte Aggregat-Übersicht für die Projektliste (Zähler/SP/Segment-Buckets
    // je Projekt per DB-Gruppierung, ohne alle Task-Rows zu laden). VOR der
    // {project}-Wildcard, damit „overview" nicht als Alias gebunden wird.
    Route::get('projects/overview', [ProjectController::class, 'overview']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::patch('projects/{project}', [ProjectController::class, 'update']);

    // Board-Read (pickable/Aggregate/Gates) — Einstieg für Board-Clients
    Route::get('projects/{project}/board', [ProjectController::class, 'board']);

    // Bündelt Board-Pick + Claim: wählt den besten pickbaren Task (meiste
    // `unlocks`) und beansprucht ihn atomar in einem Roundtrip. Spart die
    // GET /board → POST /claim → GET /task-Kette samt 409-Retry.
    Route::post('projects/{project}/claim-next', [TaskController::class, 'claimNext']);

    // Review: nächsten in-review Task mit PR zum Review übernehmen (Auto-Pick).
    Route::post('projects/{project}/review-next', [TaskController::class, 'reviewNext']);

    // Nächste sinnvolle Aktion entscheiden (fix → review → work) und den Task
    // atomar reservieren — {action, task} in einem Call für „/planstack auto".
    Route::post('projects/{project}/next-action', NextActionController::class);

    // Org-weite Status-Konfiguration für den geteilten React-Store (einmal laden,
    // über alle Projekte/Unterseiten wiederverwenden).
    Route::get('status-config', [StatusConfigController::class, 'show']);

    // Alle Tasks der zugänglichen Projekte (org-weit) — Datenbasis für die
    // Projektübersicht und (potenziell) die Unterseiten.
    Route::get('tasks', [TaskController::class, 'all']);

    // Paginierter Changelog-Feed für die React-Changelog-Seite.
    Route::get('projects/{project}/changelog', [ChangelogController::class, 'show']);

    // Board-Protokoll-Konfiguration (token-sparende Schalter)
    Route::get('projects/{project}/config', [ProjectConfigController::class, 'show']);
    Route::match(['put', 'patch'], 'projects/{project}/config', [ProjectConfigController::class, 'update']);

    // MCP-Server (Streamable-HTTP, JSON-RPC 2.0) — pro Projekt, gleiche Token-Auth
    Route::match(['get', 'post'], 'projects/{project}/mcp', McpController::class)->name('projects.mcp');

    // Phasen
    Route::get('projects/{project}/phases', [PhaseController::class, 'index']);
    Route::post('projects/{project}/phases', [PhaseController::class, 'store']);
    Route::match(['put', 'patch'], 'projects/{project}/phases/{phase}', [PhaseController::class, 'update'])->scopeBindings();
    Route::delete('projects/{project}/phases/{phase}', [PhaseController::class, 'destroy'])->scopeBindings();

    // Tasks (CRUD)
    Route::get('projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('projects/{project}/tasks', [TaskController::class, 'store']);
    // Gezielte Lookups (vor der {task}-Wildcard): Task per exaktem Namen bzw. per
    // PR-Nummer finden. Serverseitige PR→Task-Auflösung für den review/fix-Flow.
    Route::get('projects/{project}/tasks/by-name/{name}', [TaskController::class, 'showByName']);
    Route::get('projects/{project}/tasks/by-pr/{pr}', [TaskController::class, 'showByPr'])
        ->whereNumber('pr');
    Route::get('projects/{project}/tasks/{task}', [TaskController::class, 'show'])->scopeBindings();
    Route::match(['put', 'patch'], 'projects/{project}/tasks/{task}', [TaskController::class, 'update'])->scopeBindings();
    Route::delete('projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->scopeBindings();

    // Task-Aktionen (Write)
    Route::scopeBindings()->group(function () {
        Route::post('projects/{project}/tasks/{task}/claim', [TaskController::class, 'claim']);
        Route::post('projects/{project}/tasks/{task}/release', [TaskController::class, 'release']);
        // Review eines bestimmten Tasks übernehmen · Ergebnis erfassen
        Route::post('projects/{project}/tasks/{task}/review-claim', [TaskController::class, 'reviewClaim']);
        Route::post('projects/{project}/tasks/{task}/review', [TaskController::class, 'review']);
        Route::post('projects/{project}/tasks/{task}/status', [TaskController::class, 'status']);
        // Fortschritts-Event projekt-gebunden melden (Task per Name/id im Pfad) —
        // projektunabhängige REST-Alternative zum top-level POST /events und zum
        // MCP-Tool emit_event. Siehe EventController.
        Route::post('projects/{project}/tasks/{task}/events', [EventController::class, 'storeForTask']);
        Route::post('projects/{project}/tasks/{task}/pr', [TaskController::class, 'pr']);
        Route::post('projects/{project}/tasks/{task}/merge', [TaskController::class, 'merge']);
        // Gebündelte Aktion: PR setzen (optional) + fertig melden (+ optional mergen)
        // in einem Roundtrip — spart Tokens (actions.bundling).
        Route::post('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete']);
        Route::post('projects/{project}/tasks/{task}/gate', [TaskController::class, 'gate']);
        Route::post('projects/{project}/tasks/{task}/concern', [TaskController::class, 'concern']);
        Route::delete('projects/{project}/tasks/{task}/concern', [TaskController::class, 'resolveConcern']);
        Route::post('projects/{project}/tasks/{task}/split', [TaskController::class, 'split']);
    });
});
