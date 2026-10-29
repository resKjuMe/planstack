<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Namen der fehlgeschlagenen Checks und GitHubs mergeable (MERGEABLE / CONFLICTING).
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->json('ci_failed_checks')->nullable()->after('ci_state');
            $table->string('mergeable', 16)->nullable()->after('ci_failed_checks');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn(['ci_failed_checks', 'mergeable']);
        });
    }
};
