<?php

namespace App\Http\Resources;

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
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'acceptance_criteria' => $this->description_acceptance_criteria,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'display_status' => ($this->x_display_status ?? $this->status)->value,
            'display_status_label' => ($this->x_display_status ?? $this->status)->label(),
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
            'pr_number' => $this->pr_number,
            'pr_url' => $this->x_pr_url ?? null,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'claimed_by_id' => $this->claimed_by_id,
            'claimed_by' => $this->whenLoaded('claimer', fn () => $this->claimer?->name),
            'claimed_at' => $this->claimed_at,
            'merged_at' => $this->merged_at,

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
                'status' => $p->status->value,
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
