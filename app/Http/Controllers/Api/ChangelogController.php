<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Support\ChangelogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/projects/{project}/changelog?page=N — der paginierte Changelog-Feed.
 * Der React-Store lädt Seite 1 beim ersten Öffnen und cacht sie über die
 * Navigation; „Mehr laden" holt weitere Seiten, ein entity-changed-Event setzt auf
 * die (neueste) Seite 1 zurück. Die Aufbereitung bleibt serverseitig.
 */
class ChangelogController extends ApiController
{
    public function __construct(private readonly ChangelogPresenter $presenter) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $page = max(1, (int) $request->query('page', 1));

        return response()->json($this->presenter->payload($project, $page));
    }
}
