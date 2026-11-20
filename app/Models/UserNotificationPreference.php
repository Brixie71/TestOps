<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The optional notification categories a user can switch off (§14.3).
 *
 * Security and account activity notifications are mandatory and have no
 * stored preference. A user without a preference row receives both optional
 * categories, matching the column defaults for new accounts.
 */
#[Fillable([
    'user_id',
    'project_activity_enabled',
    'system_announcements_enabled',
])]
class UserNotificationPreference extends Model
{
    /**
     * Optional categories a user may switch off (§14.3).
     */
    public const CATEGORY_PROJECT_ACTIVITY = 'project_activity';

    public const CATEGORY_SYSTEM_ANNOUNCEMENTS = 'system_announcements';

    /**
     * The categories that consult a stored preference. Security and account
     * activity notifications are mandatory and never appear here.
     *
     * @var list<string>
     */
    public const OPTIONAL_CATEGORIES = [
        self::CATEGORY_PROJECT_ACTIVITY,
        self::CATEGORY_SYSTEM_ANNOUNCEMENTS,
    ];

    /**
     * Get the user who owns these preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_activity_enabled' => 'boolean',
            'system_announcements_enabled' => 'boolean',
        ];
    }
}
