<?php

namespace App\Http\Resources;

use App\Http\Middleware\AttachPlanstackConfig;
use App\Models\Task;
use App\Support\TaskBoardService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 *
 * Consistent JSON for a Task, including the computed board fields
 * (gate, stacking, pickable, unlocks, pr_url) that are present when the
 * task has been decorated by {@see TaskBoardService}.
 *
 * The field set is trimmed by the per-project `task.fields` config
 * (minimal | standard | full) — a server-enforced token knob the client
 * never has to ask about.
 */
class TaskResource extends JsonResource
{
    /** Keys kept for `task.fields=minimal` — just enough to pick & work a task. */
    private const MINIMAL = [
        'id', 'name', 'summary', 'acceptance_criteria', 'status',
        'pickable', 'unlocks', 'gate', 'unmet',
    ];

    /**
     * When true, `pr_number`/`pr_url` are always included, even under
     * `task.fields=minimal`. Set for review responses, which must carry the PR
     * regardless of the project's token-saving field config.
     */
    public bool $alwaysIncludePr = false;

    /** Extra keys added for `task.fields=standard`. */
    private const STANDARD_EXTRA = [
        'display_status', 'phase_id', 'effort', 'pr_number', 'pr_url',
        'claimed_by_id', 'prerequisites', 'concern', 'stacking',
        'last_reviewed_at', 'last_review_recommendation', 'last_review_summary',
        'target_actual', 'test_cases', 'criticality', 'criticality_label',
        'custom_fields',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $full = $this->fullArray($request);

        // Request-Override: ?fields=minimal|standard|full erzwingt den Feldumfang
        // unabhängig vom Projekt-Knopf `task.fields` — so lassen sich die vollen
        // Details eines Tasks gezielt abrufen, egal wie sparsam das Projekt steht.
        $override = $request->query('fields');
        $fields = in_array($override, ['minimal', 'standard', 'full'], true)
            ? $override
            : AttachPlanstackConfig::value($request, 'task.fields');

        $result = match ($fields) {
            'minimal' => array_intersect_key($full, array_flip(self::MINIMAL)),
            'standard' => array_intersect_key(
                $full,
                array_flip([...self::MINIMAL, ...self::STANDARD_EXTRA]),
            ),
            default => $full,
        };

        // Review-Antworten tragen die PR immer mit — unabhängig von task.fields.
        if ($this->alwaysIncludePr) {
            $result['pr_number'] = $full['pr_number'];
            $result['pr_url'] = $full['pr_url'];
        }

        return $result;
    }

    /**
     * The full, un-trimmed representation.
     *
     * @return array<string, mixed>
     */
    private function fullArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'acceptance_criteria' => $this->description_acceptance_criteria,
            'target_actual' => $this->description_target_actual,
            'test_cases' => $this->description_test_cases,
            'criticality' => $this->criticality?->value,
            'criticality_label' => $this->criticality?->label(),
            // status may be null when the task sits in a custom (org-defined)
            // status with no canonical ENUM value — fall back to the org status
            // key/label (status_id is the authority).
            'status' => $this->status?->value ?? $this->orgStatus?->key,
            'status_label' => $this->status?->label() ?? $this->orgStatus?->label,
            'display_status' => ($this->x_display_status ?? $this->status)?->value ?? $this->orgStatus?->key,
            'display_status_label' => ($this->x_display_status ?? $this->status)?->label() ?? $this->orgStatus?->label,
            'phase_id' => $this->phase_id,
            'phase' => $this->whenLoaded('phase', fn () => [
                'id' => $this->phase?->id,
                'name' => $this->phase?->name,
                'position' => $this->phase?->position,
            ]),
            'effort' => [
                'man_days' => $this->effort_man_days !== null ? (float) $this->effort_man_days : null,
                'story_points' => $this->effort_story_points,
                'tokens' => $this->effort_tokens,
            ],
            'affected_files' => $this->affected_files,
            'custom_fields' => $this->custom_fields ?? null,
            'pr_number' => $this->pr_number,
            'pr_url' => $this->x_pr_url ?? null,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'claimed_by_id' => $this->claimed_by_id,
            'claimed_by' => $this->whenLoaded('claimer', fn () => $this->claimer?->name),
            'claimed_at' => $this->claimed_at,
            'merged_at' => $this->merged_at,
            'last_reviewed_at' => $this->last_reviewed_at,
            'last_review_recommendation' => $this->last_review_recommendation?->value,
            'last_review_summary' => $this->last_review_summary,

            // Computed board fields — present once the task is decorated.
            'gate' => $this->x_gate ?? null,
            'stacking' => $this->x_stacking ?? null,
            'pickable' => $this->when(isset($this->x_pickable), fn () => (bool) $this->x_pickable),
            'unlocks' => $this->x_unlocks ?? null,
            'unmet' => $this->x_unmet ?? null,
            'color' => $this->x_class ?? null,

            'prerequisites' => $this->whenLoaded('prerequisites', fn () => $this->prerequisites->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status?->value ?? $p->orgStatus?->key,
            ])->values()),

            'concern' => $this->whenLoaded('concern', fn () => $this->concern ? [
                'summary' => $this->concern->summary,
                'context' => $this->concern->description_context,
                'blocker' => $this->concern->description_blocker,
                'misconception' => $this->concern->description_misconception,
                'decisions' => $this->concern->description_decisions,
            ] : null),
        ];
    }
}
