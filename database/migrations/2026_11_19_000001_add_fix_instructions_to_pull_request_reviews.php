<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sonstige Anweisungen aus „Alle Befunde fixen“, gelten für den ganzen Fix-Lauf.
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->text('fix_instructions')->nullable()->after('fix_commit_sha');
        });
    }

    public function down(): void
    {
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->dropColumn('fix_instructions');
        });
    }
};
