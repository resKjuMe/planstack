<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GitHub-Login, dessen Changes-Requested-Review in dieses Review übernommen wurde.
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->string('converted_from_login', 100)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('pull_request_reviews', function (Blueprint $table) {
            $table->dropColumn('converted_from_login');
        });
    }
};
