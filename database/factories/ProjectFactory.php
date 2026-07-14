<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by_id' => User::factory(),
            'alias' => Str::upper($this->faker->unique()->lexify('???')),
            'name' => rtrim($this->faker->catchPhrase(), '.'),
            'description' => $this->faker->paragraph(),
        ];
    }
}
