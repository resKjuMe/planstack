<?php

namespace App\Providers;

use App\Models\Organization;
use App\Observers\OrganizationObserver;
use App\Support\ClaimSession;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Das Session-Label des laufenden Requests: einmal pro Request gefüllt
        // (TrackClaimSession) und von TaskStatusService beim Stempeln des Claims
        // gelesen. `scoped` statt `singleton`, damit es zwischen Requests eines
        // Octane-/Queue-Workers nicht durchsickert.
        $this->app->scoped(ClaimSession::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // New organizations get the default task-status configuration seeded.
        Organization::observe(OrganizationObserver::class);

        // Entity-Änderungen werden generisch über den Trait BroadcastsEntityChange
        // gemeldet (Task, Phase, Project, TaskConcern, TaskPullRequest) — kein
        // Observer je Modell mehr nötig.
    }
}
