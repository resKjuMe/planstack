<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Phase>
 */
class PhaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'Phase '.$this->faker->unique()->numberBetween(1, 99),
            'position' => $this->faker->numberBetween(0, 10),
        ];
    }
}
