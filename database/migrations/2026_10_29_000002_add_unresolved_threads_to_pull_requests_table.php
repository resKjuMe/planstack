<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anzahl ungelöster Review-Threads.
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('unresolved_threads')->nullable()->after('mergeable');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('unresolved_threads');
        });
    }
};
