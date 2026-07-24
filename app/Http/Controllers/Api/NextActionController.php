<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\NextActionResolver;
use App\Support\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POST /api/projects/{project}/next-action — decide the next worthwhile action
 * for the token user and reserve its task atomically, in one call. Powers
 * "/planstack auto": the caller learns {action, task} up front (so a subagent can
 * be named e.g. "fix RPm-req") instead of the client deciding review/fix/work.
 *
 * Priority fix → review → work (see {@see NextActionResolver}). The response is
 * the reserved task (decorated, PR always included) under `data`, plus top-level
 * `action` and `reason`. Returns 200 `{"action": "none"}` when nothing is due.
 */
class NextActionController extends ApiController
{
    public function __construct(
        private readonly NextActionResolver $resolver,
        private readonly TaskBoardService $board,
    ) {}

    public function __invoke(Request $request, Project $project): JsonResource|JsonResponse
    {
        $this->authorize('contribute', $project);

        $result = $this->resolver->resolve($project, $request->user());

        if ($result['action'] === 'none' || $result['task'] === null) {
            return response()->json(['action' => 'none']);
        }

        $resource = new TaskResource($this->decorate($project, $result['task']));
        // Der auto-Flow muss den PR adressieren können (fix/review) — PR immer mitgeben.
        $resource->alwaysIncludePr = true;

        return $resource->additional([
            'action' => $result['action'],
            'reason' => $result['reason'],
        ]);
    }

    /**
     * Load the reserved task with the same board decoration/relations the other
     * task endpoints return, so the worker has enough to start without a re-read.
     */
    private function decorate(Project $project, Task $task): Task
    {
        $decorated = $this->board->board($project)->firstWhere('id', $task->id) ?? $task;
        $decorated->loadMissing(['phase', 'claimer', 'concern', 'reviewer', 'prerequisites.orgStatus', 'pullRequests']);

        return $decorated;
    }
}
