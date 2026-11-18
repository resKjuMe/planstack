<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Support\Jira\JiraStatusSync;
use Illuminate\Console\Command;

class SyncJira extends Command
{
    /**
     * @var string
     */
    protected $signature = 'planstack:sync-jira';

    /**
     * @var string
     */
    protected $description = 'Status und Assignee verknüpfter Tasks/Phasen nach Jira übertragen (Projekte mit Jira-Abgleich)';

    public function handle(JiraStatusSync $sync): int
    {
        $projects = Project::query()
            ->whereNotNull('jira_sync_user_id')
            ->whereNull('archived_at')
            ->with('jiraSyncUser')
            ->get();

        foreach ($projects as $project) {
            $result = $sync->sync($project);
            $line = "{$project->alias}: {$result['transitioned']} Status, {$result['assigned']} Assignee · {$result['checked']} geprüft";
            $result['errors'] === []
                ? $this->info($line)
                : $this->warn($line.' · '.implode('; ', $result['errors']));
        }

        return self::SUCCESS;
    }
}
