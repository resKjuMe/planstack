<?php

namespace App\Console\Commands;

use App\Support\GitHubPrStatusSync;
use Illuminate\Console\Command;

class SyncPrStatus extends Command
{
    /**
     * @var string
     */
    protected $signature = 'planstack:sync-pr-status {--repo= : Nur dieses eine Repo (owner/name) statt aller Projekte}';

    /**
     * @var string
     */
    protected $description = 'CI-Status, unresolved Threads, Review-Entscheidung und letzten Commit der 100 zuletzt aktualisierten offenen PRs je Repo via GitHub-GraphQL abgleichen';

    public function handle(GitHubPrStatusSync $sync): int
    {
        $repo = $this->option('repo');
        $result = $repo
            ? $sync->syncRepos([$repo])
            : $sync->syncAll();

        if ($result['tokenMissing']) {
            $this->error('Kein GITHUB_TOKEN konfiguriert — GraphQL erlaubt keine anonymen Anfragen.');

            return self::FAILURE;
        }

        if ($result['repos'] === 0) {
            $this->comment('Kein Projekt mit GitHub-Repo zum Abgleichen.');

            return self::SUCCESS;
        }

        $this->info("{$result['prs']} Task(s) über {$result['repos']} Repo(s) mit PR-Status aktualisiert.");

        if ($result['errors'] > 0) {
            $this->warn("{$result['errors']} Fehler: ".implode('; ', $result['failures']));
        } elseif ($result['failures'] !== []) {
            // Teildaten-Hinweise (kein harter Fehler), z. B. fehlendes „Checks: read"-
            // Recht → CI-Step-Aufschlüsselung bleibt leer, Rest wird uebernommen.
            $this->comment('Hinweise: '.implode('; ', $result['failures']));
        }

        return self::SUCCESS;
    }
}
