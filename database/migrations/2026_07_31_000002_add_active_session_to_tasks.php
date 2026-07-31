<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche Skill-Session arbeitet gerade an dieser Aufgabe — fuer JEDE Ausfuehrung,
 * unabhaengig von Claim, Review-Reservierung oder Fix-Lease.
 *
 * Bisher gab es nur `claim_session_label`, und das haengt am Claim
 * (ClaimSession::stamp() greift nur, wenn `claimed_by_id` mitgeschrieben wird).
 * Damit blieb jede Ausfuehrung unsichtbar, die nicht claimt:
 *
 *  - `fix` arbeitet am offenen PR einer Aufgabe, die oft jemand ANDERES haelt,
 *  - `review` reserviert ueber `reviewed_by`,
 *  - `work` auf einem bereits geclaimten Task claimt nicht erneut.
 *
 * Beobachtbare Folge: eine laufende Fix-Session zeigt in ihrer Statuszeile
 * „Fix (Fix 75 %) TXSAFE · GAP-MassRun", das Board zeigt an derselben Karte
 * nichts — sie sieht unbearbeitet aus, waehrend ein Worker daran arbeitet.
 *
 * Deshalb eigene Felder statt Umdeutung von claim_session_label: das Claim-Lease
 * gehoert dem Claim-Inhaber und darf von einer fremden Session nicht ueberschrieben
 * werden. Gefuellt wird automatisch aus dem Header X-Planstack-Session (siehe
 * TrackClaimSession) — der Skill muss dafuer nichts zusaetzlich melden, und es gibt
 * keinen Pfad, auf dem der Vermerk vergessen wird.
 *
 * Ein Ende-Signal gibt es nicht (eine Session kann jederzeit wegbrechen), deshalb
 * wie beim Claim-Heartbeat: `..._seen_at` traegt den letzten Zugriff, „verwaist"
 * leitet die Anzeige aus dem Alter ab (planstack.claim_session_ttl_minutes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('active_session_label', 60)->nullable()->after('claim_seen_at');
            $table->timestamp('active_session_seen_at')->nullable()->after('active_session_label');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['active_session_label', 'active_session_seen_at']);
        });
    }
};
