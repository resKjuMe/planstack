<?php

use App\Models\Organization;
use App\Support\DefaultTaskStatuses;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the new IN_REVIEW default on-enter effect (set reviewed_by = current
 * user) onto existing organizations. applyDefaultEffects only fills statuses
 * whose effects are still null, so the earlier-seeded CLAIMED/PICKABLE/MERGED
 * effects are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Organization::query()->each(function (Organization $organization) {
            DefaultTaskStatuses::applyDefaultEffects($organization);
        });
    }

    public function down(): void
    {
        // Irreversible data backfill.
    }
};
