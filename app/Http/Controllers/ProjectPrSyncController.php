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
            return back()->with('status', __('flash.pr_sync_none'));
        }

        $message = __('flash.pr_sync_summary', ['merged' => $result['merged'], 'checked' => $result['checked']]);

        if ($result['errors'] > 0) {
            $message .= ' · '.__('flash.pr_sync_errors', ['count' => $result['errors']]).': '.$this->errorDetail($result);
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
            401 => __('flash.pr_sync_hint_401'),
            403 => __('flash.pr_sync_hint_403'),
            404 => __('flash.pr_sync_hint_404'),
        ];

        $codes = array_values(array_unique($result['statuses']));
        sort($codes);

        $parts = array_map(
            fn (int $c) => "HTTP {$c}".(isset($hints[$c]) ? " ({$hints[$c]})" : ''),
            $codes,
        );

        $detail = implode(', ', $parts);

        if ($result['tokenMissing']) {
            $detail .= ' — '.__('flash.pr_sync_no_token');
        }

        return $detail;
    }
}
