<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrgStatusTransition;

/**
 * Generates an organisation-specific status-rules block (Markdown) from its
 * configured statuses, transitions and on-enter automations. Appended to the
 * static, server-maintained status-rules text so skills/agents (L2L, MCP) see
 * the ACTUAL per-organisation workflow — labels, roles, allowed transitions and
 * which fields are set automatically — instead of only the hard-coded default.
 *
 * Deliberately German (like the rest of the CLI/MCP/skill surface).
 */
class StatusRules
{
    public static function forOrganization(Organization $organization): string
    {
        $statuses = $organization->statuses()->get();
        if ($statuses->isEmpty()) {
            return '';
        }

        $byId = $statuses->keyBy('id');
        $lines = ['## Status dieser Organisation', ''];

        $lines[] = 'Status in Board-Reihenfolge:';
        foreach ($statuses->where('is_column', true) as $s) {
            $parts = [];
            if ($s->role !== null) {
                $parts[] = 'Rolle '.$s->role->value;
            }
            $parts[] = 'Art '.$s->kind;
            if ($s->wip_limit) {
                $parts[] = 'WIP-Limit '.$s->wip_limit;
            }
            $lines[] = "- `{$s->key}` ({$s->label}) — ".implode(', ', $parts);
        }

        $exceptions = $statuses->where('is_column', false);
        if ($exceptions->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Ausnahme-Status (keine Spalte, Sonderleiste): '
                .$exceptions->map(fn ($s) => "`{$s->key}` ({$s->label})")->implode(', ');
        }

        $transitions = OrgStatusTransition::query()
            ->whereIn('from_status_id', $statuses->pluck('id'))
            ->get()
            ->groupBy('from_status_id');

        if ($transitions->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Erlaubte Statuswechsel (Board-Drag UND API/MCP-Aktionen werden dagegen geprüft; unerlaubt → 409):';
            foreach ($statuses as $from) {
                $tos = ($transitions[$from->id] ?? collect())
                    ->map(fn ($t) => $byId->get($t->to_status_id)?->key)
                    ->filter()
                    ->values();
                if ($tos->isNotEmpty()) {
                    $lines[] = "- `{$from->key}` → ".$tos->map(fn ($k) => "`{$k}`")->implode(', ');
                }
            }
        }

        $automated = $statuses->filter(fn ($s) => ! empty($s->on_enter_effects));
        if ($automated->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Automatische Feldbefüllung beim Eintritt in einen Status:';
            foreach ($automated as $s) {
                $effects = collect($s->on_enter_effects)
                    ->map(function ($e) {
                        $suffix = ! empty($e['only_if_empty']) ? ' (nur wenn leer)' : '';

                        return "{$e['field']}={$e['value']}{$suffix}";
                    })
                    ->implode(', ');
                $lines[] = "- `{$s->key}`: {$effects}";
            }
        }

        // Ereignis-gesteuerte Status-Zuweisungen: Events (POST /events), die einen
        // Zielstatus setzen. MUSS mit in die Regeln, weil sonst eine reine
        // Event-Automations-Änderung den Inhalt (und damit skill_revision / die
        // Drift-Erkennung) nicht verändert — der Skill würde die neue Zuweisung
        // nicht mitbekommen. Ist mindestens eine konfiguriert, treibt der Server
        // den Status aus den Events: der Skill darf dann KEINE direkten
        // status-Calls mehr senden (sie würden die Zuweisung überschreiben).
        $eventStatus = $organization->eventAutomations()
            ->whereNotNull('target_status_id')
            ->get();
        if ($eventStatus->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Ereignis-gesteuerte Status-Zuweisung (Fortschritts-Events setzen den Status):';
            foreach ($eventStatus as $a) {
                $target = $byId->get($a->target_status_id);
                if ($target === null) {
                    continue;
                }
                $lines[] = "- Event `{$a->event->value}` → `{$target->key}` ({$target->label})";
            }
            $lines[] = '';
            $lines[] = '**Der Status dieser Organisation wird ereignisgesteuert gesetzt: '
                .'KEINE direkten `POST /tasks/{id}/status`-Calls (`analyze`/`in_progress`/`in_review`/`done`) '
                .'senden — sie würden die per Event zugewiesenen Status überschreiben. Der Status folgt '
                .'ausschließlich den Fortschritts-Events; nur `claim`/`claim-next`, `pr`, `merge`, `concern` '
                .'und `split` bleiben.**';
        }

        return implode("\n", $lines)."\n";
    }
}
