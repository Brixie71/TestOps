<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Project;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The owner_id field is the important part: using User::factory()
            // tells Laravel to create an owner automatically whenever a project is created through this factory.

            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('PRJ-####'),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }
}
