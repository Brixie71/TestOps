<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A defect found in a project, optionally traced to the result that exposed it.
 *
 * The per-project display key is derived from the sequence (DEF-1, DEF-2...)
 * and assigned under a project row lock. Status changes are audited.
 */
#[Fillable([
    'title',
    'description',
    'severity',
    'status',
])]
class Defect extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_WONT_FIX = 'wont_fix';

    /**
     * Approved defect statuses (B4).
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_WONT_FIX,
    ];

    public const SEVERITY_BLOCKER = 'blocker';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_MAJOR = 'major';

    public const SEVERITY_MINOR = 'minor';

    public const SEVERITY_TRIVIAL = 'trivial';

    /**
     * Approved severity scale shared with test cases.
     *
     * @var list<string>
     */
    public const SEVERITIES = [
        self::SEVERITY_BLOCKER,
        self::SEVERITY_CRITICAL,
        self::SEVERITY_MAJOR,
        self::SEVERITY_MINOR,
        self::SEVERITY_TRIVIAL,
    ];

    /**
     * Get the project that owns the defect.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the result that exposed the defect, when one was recorded.
     */
    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }

    /**
     * Get the account that reported the defect, including soft-deleted accounts.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    /**
     * Per-project display key (for example DEF-4).
     */
    public function displayKey(): string
    {
        return 'DEF-'.$this->sequence;
    }
}
