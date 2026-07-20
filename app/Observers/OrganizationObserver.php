<?php

namespace App\Observers;

use App\Models\Organization;
use App\Support\DefaultTaskStatuses;

class OrganizationObserver
{
    /**
     * Seed every new organization with the default task-status configuration.
     * Placing this on the model event (rather than only in the controller)
     * covers programmatic creation, factories and tests too.
     */
    public function created(Organization $organization): void
    {
        DefaultTaskStatuses::seed($organization);
    }
}
