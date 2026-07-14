<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PhaseResource;
use App\Models\Phase;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhaseController extends ApiController
{
    /**
     * GET /api/projects/{project}/phases — phases ordered by position.
     */
    public function index(Project $project): JsonResource
    {
        $this->authorize('view', $project);

        return PhaseResource::collection($project->phases);
    }

    /**
     * POST /api/projects/{project}/phases — create a phase bound to the project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $phase = $project->phases()->create([
            'name' => $data['name'],
            'position' => $data['position'] ?? (((int) $project->phases()->max('position')) + 1),
        ]);

        return (new PhaseResource($phase))->response()->setStatusCode(201);
    }

    /**
     * PUT|PATCH /api/projects/{project}/phases/{phase} — rename/reposition a phase.
     */
    public function update(Request $request, Project $project, Phase $phase): JsonResource
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'position' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $phase->update($data);

        return new PhaseResource($phase);
    }

    /**
     * DELETE /api/projects/{project}/phases/{phase} — remove a phase.
     *
     * Tasks in the phase are detached (phase_id → null via the FK's
     * nullOnDelete), never deleted along with it.
     */
    public function destroy(Project $project, Phase $phase): JsonResponse
    {
        $this->authorize('contribute', $project);

        $phase->delete();

        return response()->json(status: 204);
    }
}
