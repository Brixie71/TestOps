<?php

namespace App\Actions;

use App\Models\Defect;
use App\Models\Project;
use App\Models\TestResult;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create, update, and transition defects as audited operations.
 *
 * Creation assigns the per-project DEF sequence under a project row lock so
 * concurrent creations can never reuse a key. Every mutation is audited, and
 * a status change gets its own transition event.
 */
class ManageDefectAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{title: string, description: ?string, severity: string, test_result_id: ?int}  $attributes
     */
    public function create(User $actor, Project $project, array $attributes): Defect
    {
        return DB::transaction(function () use ($actor, $project, $attributes): Defect {
            // Serialize sequence assignment per project; the row lock is a
            // no-op on SQLite but effective on PostgreSQL.
            Project::query()->whereKey($project->getKey())->lockForUpdate()->first();

            $testResultId = $this->resolveTestResultId($project, $attributes['test_result_id']);

            $sequence = (int) Defect::query()
                ->where('project_id', $project->getKey())
                ->max('sequence') + 1;

            $defect = new Defect;

            $defect->forceFill([
                'project_id' => $project->getKey(),
                'test_result_id' => $testResultId,
                'sequence' => $sequence,
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'severity' => $attributes['severity'],
                'status' => Defect::STATUS_OPEN,
                'reported_by' => $actor->getKey(),
            ])->save();

            $this->auditLogger->log(
                $actor,
                null,
                'defect.created',
                null,
                $this->snapshot($defect),
            );

            return $defect;
        });
    }

    /**
     * @param  array{title: string, description: ?string, severity: string, test_result_id: ?int}  $attributes
     */
    public function update(User $actor, Defect $defect, array $attributes): Defect
    {
        return DB::transaction(function () use ($actor, $defect, $attributes): Defect {
            $before = $this->snapshot($defect);

            $testResultId = $this->resolveTestResultId($defect->project, $attributes['test_result_id']);

            $defect->forceFill([
                'test_result_id' => $testResultId,
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'severity' => $attributes['severity'],
            ])->save();

            $this->auditLogger->log(
                $actor,
                null,
                'defect.updated',
                $before,
                $this->snapshot($defect),
            );

            return $defect;
        });
    }

    /**
     * Move a defect to a new status; the transition is audited separately
     * from detail edits. A no-op status change records nothing.
     */
    public function transition(User $actor, Defect $defect, string $status): Defect
    {
        return DB::transaction(function () use ($actor, $defect, $status): Defect {
            if ($defect->status === $status) {
                return $defect;
            }

            $before = $this->snapshot($defect);

            $defect->forceFill(['status' => $status])->save();

            $this->auditLogger->log(
                $actor,
                null,
                'defect.status_changed',
                $before,
                $this->snapshot($defect),
            );

            return $defect;
        });
    }

    /**
     * A defect may only reference a result from the same project.
     */
    private function resolveTestResultId(Project $project, ?int $testResultId): ?int
    {
        if ($testResultId === null) {
            return null;
        }

        $result = TestResult::query()
            ->with('runCase.run')
            ->find($testResultId);

        if ($result === null || (int) $result->runCase->run->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages([
                'test_result_id' => 'The selected test result must belong to this project.',
            ]);
        }

        return $result->getKey();
    }

    /**
     * Auditable business snapshot: no internal identifiers beyond the ids
     * needed to trace the defect.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Defect $defect): array
    {
        return [
            'project_id' => $defect->project_id,
            'defect_id' => $defect->getKey(),
            'sequence' => $defect->sequence,
            'title' => $defect->title,
            'status' => $defect->status,
            'severity' => $defect->severity,
            'test_result_id' => $defect->test_result_id,
        ];
    }
}
