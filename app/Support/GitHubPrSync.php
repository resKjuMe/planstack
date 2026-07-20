<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPullRequest;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls the merge status of a project's open PRs from the GitHub REST API and
 * tags merged ones as MERGED. Only tasks that carry a PR number and are not
 * already merged are queried; closed-but-unmerged PRs are left untouched.
 */
class GitHubPrSync
{
    /**
     * @return array{merged: int, checked: int, requests: int, errors: int, tokenMissing: bool, statuses: array<int, int>, failures: array<int, string>}
     */
    public function sync(Project $project): array
    {
        $repo = $project->githubRepo();
        if (! $repo) {
            throw new RuntimeException(__('flash.github_repo_not_configured', ['alias' => $project->alias]));
        }

        $tasks = $project->tasks()
            ->whereNotNull('pr_number')
            ->where('status', '!=', TaskStatus::MERGED->value)
            ->get();

        return $this->syncGrouped(collect([$repo => $tasks]));
    }

    /**
     * Alle Projekte in einem Rutsch. Tasks werden nach Repo statt nach Projekt
     * gruppiert — Projekte mit demselben github_repo landen automatisch in
     * derselben Gruppe, und pro (Repo, PR-Nummer) wird nur eine Anfrage
     * gestellt, egal wie viele Projekte/Tasks auf dieselbe PR verweisen. Für
     * den minütlichen Cronjob gedacht.
     *
     * @return array{merged: int, checked: int, requests: int, errors: int, tokenMissing: bool, statuses: array<int, int>, failures: array<int, string>}
     */
    public function syncAll(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Task> $tasks */
        $tasks = Task::query()
            ->whereNotNull('pr_number')
            ->where('status', '!=', TaskStatus::MERGED->value)
            ->whereHas('project', fn ($q) => $q->whereNotNull('github_repo'))
            ->with('project:id,github_repo')
            ->get();

        return $this->syncGrouped($tasks->groupBy(fn (Task $t) => $t->project->github_repo));
    }

    /**
     * @param  Collection<string, Collection<int, Task>>  $tasksByRepo
     * @return array{merged: int, checked: int, requests: int, errors: int, tokenMissing: bool, statuses: array<int, int>, failures: array<int, string>}
     */
    private function syncGrouped(Collection $tasksByRepo): array
    {
        $token = config('planstack.github_token');
        $result = ['merged' => 0, 'checked' => 0, 'requests' => 0, 'errors' => 0, 'tokenMissing' => empty($token), 'statuses' => [], 'failures' => []];

        if ($tasksByRepo->isEmpty()) {
            return $result;
        }

        $client = $this->client($token);

        foreach ($tasksByRepo as $repo => $tasks) {
            // Mehrere Tasks (ggf. aus verschiedenen Projekten) können dieselbe
            // PR-Nummer desselben Repos tragen — pro (Repo, PR) genau eine
            // Anfrage, das Ergebnis wird auf alle passenden Tasks angewandt.
            foreach ($tasks->groupBy('pr_number') as $prNumber => $tasksForPr) {
                $result['checked'] += $tasksForPr->count();
                $result['requests']++;

                try {
                    $response = $client->get("/repos/{$repo}/pulls/{$prNumber}");
                } catch (ConnectionException $e) {
                    // Connectivity/TLS failures hit every request the same way, so
                    // bail out with one clear message instead of hammering the rest.
                    throw new RuntimeException(
                        __('flash.github_unreachable', ['error' => $e->getMessage()]),
                        previous: $e,
                    );
                }

                if ($response->failed()) {
                    $result['errors']++;
                    $result['statuses'][] = $response->status();
                    foreach ($tasksForPr as $task) {
                        $result['failures'][] = "{$task->name} (#{$prNumber}): HTTP {$response->status()}";
                    }

                    continue;
                }

                $data = $response->json();

                // A PR is merged when GitHub reports merged=true / a merged_at stamp.
                if (empty($data['merged']) && empty($data['merged_at'])) {
                    continue;
                }

                $mergedAt = ! empty($data['merged_at']) ? Carbon::parse($data['merged_at']) : now();
                foreach ($tasksForPr as $task) {
                    $task->update(['status' => TaskStatus::MERGED->value, 'merged_at' => $mergedAt]);

                    TaskPullRequest::updateOrCreate(
                        ['task_id' => $task->id, 'pull_request_id' => $prNumber],
                        [
                            'changed_files' => $data['changed_files'] ?? 0,
                            'additions' => $data['additions'] ?? 0,
                            'deletions' => $data['deletions'] ?? 0,
                            'commits' => $data['commits'] ?? 0,
                            'comments' => $data['comments'] ?? 0,
                            'review_comments' => $data['review_comments'] ?? 0,
                            'opened_at' => ! empty($data['created_at']) ? Carbon::parse($data['created_at']) : null,
                            'merged_at' => $mergedAt,
                        ],
                    );

                    $result['merged']++;
                }
            }
        }

        return $result;
    }

    private function client(?string $token): PendingRequest
    {
        $verify = config('planstack.github_verify_ssl', true);

        return Http::baseUrl(rtrim((string) config('planstack.github_api'), '/'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'planstack',
            ])
            ->withOptions(['verify' => $verify])
            ->when($token, fn (PendingRequest $c) => $c->withToken($token))
            ->timeout(15)
            ->retry(2, 250, throw: false);
    }
}
