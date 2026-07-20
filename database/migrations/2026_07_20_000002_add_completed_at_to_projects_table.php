<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Gesetzt ⇒ Projekt ist abgeschlossen: zeigt in der Übersicht das
            // Badge „Abgeschlossen" (statt der berechneten Kategorie) und ist
            // über die Filter-Pill „Abgeschlossen" filterbar. Unabhängig von
            // archived_at (Ausblenden).
            $table->timestamp('completed_at')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
