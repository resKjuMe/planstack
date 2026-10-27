<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->timestamp('review_started_at')->nullable()->after('reviewer_id');
            $table->timestamp('review_finished_at')->nullable()->after('review_started_at');
            // Ein gestartetes Review hat noch keine Bewertung.
            $table->string('decision', 20)->nullable()->change();
            $table->unsignedTinyInteger('criticality')->nullable()->change();
            $table->unsignedTinyInteger('ai_reviewability')->nullable()->change();
        });

        DB::table('pull_request_reviews')->update([
            'review_started_at' => DB::raw('created_at'),
            'review_finished_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('pull_request_reviews')->whereNull('review_finished_at')->delete();

        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->string('decision', 20)->nullable(false)->change();
            $table->unsignedTinyInteger('criticality')->nullable(false)->change();
            $table->unsignedTinyInteger('ai_reviewability')->nullable(false)->change();
            $table->dropColumn(['review_started_at', 'review_finished_at']);
        });
    }
};
