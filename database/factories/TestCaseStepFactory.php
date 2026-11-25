<?php

namespace Database\Factories;

use App\Models\TestCase;
use App\Models\TestCaseStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseStep>
 */
class TestCaseStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_id' => TestCase::factory(),
            'position' => fake()->unique()->numberBetween(1, 5000),
            'action' => fake()->sentence(8),
            'expected_result' => fake()->optional()->sentence(6),
        ];
    }
}
