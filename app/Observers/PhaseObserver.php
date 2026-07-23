<?php

namespace App\Observers;

use App\Models\Phase;
use App\Support\NotificationBroadcaster;

/**
 * Broadcastet Phasen-Änderungen (created/updated/deleted) generisch an den
 * Org-Channel. Der geteilte React-Store lädt die Phasen daraufhin neu (die
 * Summary-Ableitung hängt an Phasen-Name/-Position). Analog zu {@see TaskObserver}.
 */
class PhaseObserver
{
    public function __construct(private readonly NotificationBroadcaster $broadcaster) {}

    public function created(Phase $phase): void
    {
        $this->emit($phase, 'created');
    }

    public function updated(Phase $phase): void
    {
        $this->emit($phase, 'updated');
    }

    public function deleted(Phase $phase): void
    {
        $this->emit($phase, 'deleted');
    }

    private function emit(Phase $phase, string $action): void
    {
        $project = $phase->project;

        if ($project === null) {
            return;
        }

        $this->broadcaster->broadcastEntity($project->organization_id, [
            'type' => 'entity-changed',
            'entity' => 'phase',
            'id' => $phase->id,
            'action' => $action,
            'project_id' => $project->id,
            'project_alias' => $project->alias,
            'organization_id' => $project->organization_id,
        ]);
    }
}
