<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestRun>
 */
class TestRunFactory extends Factory
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
            'sequence' => fake()->unique()->numberBetween(1, 60000),
            'name' => 'Run '.fake()->date(),
            'status' => TestRun::STATUS_OPEN,
            'created_by' => User::factory(),
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * Mark the run as finished.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TestRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the run as abandoned before finishing.
     */
    public function aborted(): static
    {
        return $this->state(fn () => [
            'status' => TestRun::STATUS_ABORTED,
            'completed_at' => now(),
        ]);
    }
}
