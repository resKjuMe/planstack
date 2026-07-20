<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectCalibrationController;
use App\Http\Controllers\ProjectChangelogController;
use App\Http\Controllers\ProjectClaudeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDiagramController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationTaskStatusController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectPhaseController;
use App\Http\Controllers\ProjectPrSequenceController;
use App\Http\Controllers\ProjectPrSyncController;
use App\Http\Controllers\ProjectSummaryController;
use App\Http\Controllers\SkillDownloadController;
use App\Http\Controllers\ProjectTeamController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskConcernController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Middleware\EnsureUserHasOrganization;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'projects.index' : 'login'));

// Öffentliche API-Dokumentation (kein Login erforderlich)
Route::get('/api-docs', ApiDocsController::class)->name('api.docs');

Route::get('/dashboard', fn () => redirect()->route('projects.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // ---- Immer erreichbar, auch ohne Organisation ----

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Personal access tokens (API / Planstack skill)
    Route::post('/profile/tokens', [ApiTokenController::class, 'store'])->name('profile.tokens.store');
    Route::delete('/profile/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('profile.tokens.destroy');

    // Organisation (jeder User gehört höchstens einer an): gründen, beitreten
    // (per individuellem Code), einladen, austreten, löschen.
    Route::get('organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('organization', [OrganizationController::class, 'store'])->name('organization.store');
    Route::post('organization/join', [OrganizationController::class, 'join'])->name('organization.join');
    Route::post('organization/invite', [OrganizationController::class, 'invite'])->name('organization.invite');
    Route::post('organization/leave', [OrganizationController::class, 'leave'])->name('organization.leave');
    Route::delete('organization', [OrganizationController::class, 'destroy'])->name('organization.destroy');

    // Verwaltung der org-konfigurierbaren Task-Status (nur Gründer). Die
    // Transitions-Route steht vor der {status}-Route, damit sie nicht als
    // Wildcard gebunden wird.
    Route::get('organization/statuses', [OrganizationTaskStatusController::class, 'index'])
        ->name('organization.statuses.index');
    Route::put('organization/status-transitions', [OrganizationTaskStatusController::class, 'updateTransitions'])
        ->name('organization.statuses.transitions');
    Route::patch('organization/statuses/{status}', [OrganizationTaskStatusController::class, 'update'])
        ->name('organization.statuses.update');
    Route::post('organization/statuses', [OrganizationTaskStatusController::class, 'storeStatus'])
        ->name('organization.statuses.store');
    Route::delete('organization/statuses/{status}', [OrganizationTaskStatusController::class, 'destroyStatus'])
        ->name('organization.statuses.destroy');
    Route::put('organization/statuses/{status}/effects', [OrganizationTaskStatusController::class, 'updateEffects'])
        ->name('organization.statuses.effects');
    Route::put('organization/status-order', [OrganizationTaskStatusController::class, 'reorder'])
        ->name('organization.statuses.reorder');
    Route::post('organization/status-groups', [OrganizationTaskStatusController::class, 'storeGroup'])
        ->name('organization.statuses.groups.store');
    Route::delete('organization/status-groups/{group}', [OrganizationTaskStatusController::class, 'destroyGroup'])
        ->name('organization.statuses.groups.destroy');

    // ---- Erfordert die Zugehörigkeit zu einer Organisation ----
    Route::middleware(EnsureUserHasOrganization::class)->group(function () {
    // Nutzer-Changelog der Website (Versionsübersicht in der Hauptnavi)
    Route::view('/changelog', 'changelog')->name('changelog');

    // Allgemeiner Planstack-Claude-Code-Skill (projektübergreifend): eigener
    // Hauptnavi-Punkt mit vorgeschalteter Erklärungsseite (/skill) und dem
    // eigentlichen ZIP-Download (/skill/download). Das Projekt wird dem Skill
    // dynamisch als Argument übergeben (/planstack <PROJECT>).
    Route::view('/skill', 'skill.setup')->name('skill.setup');
    Route::get('/skill/download', SkillDownloadController::class)->name('skill.download');

    // Einrichtungs-/Anleitungsseite für die CI-Status-Anzeige (Userscript +
    // lokaler ci-server) — reguläre App-Seite mit Menü.
    Route::view('/planstack-ci/setup', 'planstack-ci.setup')->name('planstack-ci.setup');

    // FAQ / Nachschlagewerk (Hauptnavi „FAQ")
    Route::prefix('faq')->name('faq.')->group(function () {
        Route::view('/', 'faq.index')->name('index');
        Route::view('/status-logic', 'faq.status-logic')->name('status-logic');
    });

    // Teams
    Route::resource('teams', TeamController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])
        ->name('teams.members.store');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])
        ->name('teams.members.destroy');

    // Projects
    Route::resource('projects', ProjectController::class);

    // Status views (Diagramm / PR-Sequenz / Summary / Changelog) — top-level,
    // same nesting depth as the board itself (see the redirects below for the
    // former /status/... URLs).
    Route::get('projects/{project}/diagram', ProjectDiagramController::class)
        ->name('projects.diagram');
    Route::get('projects/{project}/pr-sequence', ProjectPrSequenceController::class)
        ->name('projects.pr-sequence');
    Route::get('projects/{project}/summary', ProjectSummaryController::class)
        ->name('projects.summary');
    Route::get('projects/{project}/changelog', ProjectChangelogController::class)
        ->name('projects.changelog');
    Route::get('projects/{project}/kalibrierung', ProjectCalibrationController::class)
        ->name('projects.calibration');
    Route::get('projects/{project}/access', [ProjectController::class, 'access'])
        ->name('projects.access');

    // Legacy URLs (formerly nested under /status/...) redirect permanently.
    Route::permanentRedirect('projects/{project}/status/diagram', '/projects/{project}/diagram');
    Route::permanentRedirect('projects/{project}/status/pr-sequence', '/projects/{project}/pr-sequence');
    Route::permanentRedirect('projects/{project}/status/summary', '/projects/{project}/summary');
    Route::permanentRedirect('projects/{project}/status/changelog', '/projects/{project}/changelog');

    // Pull PR merge status from GitHub and tag merged tasks
    Route::post('projects/{project}/sync-prs', ProjectPrSyncController::class)
        ->name('projects.sync-prs');

    // "Claude"-Unterseite der Projektbearbeitung: Board-Protokoll-Konfiguration
    // (token-sparende Schalter), Web-Pendant zur API /config.
    Route::get('projects/{project}/claude', [ProjectClaudeController::class, 'edit'])
        ->name('projects.claude.edit');
    Route::match(['put', 'patch'], 'projects/{project}/claude', [ProjectClaudeController::class, 'update'])
        ->name('projects.claude.update');

    // Phasen-Verwaltung (Web-Pendant zur API /phases): anlegen, umbenennen,
    // umsortieren, löschen. Tasks einer gelöschten Phase werden gelöst (phase_id
    // → null), nicht mitgelöscht.
    Route::get('projects/{project}/phases', [ProjectPhaseController::class, 'index'])
        ->name('projects.phases.index');
    Route::post('projects/{project}/phases', [ProjectPhaseController::class, 'store'])
        ->name('projects.phases.store');
    Route::match(['put', 'patch'], 'projects/{project}/phases/{phase}', [ProjectPhaseController::class, 'update'])
        ->scopeBindings()->name('projects.phases.update');
    Route::post('projects/{project}/phases/{phase}/move', [ProjectPhaseController::class, 'move'])
        ->scopeBindings()->name('projects.phases.move');
    Route::delete('projects/{project}/phases/{phase}', [ProjectPhaseController::class, 'destroy'])
        ->scopeBindings()->name('projects.phases.destroy');

    // Project ← team assignment (grants access)
    Route::post('projects/{project}/teams', [ProjectTeamController::class, 'store'])
        ->name('projects.teams.store');
    Route::delete('projects/{project}/teams/{team}', [ProjectTeamController::class, 'destroy'])
        ->name('projects.teams.destroy');

    // Per-user role within a project (role distribution)
    Route::patch('projects/{project}/members/{user}', [ProjectMemberController::class, 'update'])
        ->name('projects.members.update');
    Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])
        ->name('projects.members.destroy');

    // Tasks (nested + scoped to their project)
    Route::resource('projects.tasks', TaskController::class)
        ->scoped(['task' => 'id'])
        ->except(['index']);

    Route::post('projects/{project}/tasks/{task}/claim', [TaskController::class, 'claim'])
        ->scopeBindings()
        ->name('projects.tasks.claim');

    // Board drag-and-drop status change (JSON; transition validated server-side)
    Route::post('projects/{project}/tasks/{task}/board-move', [TaskController::class, 'move'])
        ->scopeBindings()
        ->name('projects.tasks.board-move');

    // Claim the review of a task (sets reviewed_by to the current user).
    Route::post('projects/{project}/tasks/{task}/review-claim', [TaskController::class, 'reviewClaim'])
        ->scopeBindings()
        ->name('projects.tasks.review-claim');

    // Task concern (1:1, upsert)
    Route::get('projects/{project}/tasks/{task}/concern/edit', [TaskConcernController::class, 'edit'])
        ->scopeBindings()
        ->name('projects.tasks.concern.edit');
    Route::put('projects/{project}/tasks/{task}/concern', [TaskConcernController::class, 'update'])
        ->scopeBindings()
        ->name('projects.tasks.concern.update');
    Route::delete('projects/{project}/tasks/{task}/concern', [TaskConcernController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.tasks.concern.destroy');

    // Task-Checkliste (Akzeptanzkriterien + Testschritte, abhakbar)
    Route::patch('projects/{project}/tasks/{task}/checklist/{checklistItem}', [TaskChecklistController::class, 'toggle'])
        ->scopeBindings()
        ->name('projects.tasks.checklist.toggle');
    Route::post('projects/{project}/tasks/{task}/checklist/convert', [TaskChecklistController::class, 'convert'])
        ->scopeBindings()
        ->name('projects.tasks.checklist.convert');
    }); // Ende: erfordert Organisation
});

require __DIR__.'/auth.php';
