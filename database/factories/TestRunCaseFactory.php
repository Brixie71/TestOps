<?php

namespace Database\Factories;

use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\TestRunCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestRunCase>
 */
class TestRunCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The case is created inside the run's project so the snapshot stays
     * consistent: test_run_id resolves before test_case_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_run_id' => TestRun::factory(),
            'test_case_id' => function (array $attributes) {
                $run = TestRun::query()->findOrFail($attributes['test_run_id']);

                return TestCase::factory()->create([
                    'project_id' => $run->project_id,
                ])->getKey();
            },
            'position' => 1,
            'title' => fake()->sentence(6),
            'severity' => TestCase::SEVERITY_MAJOR,
            'steps' => [
                ['action' => 'Open the page', 'expected_result' => 'Page loads'],
            ],
        ];
    }
}
