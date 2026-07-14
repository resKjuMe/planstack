<?php

use App\Support\SkillTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the new skill_description field with the bundled default skill text
     * (with {{alias}}/{{name}} placeholders) for every project that has none yet.
     * The placeholders are only resolved at download time.
     */
    public function up(): void
    {
        $default = SkillTemplate::default();

        if ($default === '') {
            return;
        }

        DB::table('projects')
            ->where(function ($q) {
                $q->whereNull('skill_description')->orWhere('skill_description', '');
            })
            ->update(['skill_description' => $default]);
    }

    public function down(): void
    {
        // Non-destructive seed; nothing to roll back.
    }
};
