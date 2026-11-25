<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A test case inside one project and one test suite.
 *
 * The per-project display key is derived from the sequence (TC-1, TC-2...)
 * and assigned under a project row lock, so two simultaneous creations can
 * never collide. Steps are ordered child rows, never a text blob.
 */
#[Fillable([
    'test_suite_id',
    'title',
    'description',
    'status',
    'severity',
])]
class TestCase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPRECATED = 'deprecated';

    public const SEVERITY_BLOCKER = 'blocker';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_MAJOR = 'major';

    public const SEVERITY_MINOR = 'minor';

    public const SEVERITY_TRIVIAL = 'trivial';

    /**
     * Approved case statuses (B4).
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_DEPRECATED,
    ];

    /**
     * Case severity scale, shared with run snapshots and defects.
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
     * Get the project that owns the case.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the suite that groups the case.
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class, 'test_suite_id');
    }

    /**
     * Get the author of the case, including soft-deleted accounts.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Get the ordered execution steps.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TestCaseStep::class)->orderBy('position');
    }

    /**
     * Per-project display key (for example TC-4).
     */
    public function displayKey(): string
    {
        return 'TC-'.$this->sequence;
    }
}
