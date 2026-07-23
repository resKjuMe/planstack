<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use App\Support\ProjectEditTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Web-Verwaltung der Projektphasen (Anlegen, Umbenennen, Umsortieren, Löschen) —
 * das UI-Pendant zur API unter /api/projects/{project}/phases. Änderungsrechte
 * folgen der „contribute"-Policy (jedes Projektmitglied darf Phasen pflegen),
 * identisch zur API.
 */
class ProjectPhaseController extends Controller
{
    public function index(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $phases = $project->phases()->withCount('tasks')->get();

        return Inertia::render('ProjectPhases', [
            'project' => ['alias' => $project->alias],
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'editTabs' => ProjectEditTabs::for($project, 'phases'),
            'canContribute' => auth()->user()->can('contribute', $project),
            'phases' => $phases->map(fn (Phase $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'tasksCount' => $p->tasks_count,
            ])->values(),
            'urls' => [
                'store' => route('projects.phases.store', $project),
                'update' => route('projects.phases.update', [$project, '__ID__']),
                'move' => route('projects.phases.move', [$project, '__ID__']),
                'destroy' => route('projects.phases.destroy', [$project, '__ID__']),
            ],
            'strings' => [
                'editTitle' => __('projects.edit_project'),
                'phasesTitle' => __('common.phases'),
                'showHideExplanation' => __('common.show_hide_explanation'),
                'helpBullets' => [
                    ['text' => __('projects.phases_group_tasks_into_sections_e_g')],
                    ['strong' => __('common.create'), 'text' => __('projects.adds_a_new_phase_at_the_end_of_the_list')],
                    ['strong' => __('projects.arrows'), 'text' => __('projects.change_the_order')],
                    ['strong' => __('common.delete'), 'text' => __('projects.removes_only_the_phase_the_contained')],
                ],
                'phasesCount' => __('projects.phases_count'),
                'noPhases' => __('projects.no_phases_created_yet'),
                'newPhase' => __('projects.new_phase'),
                'placeholder' => __('projects.e_g_foundation'),
                'create' => __('common.create'),
                'save' => __('common.save'),
                'cancel' => __('common.cancel'),
                'edit' => __('common.edit'),
                'delete' => __('common.delete'),
                'moveUp' => __('projects.move_up'),
                'moveDown' => __('projects.move_down'),
                'deleteConfirm' => __('projects.delete_phase_name_the_count_contained'),
                'tasksSuffix' => __('common.tasks'),
            ],
        ]);
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

        return back()->with('status', __('flash.phase_created', ['name' => $data['name']]));
    }

    public function update(Request $request, Project $project, Phase $phase): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $phase->update(['name' => $data['name']]);

        return back()->with('status', __('flash.phase_renamed'));
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

        return back()->with('status', __('flash.order_updated'));
    }

    public function destroy(Project $project, Phase $phase): RedirectResponse
    {
        $this->authorize('contribute', $project);

        // Tasks der Phase werden gelöst (phase_id → null via FK nullOnDelete),
        // niemals mitgelöscht.
        $phase->delete();

        return back()->with('status', __('flash.phase_deleted'));
    }
}
