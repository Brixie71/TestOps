<?php

namespace Tests\Feature\Settings;

use App\Mail\SystemAnnouncementMailable;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class SystemAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_page_shows_the_active_audience(): void
    {
        $admin = $this->createSuperAdmin();
        User::factory()->count(2)->create(['account_status' => 'active']);
        User::factory()->invited()->create();

        $this->actingAs($admin)
            ->get(route('settings.announcements'))
            ->assertOk()
            ->assertSee('Send a system announcement')
            ->assertSee('3 active accounts');
    }

    public function test_a_super_admin_can_send_to_every_active_account(): void
    {
        $admin = $this->createSuperAdmin();
        $first = User::factory()->create(['account_status' => 'active']);
        $second = User::factory()->create(['account_status' => 'active']);
        $invited = User::factory()->invited()->create();
        $deactivated = User::factory()->create(['account_status' => 'active']);
        $deactivated->delete();

        $this->actingAs($admin)
            ->post(route('settings.announcements.send'), [
                'subject' => 'Saturday maintenance',
                'body' => "TestOps is read-only from 22:00 to 23:00.\nPlan accordingly.",
            ])
            ->assertRedirect(route('settings.announcements'))
            ->assertSessionHas('status');

        Mail::assertSent(SystemAnnouncementMailable::class, 3);
        Mail::assertSent(
            SystemAnnouncementMailable::class,
            fn (SystemAnnouncementMailable $mail): bool => $mail->hasTo($first->email)
                && $mail->announcementSubject === 'Saturday maintenance'
                && str_contains($mail->render(), 'TestOps is read-only from 22:00 to 23:00.'),
        );
        Mail::assertNotSent(
            SystemAnnouncementMailable::class,
            fn (SystemAnnouncementMailable $mail): bool => $mail->hasTo($invited->email)
                || $mail->hasTo($deactivated->email),
        );

        $this->assertSame(
            3,
            NotificationDelivery::query()
                ->where('notification_type', 'system_announcement')
                ->where('status', NotificationDelivery::STATUS_DELIVERED)
                ->count(),
        );
        $this->assertDatabaseMissing('notification_deliveries', ['user_id' => $invited->getKey()]);
        $this->assertDatabaseMissing('notification_deliveries', ['user_id' => $deactivated->getKey()]);

        $entry = AuditLog::query()->where('action', 'announcement.sent')->sole();

        $this->assertSame('Saturday maintenance', $entry->after_values['subject']);
        $this->assertSame(3, $entry->after_values['recipients']);
        $this->assertArrayNotHasKey('body', $entry->after_values);
    }

    public function test_a_recipient_who_disabled_announcements_is_suppressed_not_emailed(): void
    {
        $admin = $this->createSuperAdmin();
        $optedOut = User::factory()->create(['account_status' => 'active']);

        UserNotificationPreference::query()->create([
            'user_id' => $optedOut->getKey(),
            'system_announcements_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.announcements.send'), [
                'subject' => 'Release v2',
                'body' => 'The dashboard has been refreshed.',
            ])
            ->assertRedirect(route('settings.announcements'));

        Mail::assertSent(SystemAnnouncementMailable::class, 1);
        Mail::assertNotSent(
            SystemAnnouncementMailable::class,
            fn (SystemAnnouncementMailable $mail): bool => $mail->hasTo($optedOut->email),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $optedOut->getKey(),
            'notification_type' => 'system_announcement',
            'status' => NotificationDelivery::STATUS_SUPPRESSED,
            'suppression_reason' => NotificationDelivery::SUPPRESSION_REASON_PREFERENCE_DISABLED,
            'failure_code' => null,
            'failure_message' => null,
        ]);
    }

    public function test_a_mail_failure_is_recorded_without_rolling_back_the_send(): void
    {
        $admin = $this->createSuperAdmin();

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('Mail transport is unavailable.'));

        $this->actingAs($admin)
            ->post(route('settings.announcements.send'), [
                'subject' => 'Release v2',
                'body' => 'The dashboard has been refreshed.',
            ])
            ->assertRedirect(route('settings.announcements'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $admin->getKey(),
            'notification_type' => 'system_announcement',
            'status' => NotificationDelivery::STATUS_FAILED,
            'failure_code' => 'delivery_failed',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.sent']);
    }

    public function test_subject_and_body_are_required(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->post(route('settings.announcements.send'), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        Mail::assertNothingSent();
    }

    public function test_non_super_admins_are_forbidden(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $actor = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($actor)
            ->get(route('settings.announcements'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('settings.announcements.send'), ['subject' => 'Release', 'body' => 'Notes.'])
            ->assertForbidden();
    }

    public function test_recent_announcement_deliveries_are_listed(): void
    {
        $admin = $this->createSuperAdmin();

        NotificationDelivery::query()->create([
            'user_id' => $admin->getKey(),
            'recipient_email' => $admin->email,
            'notification_type' => 'system_announcement',
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('settings.announcements'))
            ->assertOk()
            ->assertSee($admin->email)
            ->assertSee('Delivered');
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
