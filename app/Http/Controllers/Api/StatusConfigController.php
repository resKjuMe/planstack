<?php

namespace App\Http\Controllers\Api;

use App\Support\StatusSegments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/status-config — die ORG-weite Status-Konfiguration (geordnete Statuses
 * inkl. Styling + done/delivered-Flags + Icon + role→key-Map). Der geteilte
 * React-Store lädt sie EINMAL und verwendet sie über die Projektübersicht UND alle
 * Projekt-Unterseiten hinweg (kein erneutes Laden pro Projekt). Pro Org/Locale
 * konstant.
 */
class StatusConfigController extends ApiController
{
    public function __construct(private readonly StatusSegments $segments) {}

    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()?->organization;

        abort_if($organization === null, 403);

        return response()->json($this->segments->configForOrganization($organization));
    }
}
