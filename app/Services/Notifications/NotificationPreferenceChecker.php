<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotificationPreference;

/**
 * Resolves the current notification preference immediately before delivery
 * (§14.4).
 *
 * Only the optional categories consult the stored preference. Mandatory
 * security and account activity notifications are always allowed, and an
 * unrecognized category fails open so a naming mistake can never silence
 * mandatory mail. A user without a preference row receives both optional
 * categories, matching the stored column defaults.
 */
class NotificationPreferenceChecker
{
    /**
     * Determine whether the user's current preferences allow a delivery in
     * the given category.
     */
    public function allows(User $user, string $category): bool
    {
        $preferences = $user->notificationPreference()->first();

        return match ($category) {
            UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY => $preferences?->project_activity_enabled ?? true,
            UserNotificationPreference::CATEGORY_SYSTEM_ANNOUNCEMENTS => $preferences?->system_announcements_enabled ?? true,
            default => true,
        };
    }
}
