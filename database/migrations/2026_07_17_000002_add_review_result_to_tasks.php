<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('last_reviewed_at')->nullable()->after('reviewed_by');
            // APPROVE | REQUEST_CHANGES (siehe App\Enums\ReviewRecommendation).
            $table->string('last_review_recommendation')->nullable()->default(null)->after('last_reviewed_at');
            $table->text('last_review_summary')->nullable()->after('last_review_recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['last_reviewed_at', 'last_review_recommendation', 'last_review_summary']);
        });
    }
};
