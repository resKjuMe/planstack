<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->longText('description_target_actual')->nullable()->after('description_acceptance_criteria');
            $table->longText('description_test_cases')->nullable()->after('description_target_actual');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['description_target_actual', 'description_test_cases']);
        });
    }
};
