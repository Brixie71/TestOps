<?php

namespace Database\Factories;

use App\Models\TestResult;
use App\Models\TestRunCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestResult>
 */
class TestResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_run_case_id' => TestRunCase::factory(),
            'status' => TestResult::STATUS_PASSED,
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => ['status' => TestResult::STATUS_PASSED]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => TestResult::STATUS_FAILED]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => TestResult::STATUS_BLOCKED]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => ['status' => TestResult::STATUS_SKIPPED]);
    }

    public function notRun(): static
    {
        return $this->state(fn () => ['status' => TestResult::STATUS_NOT_RUN]);
    }
}
