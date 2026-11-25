<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Start, complete, and abort test runs as audited operations.
 *
 * Starting assigns the per-project TR sequence under a project row lock and
 * freezes each selected case (title, severity, steps) into the run, so later
 * authoring never rewrites what was executed. Only open runs can be finished.
 */
class ManageTestRunAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<int>  $caseIds
     */
    public function start(User $actor, Project $project, string $name, array $caseIds): TestRun
    {
        return DB::transaction(function () use ($actor, $project, $name, $caseIds): TestRun {
            // Serialize sequence assignment per project; the row lock is a
            // no-op on SQLite but effective on PostgreSQL.
            Project::query()->whereKey($project->getKey())->lockForUpdate()->first();

            $cases = $project->testCases()
                ->with('steps')
                ->whereIn('id', $caseIds)
                ->orderBy('sequence')
                ->get();

            // Every requested case must belong to this project, and a case
            // may appear once per run.
            if ($cases->count() !== count($caseIds)) {
                throw ValidationException::withMessages([
                    'case_ids' => 'Every selected test case must belong to this project.',
                ]);
            }

            $sequence = (int) TestRun::query()
                ->where('project_id', $project->getKey())
                ->max('sequence') + 1;

            $run = new TestRun;

            $run->forceFill([
                'project_id' => $project->getKey(),
                'sequence' => $sequence,
                'name' => $name,
                'status' => TestRun::STATUS_OPEN,
                'created_by' => $actor->getKey(),
                'started_at' => now(),
            ])->save();

            foreach ($cases->values() as $index => $case) {
                $run->cases()->create([
                    'test_case_id' => $case->getKey(),
                    'position' => $index + 1,
                    'title' => $case->title,
                    'severity' => $case->severity,
                    'steps' => $case->steps->map(fn ($step): array => [
                        'action' => $step->action,
                        'expected_result' => $step->expected_result,
                    ])->all(),
                ]);
            }

            $this->auditLogger->log(
                $actor,
                null,
                'test_run.started',
                null,
                $this->snapshot($run),
            );

            return $run;
        });
    }

    public function complete(User $actor, TestRun $run): TestRun
    {
        return $this->finish($actor, $run, TestRun::STATUS_COMPLETED, 'test_run.completed');
    }

    public function abort(User $actor, TestRun $run): TestRun
    {
        return $this->finish($actor, $run, TestRun::STATUS_ABORTED, 'test_run.aborted');
    }

    private function finish(User $actor, TestRun $run, string $status, string $event): TestRun
    {
        return DB::transaction(function () use ($actor, $run, $status, $event): TestRun {
            if (! $run->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Only open test runs can be '.($status === TestRun::STATUS_COMPLETED ? 'completed' : 'aborted').'.',
                ]);
            }

            $before = $this->snapshot($run);

            $run->forceFill([
                'status' => $status,
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                $actor,
                null,
                $event,
                $before,
                $this->snapshot($run),
            );

            return $run;
        });
    }

    /**
     * Auditable business snapshot: no internal identifiers beyond the ids
     * needed to trace the run.
     *
     * @return array<string, mixed>
     */
    private function snapshot(TestRun $run): array
    {
        return [
            'project_id' => $run->project_id,
            'test_run_id' => $run->getKey(),
            'sequence' => $run->sequence,
            'name' => $run->name,
            'status' => $run->status,
            'cases' => $run->cases()->count(),
        ];
    }
}
