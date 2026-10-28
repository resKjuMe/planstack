<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CI-Zustand des letzten Commits: failed / running / success, null = keine Checks.
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->string('ci_state', 16)->nullable()->after('review_requirement');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('ci_state');
        });
    }
};
