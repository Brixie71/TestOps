<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestSuite>
 */
class TestSuiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
