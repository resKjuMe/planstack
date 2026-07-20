<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web-Verwaltung der Projektphasen (Anlegen, Umbenennen, Umsortieren, Löschen) —
 * das UI-Pendant zur API unter /api/projects/{project}/phases. Änderungsrechte
 * folgen der „contribute"-Policy (jedes Projektmitglied darf Phasen pflegen),
 * identisch zur API.
 */
class ProjectPhaseController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $phases = $project->phases()->withCount('tasks')->get();

        return view('projects.phases', compact('project', 'phases'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $project->phases()->create([
            'name' => $data['name'],
            // Neue Phase ans Ende: höchste bestehende Position + 1.
            'position' => ((int) $project->phases()->max('position')) + 1,
        ]);

        return back()->with('status', "Phase \"{$data['name']}\" angelegt.");
    }

    public function update(Request $request, Project $project, Phase $phase): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $phase->update(['name' => $data['name']]);

        return back()->with('status', 'Phase umbenannt.');
    }

    /**
     * Verschiebt eine Phase eine Position nach oben/unten, indem die Position mit
     * der benachbarten Phase getauscht wird. Robust gegenüber nicht-lückenlosen
     * Positionswerten, da nur die Reihenfolge (nicht die konkreten Zahlen) zählt.
     */
    public function move(Request $request, Project $project, Phase $phase): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $ordered = $project->phases()->get();
        $index = $ordered->search(fn (Phase $p) => $p->id === $phase->id);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ordered[$swapIndex])) {
            $neighbour = $ordered[$swapIndex];
            [$phase->position, $neighbour->position] = [$neighbour->position, $phase->position];
            $phase->save();
            $neighbour->save();
        }

        return back()->with('status', 'Reihenfolge aktualisiert.');
    }

    public function destroy(Project $project, Phase $phase): RedirectResponse
    {
        $this->authorize('contribute', $project);

        // Tasks der Phase werden gelöst (phase_id → null via FK nullOnDelete),
        // niemals mitgelöscht.
        $phase->delete();

        return back()->with('status', 'Phase gelöscht.');
    }
}
