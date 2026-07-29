<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sichtbarkeit für parallel arbeitende Worker-Sessions. claimed_by_id sagt nur,
     * WELCHER NUTZER einen Task hält — laufen mehrere Agenten-Sessions unter
     * demselben Token (der Normalfall beim ferngesteuerten Abarbeiten), fallen sie
     * auf dem Board in eine Zeile zusammen.
     *
     * claim_session_label ist ein sprechender Name der haltenden Session, den der
     * Client per Header X-Planstack-Session mitschickt. claim_seen_at ist der
     * Heartbeat: er wird bei jedem task-bezogenen API-Zugriff DIESER Session
     * aufgefrischt. Anders als der Claim selbst (der ohne explizites release ewig
     * stehen bleibt) macht der Heartbeat einen hart gekillten Worker erkennbar —
     * die Anzeige leitet „verwaist" clientseitig aus dem Alter ab (TTL:
     * planstack.claim_session_ttl_minutes), es braucht also keinen Cleanup-Job.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('claim_session_label', 60)->nullable()->after('claimed_at');
            $table->timestamp('claim_seen_at')->nullable()->after('claim_session_label');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['claim_session_label', 'claim_seen_at']);
        });
    }
};
