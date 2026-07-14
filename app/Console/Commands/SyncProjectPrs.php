<?php

namespace App\Console\Commands;

use App\Support\GitHubPrSync;
use Illuminate\Console\Command;
use RuntimeException;

class SyncProjectPrs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'planstack:sync-prs';

    /**
     * @var string
     */
    protected $description = 'PRs aller Projekte mit GitHub-Repo abgleichen (Repo-übergreifend dedupliziert)';

    public function handle(GitHubPrSync $sync): int
    {
        try {
            $result = $sync->syncAll();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['checked'] === 0) {
            $this->comment('Keine offenen PRs mit PR-Nummer zum Abgleichen.');

            return self::SUCCESS;
        }

        $this->info("{$result['merged']} PR(s) als merged getaggt · {$result['checked']} Tasks über {$result['requests']} Request(s) geprüft");

        if ($result['errors'] > 0) {
            $this->warn("{$result['errors']} Fehler: ".implode('; ', $result['failures']));
        }

        return self::SUCCESS;
    }
}
