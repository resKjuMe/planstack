<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stufe, Pflicht-Approvals und Critical-Files-Gruppen aus dem Check „pr-bot / Review Requirement“.
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->json('review_requirement')->nullable()->after('approval_groups');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('review_requirement');
        });
    }
};
