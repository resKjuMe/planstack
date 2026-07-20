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

        return implode("\n", $lines)."\n";
    }
}
