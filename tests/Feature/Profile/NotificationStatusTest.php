<?php

namespace Tests\Feature\Profile;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_lists_the_users_own_deliveries(): void
    {
        $user = User::factory()->create();

        NotificationDelivery::factory()->create([
            'user_id' => $user->getKey(),
            'recipient_email' => $user->email,
            'notification_type' => 'account_reactivated',
            'status' => NotificationDelivery::STATUS_DELIVERED,
        ]);

        NotificationDelivery::factory()->create([
            'user_id' => User::factory()->create()->getKey(),
            'notification_type' => 'someone_elses_notification',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Account reactivated')
            ->assertSee('Delivered')
            ->assertDontSee('Someone elses notification');
    }

    public function test_a_failed_delivery_shows_only_the_simple_explanation(): void
    {
        $user = User::factory()->create();

        NotificationDelivery::factory()->failed()->create([
            'user_id' => $user->getKey(),
            'recipient_email' => $user->email,
            'notification_type' => 'account_deactivated',
            'failure_code' => 'smtp_auth_failed',
            'failure_message' => 'Internal SMTP diagnostic text.',
            'user_message' => 'Your email could not be delivered. Please try again later.',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee('Your email could not be delivered. Please try again later.')
            ->assertDontSee('Internal SMTP diagnostic text.')
            ->assertDontSee('smtp_auth_failed');
    }

    public function test_the_displayed_status_reflects_the_latest_retry(): void
    {
        $user = User::factory()->create();

        $delivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $user->getKey(),
            'recipient_email' => $user->email,
            'notification_type' => 'invitation_link',
        ]);

        NotificationDeliveryRetry::factory()->delivered()->create([
            'notification_delivery_id' => $delivery->getKey(),
            'attempt_number' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Delivered')
            ->assertDontSee('Failed')
            ->assertDontSee('Your email could not be delivered. Please try again later.');
    }

    public function test_a_suppressed_delivery_shows_the_preference_reason(): void
    {
        $user = User::factory()->create();

        NotificationDelivery::factory()->suppressed()->create([
            'user_id' => $user->getKey(),
            'recipient_email' => $user->email,
            'notification_type' => 'project_activity',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Suppressed')
            ->assertSee('Disabled in your notification preferences.')
            ->assertDontSee('Failed');
    }

    public function test_the_profile_offers_no_retry_control(): void
    {
        $user = User::factory()->create();

        $delivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $user->getKey(),
            'recipient_email' => $user->email,
            'notification_type' => 'invitation_link',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertDontSee(route('settings.notifications.retry', $delivery), false)
            ->assertDontSee('Retry');
    }

    public function test_the_profile_shows_an_empty_state_without_deliveries(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('No notifications sent to your account yet.');
    }
}
