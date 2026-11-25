<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create, update, and delete test cases as one audited operation.
 *
 * Creation assigns the per-project TC sequence under a project row lock so
 * concurrent creations can never reuse a key. Steps are replaced as ordered
 * child rows, never stored as a text blob. Every case mutation is audited.
 */
class SaveTestCaseAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{title: string, description: ?string, status: string, severity: string, steps: list<array{action: string, expected_result: ?string}>}  $attributes
     */
    public function create(
        User $actor,
        Project $project,
        TestSuite $suite,
        array $attributes,
    ): TestCase {
        return DB::transaction(function () use ($actor, $project, $suite, $attributes): TestCase {
            // Serialize sequence assignment per project; the row lock is a
            // no-op on SQLite but effective on PostgreSQL.
            Project::query()->whereKey($project->getKey())->lockForUpdate()->first();

            $sequence = (int) TestCase::query()
                ->where('project_id', $project->getKey())
                ->max('sequence') + 1;

            $case = new TestCase;

            $case->forceFill([
                'project_id' => $project->getKey(),
                'test_suite_id' => $suite->getKey(),
                'sequence' => $sequence,
                'created_by' => $actor->getKey(),
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'status' => $attributes['status'],
                'severity' => $attributes['severity'],
            ])->save();

            $this->writeSteps($case, $attributes['steps']);

            $this->auditLogger->log(
                $actor,
                null,
                'test_case.created',
                null,
                $this->snapshot($case),
            );

            return $case;
        });
    }

    /**
     * @param  array{title: string, description: ?string, status: string, severity: string, steps: list<array{action: string, expected_result: ?string}>}  $attributes
     */
    public function update(User $actor, TestCase $case, array $attributes): TestCase
    {
        return DB::transaction(function () use ($actor, $case, $attributes): TestCase {
            $before = $this->snapshot($case);

            $case->update([
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'status' => $attributes['status'],
                'severity' => $attributes['severity'],
            ]);

            $this->writeSteps($case, $attributes['steps']);

            $this->auditLogger->log(
                $actor,
                null,
                'test_case.updated',
                $before,
                $this->snapshot($case),
            );

            return $case;
        });
    }

    public function delete(User $actor, TestCase $case): void
    {
        DB::transaction(function () use ($actor, $case): void {
            // A case referenced by a run is part of recorded history: the
            // run's frozen snapshot must keep pointing at a real case.
            if (TestRunCase::query()->where('test_case_id', $case->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'test_case' => 'This test case is part of a test run and cannot be deleted.',
                ]);
            }

            $before = $this->snapshot($case);

            $case->delete();

            $this->auditLogger->log(
                $actor,
                null,
                'test_case.deleted',
                $before,
                null,
            );
        });
    }

    /**
     * Replace the case's ordered steps with the submitted rows.
     *
     * @param  list<array{action: string, expected_result: ?string}>  $steps
     */
    private function writeSteps(TestCase $case, array $steps): void
    {
        $case->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            $case->steps()->create([
                'position' => $index + 1,
                'action' => $step['action'],
                'expected_result' => $step['expected_result'],
            ]);
        }
    }

    /**
     * Auditable business snapshot: no internal identifiers beyond the ids
     * needed to trace the case.
     *
     * @return array<string, mixed>
     */
    private function snapshot(TestCase $case): array
    {
        return [
            'project_id' => $case->project_id,
            'test_case_id' => $case->getKey(),
            'test_suite_id' => $case->test_suite_id,
            'sequence' => $case->sequence,
            'title' => $case->title,
            'status' => $case->status,
            'severity' => $case->severity,
            'steps' => $case->steps()->count(),
        ];
    }
}
