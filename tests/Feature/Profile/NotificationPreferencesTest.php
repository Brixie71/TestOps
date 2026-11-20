<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_renders_optional_categories_enabled_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('profile.notifications.update'), false)
            ->assertSee('name="project_activity_enabled"', false)
            ->assertSee('name="system_announcements_enabled"', false)
            ->assertSee('Project activity')
            ->assertSee('System announcements')
            ->assertSee('Always on');

        $this->assertDatabaseCount('user_notification_preferences', 0);
    }

    public function test_a_user_can_disable_project_activity_and_keep_announcements_enabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [
                'system_announcements_enabled' => '1',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $preferences = UserNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->sole();

        $this->assertFalse($preferences->project_activity_enabled);
        $this->assertTrue($preferences->system_announcements_enabled);
    }

    public function test_unchecking_both_optional_categories_disables_both(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();

        $preferences = UserNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->sole();

        $this->assertFalse($preferences->project_activity_enabled);
        $this->assertFalse($preferences->system_announcements_enabled);
    }

    public function test_resubmitting_updates_the_single_preference_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [
                'project_activity_enabled' => '1',
                'system_announcements_enabled' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('user_notification_preferences', 1);

        $preferences = UserNotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->sole();

        $this->assertFalse($preferences->project_activity_enabled);
        $this->assertFalse($preferences->system_announcements_enabled);
    }

    public function test_only_the_signed_in_users_preferences_are_changed(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($actor)
            ->patch(route('profile.notifications.update'), [
                'user_id' => $other->getKey(),
                'project_activity_enabled' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $actor->getKey(),
            'project_activity_enabled' => true,
        ]);
        $this->assertDatabaseMissing('user_notification_preferences', [
            'user_id' => $other->getKey(),
        ]);
    }

    public function test_mandatory_security_notifications_remain_always_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [
                'security_and_account_activity_enabled' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Always on')
            ->assertDontSee('name="security_and_account_activity_enabled"', false);
    }

    public function test_guests_cannot_update_notification_preferences(): void
    {
        $this->patch(route('profile.notifications.update'), [
            'project_activity_enabled' => '1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_notification_preferences', 0);
    }

    public function test_a_deactivated_user_cannot_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->actingAs($user)
            ->patch(route('profile.notifications.update'), [
                'project_activity_enabled' => '1',
            ])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_notification_preferences', 0);
    }
}
