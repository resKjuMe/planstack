<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Phase;
use App\Models\Task;
use App\Observers\OrganizationObserver;
use App\Observers\PhaseObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // New organizations get the default task-status configuration seeded.
        Organization::observe(OrganizationObserver::class);

        // Entity-Änderungen (Task/Phase) generisch über Pusher broadcasten, damit
        // der geteilte React-Store sie partiell nachlädt (entity-changed-Event).
        Task::observe(TaskObserver::class);
        Phase::observe(PhaseObserver::class);
    }
}
