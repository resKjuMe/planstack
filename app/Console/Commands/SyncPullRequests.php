<?php

namespace App\Console\Commands;

use App\Support\PullRequestStateSync;
use Illuminate\Console\Command;

class SyncPullRequests extends Command
{
    /**
     * @var string
     */
    protected $signature = 'planstack:sync-pull-requests';

    /**
     * @var string
     */
    protected $description = 'Status, letzten Commit und Umfang der offenen PRs aus ps-review via GitHub-GraphQL abgleichen';

    public function handle(PullRequestStateSync $sync): int
    {
        $result = $sync->sync();

        if ($result['tokenMissing']) {
            $this->error('Kein GITHUB_TOKEN konfiguriert — GraphQL erlaubt keine anonymen Anfragen.');

            return self::FAILURE;
        }

        $this->info("{$result['prs']} PR(s) über {$result['repos']} Repo(s) aktualisiert.");

        if ($result['errors'] > 0) {
            $this->warn("{$result['errors']} Fehler: ".implode('; ', $result['failures']));
        }

        return self::SUCCESS;
    }
}
