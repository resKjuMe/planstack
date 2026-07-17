<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaskChecklistItem>
 */
class TaskChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'kind' => 'acceptance',
            'role' => 'item',
            'position' => 0,
            'text' => rtrim($this->faker->sentence(), '.'),
            'checked' => false,
            'checked_by_id' => null,
            'checked_at' => null,
        ];
    }
}
