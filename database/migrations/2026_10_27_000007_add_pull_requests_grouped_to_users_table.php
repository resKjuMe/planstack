<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // PR-Liste in Gruppen (laufend, Review nötig, …) oder flach.
            $table->boolean('pull_requests_grouped')->default(true)->after('notification_display');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pull_requests_grouped');
        });
    }
};
