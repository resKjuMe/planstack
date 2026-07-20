<?php

use App\Models\Organization;
use App\Support\DefaultTaskStatuses;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the expanded default transition graph (adds CLAIMED→IN_REVIEW and
 * IN_REVIEW→MERGED, which the wired done/merge actions traverse) onto existing
 * organizations, so the new transition check on the API/MCP actions does not
 * reject standard L2L flows. Only adds missing edges; custom edges are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Organization::query()->each(function (Organization $organization) {
            DefaultTaskStatuses::syncDefaultTransitions($organization);
        });
    }

    public function down(): void
    {
        // Additive backfill; leaving the edges in place is harmless.
    }
};
