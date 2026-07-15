<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectCalibrationController;
use App\Http\Controllers\ProjectChangelogController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDiagramController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectPrSequenceController;
use App\Http\Controllers\ProjectPrSyncController;
use App\Http\Controllers\ProjectSkillController;
use App\Http\Controllers\ProjectSummaryController;
use App\Http\Controllers\ProjectTeamController;
use App\Http\Controllers\TaskConcernController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'projects.index' : 'login'));

// Öffentliche API-Dokumentation (kein Login erforderlich)
Route::get('/api-docs', ApiDocsController::class)->name('api.docs');

Route::get('/dashboard', fn () => redirect()->route('projects.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Personal access tokens (API / Planstack skill)
    Route::post('/profile/tokens', [ApiTokenController::class, 'store'])->name('profile.tokens.store');
    Route::delete('/profile/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('profile.tokens.destroy');

    // Teams
    Route::resource('teams', TeamController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
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

    // Legacy URLs (formerly nested under /status/...) redirect permanently.
    Route::permanentRedirect('projects/{project}/status/diagram', '/projects/{project}/diagram');
    Route::permanentRedirect('projects/{project}/status/pr-sequence', '/projects/{project}/pr-sequence');
    Route::permanentRedirect('projects/{project}/status/summary', '/projects/{project}/summary');
    Route::permanentRedirect('projects/{project}/status/changelog', '/projects/{project}/changelog');

    // Pull PR merge status from GitHub and tag merged tasks
    Route::post('projects/{project}/sync-prs', ProjectPrSyncController::class)
        ->name('projects.sync-prs');

    // Download the Planstack Claude-Code skill (SKILL.md + prefilled config) as ZIP
    Route::get('projects/{project}/skill', ProjectSkillController::class)
        ->name('projects.skill');

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
});

require __DIR__.'/auth.php';
