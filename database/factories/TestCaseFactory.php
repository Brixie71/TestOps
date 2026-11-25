<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCase>
 */
class TestCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The suite is created inside the case's project so the pair stays
     * consistent: project_id resolves before test_suite_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'test_suite_id' => fn (array $attributes) => TestSuite::factory()->create([
                'project_id' => $attributes['project_id'],
            ])->getKey(),
            'sequence' => fake()->unique()->numberBetween(1, 60000),
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'status' => TestCase::STATUS_DRAFT,
            'severity' => TestCase::SEVERITY_MAJOR,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Mark the case as runnable.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => TestCase::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark the case as retired.
     */
    public function deprecated(): static
    {
        return $this->state(fn () => [
            'status' => TestCase::STATUS_DEPRECATED,
        ]);
    }

    /**
     * Give the case a high severity.
     */
    public function critical(): static
    {
        return $this->state(fn () => [
            'severity' => TestCase::SEVERITY_CRITICAL,
        ]);
    }
}
