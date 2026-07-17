<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alias' => $this->alias,
            'name' => $this->name,
            'description' => $this->description,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
            ]),
            'is_owner' => $this->when(
                $request->user() !== null,
                fn () => $this->isOwner($request->user()),
            ),
            'role' => $this->when(
                $request->user() !== null,
                fn () => $this->roleFor($request->user())?->value,
            ),
            'tasks_count' => $this->whenCounted('tasks'),
            'config_version' => $this->config_version,
            'phases' => PhaseResource::collection($this->whenLoaded('phases')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
