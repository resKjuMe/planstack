<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default icon per canonical status key — mirrors
     * App\Support\DefaultTaskStatuses. Applied to existing rows by key; custom
     * statuses (no match) stay null until the owner picks one.
     *
     * @var array<string, string>
     */
    private const DEFAULT_ICONS = [
        'PICKABLE' => 'inbox',
        'CLAIMED' => 'user-check',
        'ANALYZING' => 'search',
        'IN_PROGRESS' => 'hammer',
        'IN_REVIEW' => 'eye',
        'MERGED' => 'git-merge',
        'COMPLETED' => 'circle-check',
        'BLOCKED' => 'octagon-x',
        'CONCERNED' => 'triangle-alert',
    ];

    public function up(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('color_token');
        });

        foreach (self::DEFAULT_ICONS as $key => $icon) {
            DB::table('task_statuses')->where('key', $key)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
