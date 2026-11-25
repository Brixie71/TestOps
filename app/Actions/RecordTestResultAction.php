<?php

namespace App\Actions;

use App\Models\TestResult;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record (or re-record) the result of one frozen case inside an open run.
 *
 * Results are execution history, so a finished run never accepts new
 * outcomes. Each recording audits the status and notes before and after.
 */
class RecordTestResultAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function record(
        User $actor,
        TestRun $run,
        TestRunCase $runCase,
        string $status,
        ?string $notes,
    ): TestResult {
        return DB::transaction(function () use ($actor, $run, $runCase, $status, $notes): TestResult {
            if (! $run->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Results can only be recorded while the run is open.',
                ]);
            }

            $result = TestResult::query()
                ->where('test_run_case_id', $runCase->getKey())
                ->first() ?? new TestResult;

            // The foreign key is never mass-assignable; set it explicitly.
            $result->test_run_case_id = $runCase->getKey();

            $before = $result->exists ? $this->snapshot($runCase, $result) : null;

            $result->forceFill([
                'status' => $status,
                'notes' => $notes,
                'recorded_by' => $actor->getKey(),
                'recorded_at' => now(),
            ])->save();

            $this->auditLogger->log(
                $actor,
                null,
                'test_result.recorded',
                $before,
                $this->snapshot($runCase, $result),
            );

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TestRunCase $runCase, TestResult $result): array
    {
        return [
            'test_run_id' => $runCase->test_run_id,
            'test_run_case_id' => $runCase->getKey(),
            'status' => $result->status,
            'notes' => $result->notes,
        ];
    }
}
