<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution pass over a frozen set of test cases inside a project.
 *
 * The per-project display key is derived from the sequence (TR-1, TR-2...)
 * and assigned under a project row lock. The run's cases are snapshots: a
 * later case edit never rewrites an existing run.
 */
#[Fillable([
    'name',
    'status',
])]
class TestRun extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABORTED = 'aborted';

    /**
     * Approved run statuses (B4).
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_ABORTED,
    ];

    /**
     * Get the project that owns the run.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the frozen case snapshots in execution order.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(TestRunCase::class)->orderBy('position');
    }

    /**
     * Get the account that started the run, including soft-deleted accounts.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Per-project display key (for example TR-4).
     */
    public function displayKey(): string
    {
        return 'TR-'.$this->sequence;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
