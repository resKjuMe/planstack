<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Generischer Demo-Stand: ein Owner-Login und ein kleines Beispielboard,
     * damit die App nach `db:seed` sofort mit Inhalt läuft.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@planstack.test',
            'password' => Hash::make(env('SEED_OWNER_PASSWORD', 'password')),
        ]);

        // Zugriff wird über die Team-Zuordnung des Projekts gewährt.
        $team = Team::create(['created_by_id' => $owner->id, 'name' => 'Demo Team']);
        $team->members()->attach([$owner->id]);

        $project = Project::factory()->create([
            'created_by_id' => $owner->id,
            'alias' => 'DEMO',
            'name' => 'Beispielprojekt',
            'description' => 'Kleines Demo-Board für den ersten Eindruck.',
        ]);

        $project->teams()->attach($team->id);
        $project->members()->attach($owner->id, ['role' => ProjectRole::ADMIN->value]);

        $phase = Phase::create(['project_id' => $project->id, 'name' => 'P1 · Start', 'position' => 1]);

        $a = Task::factory()->for($project)->create([
            'created_by_id' => $owner->id,
            'name' => 'A1',
            'summary' => 'Erster Task',
            'phase_id' => $phase->id,
            'status' => TaskStatus::MERGED,
            'merged_at' => now(),
        ]);

        Task::factory()->for($project)->create([
            'created_by_id' => $owner->id,
            'name' => 'A2',
            'summary' => 'Baut auf A1 auf',
            'phase_id' => $phase->id,
            'status' => TaskStatus::PICKABLE,
        ])->prerequisites()->sync([$a->id]);
    }
}
