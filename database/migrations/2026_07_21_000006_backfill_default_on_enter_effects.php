<?php

use App\Models\Organization;
use App\Support\DefaultTaskStatuses;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the default on-enter effects (claim → assignee/claimed_at, release →
 * clear, merge → merged_at) onto every existing organization's role-bearing
 * statuses. Runs after the on_enter_effects column is added (000005) and after
 * the initial seed (000002, which no longer writes effects because the column
 * did not exist yet). Never overwrites statuses that already have effects set.
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
        // Irreversible data backfill; leaving effects in place is harmless.
    }
};
