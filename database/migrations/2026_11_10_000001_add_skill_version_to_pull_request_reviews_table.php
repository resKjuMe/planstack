<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Version des ps-review-Skills, mit der das Review erstellt wurde.
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->string('skill_version', 20)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->dropColumn('skill_version');
        });
    }
};
