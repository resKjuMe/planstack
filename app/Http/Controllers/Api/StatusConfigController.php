<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Support\StatusSegments;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/projects/{project}/status-config — die Org-Status-Konfiguration
 * (geordnete Statuses inkl. Styling + done/delivered-Flags + role→key-Map), die
 * der geteilte React-Store einmalig lädt, um Summary-Balken, -KPIs und -Blocker
 * clientseitig aus den Tasks abzuleiten. Pro Org/Locale konstant → einmalig.
 */
class StatusConfigController extends ApiController
{
    public function __construct(private readonly StatusSegments $segments) {}

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($this->segments->config($project));
    }
}
