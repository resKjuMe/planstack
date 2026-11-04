<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->boolean('in_merge_queue')->default(false)->after('mergeable');
        });
    }

    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('in_merge_queue');
        });
    }
};
