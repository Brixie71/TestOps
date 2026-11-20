<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationDeliveryRecorder;
use App\Services\Notifications\NotificationPreferenceChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_categories_are_allowed_without_a_preference_row(): void
    {
        $user = User::factory()->create();
        $checker = $this->app->make(NotificationPreferenceChecker::class);

        $this->assertTrue($checker->allows($user, UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY));
        $this->assertTrue($checker->allows($user, UserNotificationPreference::CATEGORY_SYSTEM_ANNOUNCEMENTS));
    }

    public function test_a_disabled_category_is_not_allowed_while_the_other_stays_enabled(): void
    {
        $user = User::factory()->create();

        UserNotificationPreference::query()->create([
            'user_id' => $user->getKey(),
            'project_activity_enabled' => false,
            'system_announcements_enabled' => true,
        ]);

        $checker = $this->app->make(NotificationPreferenceChecker::class);

        $this->assertFalse($checker->allows($user, UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY));
        $this->assertTrue($checker->allows($user, UserNotificationPreference::CATEGORY_SYSTEM_ANNOUNCEMENTS));
    }

    public function test_security_and_unknown_categories_are_always_allowed(): void
    {
        $user = User::factory()->create();

        UserNotificationPreference::query()->create([
            'user_id' => $user->getKey(),
            'project_activity_enabled' => false,
            'system_announcements_enabled' => false,
        ]);

        $checker = $this->app->make(NotificationPreferenceChecker::class);

        $this->assertTrue($checker->allows($user, 'security_and_account_activity'));
        $this->assertTrue($checker->allows($user, 'unrecognized_category'));
    }

    public function test_the_checker_reads_the_current_preference_state(): void
    {
        $user = User::factory()->create();
        $checker = $this->app->make(NotificationPreferenceChecker::class);

        $this->assertTrue($checker->allows($user, UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY));

        UserNotificationPreference::query()->create([
            'user_id' => $user->getKey(),
            'project_activity_enabled' => false,
            'system_announcements_enabled' => true,
        ]);

        $this->assertFalse($checker->allows($user, UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY));
    }

    public function test_a_suppressed_delivery_is_recorded_without_failure_diagnostics(): void
    {
        $user = User::factory()->create();
        $recorder = $this->app->make(NotificationDeliveryRecorder::class);

        $delivery = $recorder->recordSuppressed(
            $user,
            $user->email,
            UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY,
        );

        $this->assertNotNull($delivery);
        $this->assertSame(NotificationDelivery::STATUS_SUPPRESSED, $delivery->status);
        $this->assertSame(NotificationDelivery::SUPPRESSION_REASON_PREFERENCE_DISABLED, $delivery->suppression_reason);
        $this->assertNull($delivery->failure_code);
        $this->assertNull($delivery->failure_message);
        $this->assertNull($delivery->delivered_at);
        $this->assertSame($user->getKey(), $delivery->user_id);
        $this->assertSame($user->email, $delivery->recipient_email);
    }
}
