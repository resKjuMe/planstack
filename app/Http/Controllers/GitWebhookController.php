<?php

namespace App\Http\Controllers;

use App\Models\GitWebhookEvent;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nimmt eingehende GitHub-Webhooks entgegen (POST /hooks/git) und protokolliert
 * sie in `git_webhook_events`. Vorerst wird ausschließlich geloggt – Repo und
 * PR-Nummer werden, wo aus der Nutzlast ableitbar, best-effort zum passenden
 * Task/Projekt aufgelöst. Die eigentliche Ereignisverarbeitung (Task-Status,
 * Automationen) folgt in einem späteren Schritt.
 */
class GitWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Signatur nur prüfen, wenn ein Secret konfiguriert ist. In der reinen
        // Log-Phase soll ein fehlendes Secret den Empfang nicht blockieren.
        if (! $this->signatureValid($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $event = (string) $request->header('X-GitHub-Event', 'unknown');
        $action = isset($payload['action']) && is_scalar($payload['action'])
            ? (string) $payload['action']
            : null;
        $repository = data_get($payload, 'repository.full_name');
        $prNumber = $this->extractPrNumber($event, $payload);

        [$project, $task] = $this->resolve($repository, $prNumber);

        GitWebhookEvent::create([
            'event' => $event,
            'action' => $action,
            'delivery_id' => $request->header('X-GitHub-Delivery'),
            'repository' => is_string($repository) ? $repository : null,
            'pr_number' => $prNumber,
            'project_id' => $project?->id,
            'task_id' => $task?->id,
            'payload' => $payload,
        ]);

        // 202: angenommen und protokolliert; Verarbeitung erfolgt (später) asynchron.
        return response()->json(['status' => 'logged'], 202);
    }

    /**
     * HMAC-SHA256-Signatur (X-Hub-Signature-256) gegen das konfigurierte Secret.
     * Ohne konfiguriertes Secret wird nicht geprüft (Log-Phase).
     */
    private function signatureValid(Request $request): bool
    {
        $secret = config('planstack.github_webhook_secret');
        if (empty($secret)) {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    /**
     * PR-Nummer je nach Ereignistyp aus der Nutzlast ziehen. Ereignisse ohne
     * PR-Bezug (push, create, delete, workflow_job, merge_group) liefern null.
     */
    private function extractPrNumber(string $event, array $payload): ?int
    {
        $number = match ($event) {
            'pull_request' => data_get($payload, 'pull_request.number'),
            'pull_request_review',
            'pull_request_review_comment',
            'pull_request_review_thread' => data_get($payload, 'pull_request.number'),
            // issue_comment betrifft nur dann eine PR, wenn issue.pull_request gesetzt ist.
            'issue_comment' => data_get($payload, 'issue.pull_request')
                ? data_get($payload, 'issue.number')
                : null,
            'workflow_run' => data_get($payload, 'workflow_run.pull_requests.0.number'),
            default => null,
        };

        return is_numeric($number) ? (int) $number : null;
    }

    /**
     * Best-effort-Auflösung von Repo + PR-Nummer zu Task/Projekt. Der Task wird
     * über (github_repo, pr_number) gesucht; das Projekt fällt auf das erste
     * Repo-Match zurück, damit auch Ereignisse ohne PR (push, CI) ein Projekt
     * tragen.
     *
     * @return array{0: ?Project, 1: ?Task}
     */
    private function resolve(mixed $repository, ?int $prNumber): array
    {
        if (! is_string($repository) || $repository === '') {
            return [null, null];
        }

        $task = null;
        if ($prNumber !== null) {
            $task = Task::query()
                ->where('pr_number', $prNumber)
                ->whereHas('project', fn ($q) => $q->where('github_repo', $repository))
                ->with('project')
                ->first();
        }

        $project = $task?->project ?? Project::query()->where('github_repo', $repository)->first();

        return [$project, $task];
    }
}
