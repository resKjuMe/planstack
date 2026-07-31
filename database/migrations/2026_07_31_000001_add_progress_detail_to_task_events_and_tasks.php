<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fortschritt am Fortschritts-Event: bisher meldete der Skill nur, WELCHES Event
 * eintrat (`PROCESSING`), nicht wie weit er darin ist. Der Detailgrad lebte
 * ausschliesslich in der lokalen Sticky-Statuszeile — sichtbar nur im Fenster des
 * laufenden Workers, weg beim naechsten Aufruf.
 *
 * `detail` (Freitext, z. B. "4/9 Dateien: TaskController.php") und `progress`
 * (0–100) machen daraus eine serverseitige, dauerhafte und fuer alle sichtbare
 * Angabe.
 *
 * Zweigleisig, mit Absicht:
 *  - task_events bekommt beide Felder → vollstaendige Historie je Event.
 *  - tasks bekommt denselben Stand denormalisiert (progress_*) → das Board zeigt
 *    ihn ohne Join/N+1 auf jeder Karte, genau wie schon bei den pr_*-Feldern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->string('detail', 200)->nullable()->after('event');
            $table->unsignedTinyInteger('progress')->nullable()->after('detail');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('progress_detail', 200)->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->timestamp('progress_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->dropColumn(['detail', 'progress']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['progress_detail', 'progress_percent', 'progress_at']);
        });
    }
};
