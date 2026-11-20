<?php

namespace Tests\Feature\Settings;

use App\Mail\AccountDeactivatedMailable;
use App\Mail\PasswordChangedMailable;
use App\Mail\SystemRoleChangedMailable;
use App\Models\AuditLog;
use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_permitted_admin_sees_the_delivery_history(): void
    {
        $actor = $this->createSuperAdmin();

        NotificationDelivery::factory()->failed()->create([
            'recipient_email' => 'history@example.net',
            'notification_type' => 'invitation_link',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.notifications'))
            ->assertOk()
            ->assertSee('history@example.net')
            ->assertSee('invitation_link')
            ->assertSee('Failed');
    }

    public function test_a_system_admin_with_the_permission_sees_the_page(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);

        $this->actingAs($actor)
            ->get(route('settings.notifications'))
            ->assertOk();
    }

    public function test_a_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);

        $this->actingAs($actor)
            ->get(route('settings.notifications'))
            ->assertForbidden();
    }

    public function test_an_ordinary_user_is_forbidden(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.notifications'))
            ->assertForbidden();
    }

    public function test_the_recipient_search_filters_results(): void
    {
        $actor = $this->createSuperAdmin();

        NotificationDelivery::factory()->failed()->create([
            'recipient_email' => 'wanted@example.net',
            'notification_type' => 'invitation_link',
        ]);

        NotificationDelivery::factory()->failed()->create([
            'recipient_email' => 'other@example.net',
            'notification_type' => 'invitation_link',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.notifications', ['search' => 'wanted@example.net']))
            ->assertOk()
            ->assertSee('wanted@example.net')
            ->assertDontSee('other@example.net');
    }

    public function test_the_status_filter_restricts_to_one_status(): void
    {
        $actor = $this->createSuperAdmin();

        NotificationDelivery::factory()->create([
            'recipient_email' => 'delivered@example.net',
            'notification_type' => 'invitation_link',
        ]);

        NotificationDelivery::factory()->failed()->create([
            'recipient_email' => 'failed@example.net',
            'notification_type' => 'invitation_link',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.notifications', ['status' => 'delivered']))
            ->assertOk()
            ->assertSee('delivered@example.net')
            ->assertDontSee('failed@example.net');
    }

    public function test_the_retry_button_renders_only_for_eligible_failures(): void
    {
        $actor = $this->createSuperAdmin();

        // Eligible: failed, retryable type, linked invitation link request.
        $eligible = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'invitation_link',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $eligible->getKey()]);

        // Not retryable: email verification has no resend path.
        $verification = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'email_verification',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $verification->getKey()]);

        // Not retryable: successfully delivered.
        $delivered = NotificationDelivery::factory()->create([
            'notification_type' => 'invitation_link',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $delivered->getKey()]);

        $response = $this->actingAs($actor)
            ->get(route('settings.notifications'));

        // Exactly one row (the eligible failure) must offer a retry control:
        // the form action is the only place the retry route URL appears.
        $response->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '/retry'));
    }

    public function test_a_manual_retry_rotates_credentials_and_records_a_successful_attempt(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $delivery = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'invitation_link',
        ]);
        $linkRequest = InvitationLinkRequest::factory()->create([
            'delivery_id' => $delivery->getKey(),
        ]);

        // Backdate the linked link request beyond the resend cooldown, so
        // the retry closure's rotation is not rate limited.
        InvitationLinkRequest::withoutTimestamps(function () use ($linkRequest): void {
            $linkRequest->forceFill(['created_at' => now()->subMinutes(2)])->save();
        });

        $previousGeneration = $linkRequest->invitation->credential_generation;

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHas('status');

        $retry = NotificationDeliveryRetry::query()
            ->where('notification_delivery_id', $delivery->getKey())
            ->sole();

        $this->assertSame(1, (int) $retry->attempt_number);
        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);

        // The original delivery record stays intact and the invitation
        // credentials were rotated (fresh generation).
        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->getKey(),
            'status' => NotificationDelivery::STATUS_FAILED,
        ]);
        $this->assertSame($previousGeneration + 1, $linkRequest->invitation->refresh()->credential_generation);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.retry',
            'actor_user_id' => $actor->getKey(),
        ]);
    }

    public function test_a_failed_security_notification_can_be_retried(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $delivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'account_deactivated',
        ]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHas('status');

        $retry = NotificationDeliveryRetry::query()
            ->where('notification_delivery_id', $delivery->getKey())
            ->sole();

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);

        Mail::assertSent(
            AccountDeactivatedMailable::class,
            fn (AccountDeactivatedMailable $mail): bool => $mail->hasTo($target->email),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.retry',
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target->getKey(),
        ]);
    }

    public function test_a_password_change_security_notification_can_be_retried(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $delivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'password_changed',
        ]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHas('status');

        $retry = NotificationDeliveryRetry::query()
            ->where('notification_delivery_id', $delivery->getKey())
            ->sole();

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);

        Mail::assertSent(
            PasswordChangedMailable::class,
            fn (PasswordChangedMailable $mail): bool => $mail->hasTo($target->email),
        );
    }

    public function test_a_role_change_retry_rebuilds_the_message_from_the_audit_snapshot(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        AuditLog::forceCreate([
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target->getKey(),
            'action' => 'user.role_changed',
            'before_values' => ['role_name' => 'Analyst'],
            'after_values' => ['role_name' => 'Lead'],
            'created_at' => now()->subMinute(),
        ]);

        $delivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'system_role_changed',
        ]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHas('status');

        Mail::assertSent(
            SystemRoleChangedMailable::class,
            fn (SystemRoleChangedMailable $mail): bool => $mail->hasTo($target->email)
                && $mail->previousRoleName === 'Analyst'
                && $mail->newRoleName === 'Lead',
        );
    }

    public function test_verification_and_reset_deliveries_have_no_resend_path(): void
    {
        $actor = $this->createSuperAdmin();

        foreach (['email_verification', 'forced_password_reset', 'email_change_verification'] as $type) {
            $delivery = NotificationDelivery::factory()->failed()->create([
                'notification_type' => $type,
            ]);

            $this->actingAs($actor)
                ->from(route('settings.notifications'))
                ->post(route('settings.notifications.retry', $delivery))
                ->assertRedirect(route('settings.notifications'))
                ->assertSessionHasErrors('retry');
        }

        $this->assertSame(0, NotificationDeliveryRetry::query()->count());
    }

    public function test_the_retry_button_renders_for_failed_security_notifications(): void
    {
        $actor = $this->createSuperAdmin();

        $delivery = NotificationDelivery::factory()->failed()->create([
            'recipient_email' => 'deactivated@example.net',
            'notification_type' => 'account_deactivated',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.notifications'))
            ->assertOk()
            ->assertSee(route('settings.notifications.retry', $delivery), false);
    }

    public function test_a_successful_delivery_cannot_be_retried(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $delivery = NotificationDelivery::factory()->create([
            'notification_type' => 'invitation_link',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $delivery->getKey()]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHasErrors('retry');

        $this->assertSame(0, NotificationDeliveryRetry::query()->count());
    }

    public function test_the_retry_limit_is_enforced(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $delivery = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'invitation_link',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $delivery->getKey()]);

        NotificationDeliveryRetry::query()->create([
            'notification_delivery_id' => $delivery->getKey(),
            'attempt_number' => 1,
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'attempted_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(29),
        ]);
        NotificationDeliveryRetry::query()->create([
            'notification_delivery_id' => $delivery->getKey(),
            'attempt_number' => 2,
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'attempted_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(19),
        ]);
        NotificationDeliveryRetry::query()->create([
            'notification_delivery_id' => $delivery->getKey(),
            'attempt_number' => 3,
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
        ]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHasErrors('retry');

        $this->assertSame(3, NotificationDeliveryRetry::query()->count());
    }

    public function test_the_cooldown_is_enforced_between_retries(): void
    {
        Mail::fake();

        $actor = $this->createSuperAdmin();
        $delivery = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'invitation_link',
        ]);
        InvitationLinkRequest::factory()->create(['delivery_id' => $delivery->getKey()]);

        NotificationDeliveryRetry::query()->create([
            'notification_delivery_id' => $delivery->getKey(),
            'attempt_number' => 1,
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'attempted_at' => now()->subMinutes(1),
            'completed_at' => now()->subMinutes(1),
        ]);

        $this->actingAs($actor)
            ->from(route('settings.notifications'))
            ->post(route('settings.notifications.retry', $delivery))
            ->assertRedirect(route('settings.notifications'))
            ->assertSessionHasErrors('retry');

        $this->assertSame(1, NotificationDeliveryRetry::query()->count());
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        if ($withPermission) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'notifications.view'],
                ['name' => 'View Email Delivery History'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
