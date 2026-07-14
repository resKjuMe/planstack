<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\GitHubPrSync;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ProjectPrSyncController extends Controller
{
    /**
     * Fetch the merge status of the project's open PRs from GitHub and tag
     * merged ones as MERGED.
     */
    public function __invoke(Project $project, GitHubPrSync $sync): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $result = $sync->sync($project);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['checked'] === 0) {
            return back()->with('status', 'Keine offenen PRs mit PR-Nummer zum Abgleichen.');
        }

        $message = "{$result['merged']} PR(s) als merged getaggt · {$result['checked']} geprüft";

        if ($result['errors'] > 0) {
            $message .= " · {$result['errors']} Fehler: ".$this->errorDetail($result);
        }

        return back()->with($result['errors'] > 0 && $result['merged'] === 0 ? 'error' : 'status', $message);
    }

    /**
     * Turn the collected HTTP error statuses into a readable hint.
     *
     * @param  array{tokenMissing: bool, statuses: array<int, int>}  $result
     */
    private function errorDetail(array $result): string
    {
        $hints = [
            401 => 'Token ungültig oder abgelaufen',
            403 => 'kein Zugriff oder Rate-Limit (ggf. SSO für die Organisation autorisieren)',
            404 => 'PR/Repo nicht gefunden oder Token ohne Zugriff',
        ];

        $codes = array_values(array_unique($result['statuses']));
        sort($codes);

        $parts = array_map(
            fn (int $c) => "HTTP {$c}".(isset($hints[$c]) ? " ({$hints[$c]})" : ''),
            $codes,
        );

        $detail = implode(', ', $parts);

        if ($result['tokenMissing']) {
            $detail .= ' — kein GITHUB_TOKEN gesetzt';
        }

        return $detail;
    }
}
