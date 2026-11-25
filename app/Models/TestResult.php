<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The recorded execution outcome of one frozen case inside a run.
 *
 * A run case holds at most one current result; recording again replaces the
 * status and notes and audits the change.
 */
#[Fillable([
    'status',
    'notes',
])]
class TestResult extends Model
{
    use HasFactory;

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_NOT_RUN = 'not_run';

    /**
     * Approved result statuses (B4).
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PASSED,
        self::STATUS_FAILED,
        self::STATUS_BLOCKED,
        self::STATUS_SKIPPED,
        self::STATUS_NOT_RUN,
    ];

    /**
     * Get the frozen case this result belongs to.
     */
    public function runCase(): BelongsTo
    {
        return $this->belongsTo(TestRunCase::class, 'test_run_case_id');
    }

    /**
     * Get the account that recorded the result, including soft-deleted accounts.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
