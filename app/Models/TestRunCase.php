<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One frozen test case inside a run.
 *
 * Title, severity, and steps are copied when the run starts, so results and
 * reports always describe what was actually executed, not the current case.
 */
#[Fillable([
    'test_case_id',
    'position',
    'title',
    'severity',
    'steps',
])]
class TestRunCase extends Model
{
    use HasFactory;

    /**
     * Get the run that owns the snapshot.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(TestRun::class, 'test_run_id');
    }

    /**
     * Get the case the snapshot was taken from.
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /**
     * Get the current recorded result, if any.
     */
    public function result(): HasOne
    {
        return $this->hasOne(TestResult::class, 'test_run_case_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps' => 'array',
        ];
    }
}
