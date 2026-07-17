<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Support\TaskContentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Abhakbare Checklisten eines Tasks: Akzeptanzkriterien (`kind=acceptance`) und
 * Testschritte (`kind=test`). Ein Toggle-Endpoint (Optimistic UI) und ein
 * Convert-Endpoint, der bestehende Freitext-Prosa einmalig in Items zerlegt.
 * Autorisierung über die Task-Ability `update` (Projektmitglied), wie beim
 * Concern.
 */
class TaskChecklistController extends Controller
{
    /**
     * PATCH .../checklist/{item} — Häkchen setzen/entfernen. Gibt JSON zurück,
     * damit die Detailseite optimistisch aktualisieren und bei Fehler zurückrollen
     * kann.
     */
    public function toggle(Request $request, Project $project, Task $task, TaskChecklistItem $checklistItem): JsonResponse
    {
        $this->authorize('update', $task);

        $item = $checklistItem;

        if (! $item->isCheckable()) {
            abort(422, 'Dieses Item kann nicht abgehakt werden.');
        }

        $data = $request->validate([
            'checked' => ['required', 'boolean'],
        ]);

        $item->update([
            'checked' => $data['checked'],
            'checked_by_id' => $data['checked'] ? $request->user()->id : null,
            'checked_at' => $data['checked'] ? now() : null,
        ]);

        return response()->json([
            'checked' => $item->checked,
            'checked_by' => $item->checked ? $request->user()->name : null,
            'checked_at' => $item->checked_at?->format('d.m.Y H:i'),
            'progress' => $this->progress($task, $item->kind),
        ]);
    }

    /**
     * POST .../checklist/convert — Freitext eines Feldes in Checklisten-Items
     * zerlegen. Nur wenn für dieses `kind` noch keine Items existieren
     * (nicht-destruktiv, idempotent).
     */
    public function convert(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'kind' => ['required', 'in:acceptance,test'],
        ]);
        $kind = $data['kind'];

        if ($task->checklistItems()->where('kind', $kind)->exists()) {
            return back()->with('status', 'Checkliste besteht bereits.');
        }

        $source = $kind === 'acceptance'
            ? $task->description_acceptance_criteria
            : $task->description_test_cases;

        $items = TaskContentParser::checklist((string) $source, $kind);

        if ($items === []) {
            return back()->with('error', 'Kein Text zum Umwandeln gefunden.');
        }

        foreach ($items as $i => $parsed) {
            $task->checklistItems()->create([
                'kind' => $kind,
                'role' => $parsed['role'],
                'position' => $i,
                'text' => $parsed['text'],
            ]);
        }

        $label = $kind === 'acceptance' ? 'Akzeptanzkriterien' : 'Testanleitung';

        return back()->with('status', "{$label} in Checkliste umgewandelt.");
    }

    /**
     * Fortschritt einer Checkliste: abgehakte / abhakbare Items eines `kind`.
     *
     * @return array{done: int, total: int}
     */
    private function progress(Task $task, string $kind): array
    {
        $items = $task->checklistItems()->where('kind', $kind)->get();
        $checkable = $items->filter(fn (TaskChecklistItem $i) => $i->isCheckable());

        return [
            'done' => $checkable->where('checked', true)->count(),
            'total' => $checkable->count(),
        ];
    }
}
