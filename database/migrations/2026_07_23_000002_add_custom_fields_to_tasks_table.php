<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Werte der benutzerdefinierten Felder (siehe custom_fields) je Task, als JSON-
 * Objekt keyed by custom_fields.key. Wird über die API befüllt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('affected_files');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
