<?php

namespace Database\Factories;

use App\Models\Defect;
use App\Models\Project;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Defect>
 */
class DefectFactory extends Factory
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
            'test_result_id' => null,
            'sequence' => 1,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'severity' => Defect::SEVERITY_MAJOR,
            'status' => Defect::STATUS_OPEN,
            'reported_by' => User::factory(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => Defect::STATUS_OPEN]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => Defect::STATUS_IN_PROGRESS]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => Defect::STATUS_RESOLVED]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => Defect::STATUS_CLOSED]);
    }

    public function wontFix(): static
    {
        return $this->state(fn () => ['status' => Defect::STATUS_WONT_FIX]);
    }

    public function forResult(TestResult $result): static
    {
        return $this->state(fn () => ['test_result_id' => $result->getKey()]);
    }
}
