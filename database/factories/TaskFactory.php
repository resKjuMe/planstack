<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by_id' => User::factory(),
            'claimed_by_id' => null,
            'name' => Str::upper($this->faker->bothify('?#')),
            'summary' => rtrim($this->faker->sentence(), '.'),
            'description' => $this->faker->paragraphs(2, true),
            'effort_man_days' => $this->faker->numberBetween(1, 10),
            'effort_story_points' => $this->faker->randomElement([1, 2, 3, 5, 8, 13]),
            'effort_tokens' => $this->faker->numberBetween(10, 500) * 1000,
            'affected_files' => $this->faker->numberBetween(0, 40),
            'status' => $this->faker->randomElement(TaskStatus::cases()),
        ];
    }

    public function claimedBy(User $user): static
    {
        return $this->state(fn () => [
            'claimed_by_id' => $user->id,
            'claimed_at' => now(),
            'status' => TaskStatus::CLAIMED,
        ]);
    }
}
