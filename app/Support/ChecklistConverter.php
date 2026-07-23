<?php

namespace App\Support;

use App\Models\Task;

/**
 * Wandelt den Freitext eines Task-Feldes (Akzeptanzkriterien/Testanleitung)
 * einmalig in abhakbare Checklisten-Items um. Nicht-destruktiv und idempotent:
 * es wird NUR konvertiert, wenn für das `kind` noch keine Items existieren.
 *
 * Geteilt vom expliziten Convert-Endpoint und der Auto-Konvertierung beim
 * Anzeigen der Task-Detailseite (die Checkliste ist immer eine Checkliste).
 */
class ChecklistConverter
{
    /**
     * Stellt sicher, dass für $kind Checklisten-Items existieren, indem der
     * zugehörige Freitext bei Bedarf zerlegt und persistiert wird.
     *
     * @return bool true, wenn Items neu erzeugt wurden.
     */
    public static function ensure(Task $task, string $kind): bool
    {
        if ($task->checklistItems()->where('kind', $kind)->exists()) {
            return false;
        }

        $source = $kind === 'acceptance'
            ? $task->description_acceptance_criteria
            : $task->description_test_cases;

        $items = TaskContentParser::checklist((string) $source, $kind);
        if ($items === []) {
            return false;
        }

        foreach ($items as $i => $parsed) {
            $task->checklistItems()->create([
                'kind' => $kind,
                'role' => $parsed['role'],
                'position' => $i,
                'text' => $parsed['text'],
            ]);
        }

        return true;
    }
}
