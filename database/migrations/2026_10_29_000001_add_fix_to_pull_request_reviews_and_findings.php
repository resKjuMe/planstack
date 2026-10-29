<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix-Lauf je Review: angefordert auf der Detailseite, ausgeführt von ps-review.
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->foreignId('fix_requested_by_id')->nullable()->after('model')->constrained('users')->nullOnDelete();
            $table->timestamp('fix_requested_at')->nullable()->after('fix_requested_by_id');
            $table->timestamp('fix_started_at')->nullable()->after('fix_requested_at');
            $table->timestamp('fix_finished_at')->nullable()->after('fix_started_at');
            $table->string('fix_commit_sha', 40)->nullable()->after('fix_finished_at');
        });

        Schema::table('review_findings', function (Blueprint $table) {
            $table->string('fix_decision', 10)->nullable()->after('message');
            $table->text('fix_note')->nullable()->after('fix_decision');
            $table->string('fix_outcome', 10)->nullable()->after('fix_note');
            $table->text('fix_outcome_note')->nullable()->after('fix_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('review_findings', function (Blueprint $table) {
            $table->dropColumn(['fix_decision', 'fix_note', 'fix_outcome', 'fix_outcome_note']);
        });

        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fix_requested_by_id');
            $table->dropColumn(['fix_requested_at', 'fix_started_at', 'fix_finished_at', 'fix_commit_sha']);
        });
    }
};
