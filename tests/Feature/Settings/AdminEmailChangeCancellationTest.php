<?php

namespace Tests\Feature\Settings;

use App\Mail\EmailChangeCancelledMailable;
use App\Models\EmailVerificationRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEmailChangeCancellationTest extends TestCase
{
    use RefreshDatabase;

    private const PENDING_EMAIL = 'pending-address@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function targetWithPendingChange(): User
    {
        $target = User::factory()->create();

        UserEmailAddress::query()->create([
            'user_id' => $target->getKey(),
            'email' => self::PENDING_EMAIL,
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        app(EmailVerificationService::class)->issue(
            $target,
            EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            self::PENDING_EMAIL,
        );

        return $target;
    }

    public function test_a_permitted_administrator_can_cancel_a_pending_change(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->targetWithPendingChange();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.email-change.cancel', $target), [
                'reason' => 'Requested by the account holder by phone.',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('user_email_addresses', [
            'user_id' => $target->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->assertSame(0, EmailVerificationRequest::query()
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.email.change_cancelled_by_admin',
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target->getKey(),
            'reason' => 'Requested by the account holder by phone.',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'email_change_cancelled',
            'status' => 'delivered',
        ]);

        Mail::assertSent(EmailChangeCancelledMailable::class);

        // The current verified address is preserved.
        $this->assertSame($target->email, $target->refresh()->email);
    }

    public function test_a_reason_is_required(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->targetWithPendingChange();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.email-change.cancel', $target), ['reason' => ''])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $target->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_a_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withEmailPermission: false);
        $target = $this->targetWithPendingChange();

        $this->actingAs($actor)
            ->post(route('settings.users.email-change.cancel', $target), [
                'reason' => 'Not allowed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $target->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_a_system_admin_with_the_permission_can_cancel(): void
    {
        $actor = $this->createSystemAdmin(withEmailPermission: true);
        $target = $this->targetWithPendingChange();

        $this->actingAs($actor)
            ->post(route('settings.users.email-change.cancel', $target), [
                'reason' => 'Suspicious change request.',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('user_email_addresses', [
            'user_id' => $target->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_an_administrator_cannot_cancel_their_own_pending_change_here(): void
    {
        $actor = $this->createSuperAdmin();

        UserEmailAddress::query()->create([
            'user_id' => $actor->getKey(),
            'email' => self::PENDING_EMAIL,
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->actingAs($actor)
            ->post(route('settings.users.email-change.cancel', $actor), [
                'reason' => 'Self cancellation attempt.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $actor->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_a_system_admin_cannot_cancel_a_super_admins_pending_change(): void
    {
        $actor = $this->createSystemAdmin(withEmailPermission: true);
        $superAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );
        $target = User::factory()->create(['role_id' => $superAdminRole->id]);

        UserEmailAddress::query()->create([
            'user_id' => $target->getKey(),
            'email' => self::PENDING_EMAIL,
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->actingAs($actor)
            ->post(route('settings.users.email-change.cancel', $target), [
                'reason' => 'Protected target.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $target->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_cancelling_without_a_pending_change_is_rejected(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->post(route('settings.users.email-change.cancel', $target), [
                'reason' => 'Nothing pending.',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors('email');
    }

    public function test_the_details_page_offers_the_cancellation_form_only_to_permitted_actors(): void
    {
        $target = $this->targetWithPendingChange();

        $this->actingAs($this->createSuperAdmin())
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee(self::PENDING_EMAIL)
            ->assertSee(route('settings.users.email-change.cancel', $target), false);

        $this->actingAs($this->createSystemAdmin(withEmailPermission: false))
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee(self::PENDING_EMAIL)
            ->assertDontSee(route('settings.users.email-change.cancel', $target), false);
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SuperAdmin#Pass1',
        ]);
    }

    private function createSystemAdmin(bool $withEmailPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $deactivate = Permission::query()->firstOrCreate(
            ['code' => 'users.deactivate'],
            ['name' => 'Deactivate User Accounts'],
        );

        $permissions = [$deactivate->id];

        if ($withEmailPermission) {
            $permissions[] = Permission::query()->firstOrCreate(
                ['code' => 'users.email.manage'],
                ['name' => 'Manage User Email Changes'],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($permissions);

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SystemAdmin#Pass1',
        ]);
    }
}
