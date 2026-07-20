<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Zielorganisation für die Zuordnung bestehender Daten bestimmen.
        //    Es wird die erste (älteste) Organisation verwendet; existiert noch
        //    keine, wird „Clockodo" angelegt. Zusätzlich wird sichergestellt,
        //    dass es eine Organisation „Clockodo" gibt.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null && ! DB::table('organizations')->where('name', 'Clockodo')->exists()) {
            DB::table('organizations')->insert([
                'created_by_id' => $firstUserId,
                'name' => 'Clockodo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $targetOrgId = DB::table('organizations')->orderBy('id')->value('id');

        // Bestehende User ohne Organisation der Zielorganisation zuordnen, damit
        // sie nach der Zugriffssperre (EnsureUserHasOrganization) weiter arbeiten
        // können.
        if ($targetOrgId !== null) {
            DB::table('users')->whereNull('organization_id')->update(['organization_id' => $targetOrgId]);
        }

        $this->addOrganizationColumn('projects', $targetOrgId);
        $this->addOrganizationColumn('teams', $targetOrgId);
    }

    /**
     * Fügt eine NOT-NULL-Spalte organization_id inkl. FK hinzu: erst nullable
     * anlegen, bestehende Zeilen auf die Zielorganisation setzen, dann NOT NULL
     * erzwingen und den Fremdschlüssel ergänzen (vermeidet change() auf einer
     * Spalte mit bestehendem FK).
     */
    private function addOrganizationColumn(string $table, ?int $targetOrgId): void
    {
        Schema::table($table, function (Blueprint $t) {
            $t->unsignedBigInteger('organization_id')->nullable()->after('id');
        });

        if ($targetOrgId !== null) {
            DB::table($table)->whereNull('organization_id')->update(['organization_id' => $targetOrgId]);
        }

        Schema::table($table, function (Blueprint $t) {
            $t->unsignedBigInteger('organization_id')->nullable(false)->change();
        });

        Schema::table($table, function (Blueprint $t) {
            $t->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['projects', 'teams'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['organization_id']);
                $t->dropColumn('organization_id');
            });
        }
    }
};
