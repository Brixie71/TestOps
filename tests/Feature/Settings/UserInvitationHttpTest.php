<?php

namespace Tests\Feature\Settings;

use App\Mail\InvitationLinkMailable;
use App\Models\NotificationDelivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInvitationHttpTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_ENDPOINT = '/settings/users/invitations';

    private function resendEndpoint(int $invitationId): string
    {
        return self::STORE_ENDPOINT.'/'.$invitationId.'/resend';
    }

    private function cancelEndpoint(int $invitationId): string
    {
        return self::STORE_ENDPOINT.'/'.$invitationId.'/cancel';
    }

    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => $scope],
            ['name' => $code],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function grant(User $user, string $permissionCode): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            ['name' => $permissionCode, 'description' => $permissionCode],
        );

        $user->role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }

    // ------------------------------------------------------------------
    // Directory affordances
    // ------------------------------------------------------------------

    public function test_the_directory_shows_invitation_controls_for_live_invitations(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');
        $this->grant($admin, 'users.invitations.manage');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'affordance@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users'))
            ->assertOk();

        // Assert the per-row affordances: the resend form for the live
        // invitation and the row-level cancel trigger (the shared dialog
        // markup itself always renders, hidden).
        $this->assertStringContainsString('Resend link', $response->getContent());
        $this->assertStringContainsString('data-dialog-target="dialog-cancel-invitation"', $response->getContent());
    }

    public function test_the_directory_hides_invitation_controls_without_the_manage_permission(): void
    {
        Mail::fake();

        // A Super Admin issues the invitation (unrestricted), then a System
        // Admin with directory access but no `users.invitations.manage`
        // permission views the directory.
        $issuer = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($issuer, 'users.create');

        $this->actingAs($issuer)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'hidden.controls@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ]);

        // The directory route requires `users.deactivate`, which is granted;
        // the invitation controls additionally require `users.invitations.manage`.
        $viewer = $this->createUserWithRole('SYSTEM_ADMIN', 'system');
        $this->grant($viewer, 'users.deactivate');

        $response = $this->actingAs($viewer)
            ->get(route('settings.users'))
            ->assertOk();

        // The per-row affordances are absent: no resend form and no row-level
        // cancel trigger (the shared hidden dialog markup still renders).
        $this->assertStringNotContainsString('Resend link', $response->getContent());
        $this->assertStringNotContainsString('data-dialog-target="dialog-cancel-invitation"', $response->getContent());
    }

    // ------------------------------------------------------------------
    // Issuing
    // ------------------------------------------------------------------

    public function test_a_permitted_admin_issues_an_invitation_and_the_email_sends(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'new.member@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSentCount(1);

        $this->assertDatabaseHas('users', [
            'email' => 'new.member@example.net',
            'account_status' => 'invited',
        ]);

        $this->assertDatabaseHas('user_invitations', [
            'invited_email' => 'new.member@example.net',
            'method' => UserInvitation::METHOD_INVITATION_LINK,
            'acceptance_flow' => UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
        ]);
    }

    public function test_direct_account_setup_uses_the_password_setup_email(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'direct.member@example.net',
                'method' => UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_invitations', [
            'invited_email' => 'direct.member@example.net',
            'method' => UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'recipient_email' => 'direct.member@example.net',
            'notification_type' => 'direct_account_setup',
            'status' => NotificationDelivery::STATUS_DELIVERED,
        ]);
    }

    public function test_a_duplicated_email_is_rejected_with_a_validation_error(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => $admin->email,
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_the_invitation_email_targets_the_recipient_only(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'secret.check@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertRedirect();

        Mail::assertSent(InvitationLinkMailable::class, function (InvitationLinkMailable $mailable): bool {
            return $mailable->hasTo('secret.check@example.net');
        });
    }

    // ------------------------------------------------------------------
    // Resend and cancel
    // ------------------------------------------------------------------

    public function test_a_permitted_admin_resends_and_the_credentials_rotate(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');
        $this->grant($admin, 'users.invitations.manage');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'rotate.admin@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertRedirect();

        $invitation = UserInvitation::query()
            ->where('invited_email', 'rotate.admin@example.net')
            ->sole();

        $previousGeneration = $invitation->credential_generation;

        $this->travel(2)->minutes();

        $this->actingAs($admin)
            ->post($this->resendEndpoint($invitation->id))
            ->assertRedirect()
            ->assertSessionHas('status');

        $invitation->refresh();

        $this->assertSame($previousGeneration + 1, $invitation->credential_generation);
        $this->assertSame(2, $invitation->sent_count);

        Mail::assertSentCount(2);
    }

    public function test_a_permitted_admin_cancels_a_pending_invitation(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');
        $this->grant($admin, 'users.invitations.manage');

        $this->actingAs($admin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'cancel.admin@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertRedirect();

        $invitation = UserInvitation::query()
            ->where('invited_email', 'cancel.admin@example.net')
            ->sole();

        $this->travel(2)->minutes();

        $this->actingAs($admin)
            ->post($this->cancelEndpoint($invitation->getKey()), [
                'cancelled_reason' => 'Requested by the team lead',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $invitation->refresh();

        $this->assertTrue($invitation->isCancelled());
        $this->assertSame('Requested by the team lead', $invitation->cancelled_reason);
        $this->assertSame($admin->getKey(), $invitation->cancelled_by);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($admin, 'users.create');
        $this->grant($admin, 'users.invitations.manage');

        $invitation = UserInvitation::factory()->create();

        $this->actingAs($admin)
            ->post($this->cancelEndpoint($invitation->id))
            ->assertSessionHasErrors(['cancelled_reason']);
    }

    // ------------------------------------------------------------------
    // Permission matrix
    // ------------------------------------------------------------------

    public function test_issuing_requires_the_users_create_permission(): void
    {
        $systemAdmin = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->actingAs($systemAdmin)
            ->post(self::STORE_ENDPOINT, ['email' => 'denied@example.net'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'denied@example.net']);
    }

    public function test_a_system_admin_with_users_create_can_issue(): void
    {
        Mail::fake();

        $systemAdmin = $this->createUserWithRole('SYSTEM_ADMIN', 'system');
        $this->grant($systemAdmin, 'users.create');

        $this->actingAs($systemAdmin)
            ->post(self::STORE_ENDPOINT, [
                'email' => 'system.permitted@example.net',
                'method' => UserInvitation::METHOD_INVITATION_LINK,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'system.permitted@example.net',
            'account_status' => 'invited',
        ]);
    }

    public function test_resend_and_cancel_require_the_manage_permission(): void
    {
        $systemAdmin = $this->createUserWithRole('SYSTEM_ADMIN', 'system');
        $this->grant($systemAdmin, 'users.create');

        $invitation = UserInvitation::factory()->create();

        $this->actingAs($systemAdmin)
            ->post($this->resendEndpoint($invitation->id))
            ->assertForbidden();

        $this->actingAs($systemAdmin)
            ->post($this->cancelEndpoint($invitation->id), ['cancelled_reason' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $invitation = UserInvitation::factory()->create();

        $this->post(self::STORE_ENDPOINT, ['email' => 'guest@example.net'])
            ->assertRedirect(route('login'));

        $this->post($this->resendEndpoint($invitation->id))
            ->assertRedirect(route('login'));

        $this->post($this->cancelEndpoint($invitation->id))
            ->assertRedirect(route('login'));
    }

    public function test_inactive_accounts_are_blocked_by_the_active_account_gate(): void
    {
        $deactivated = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $this->grant($deactivated, 'users.create');

        $deactivated->delete(); // SoftDelete deactivation.

        // The active-account gate ends the session and sends the user to
        // login instead of processing the write.
        $this->actingAs($deactivated)
            ->post(self::STORE_ENDPOINT, ['email' => 'inactive@example.net'])
            ->assertRedirect('/login');

        $this->assertFalse(Auth::guard('web')->check() ?? false);

        $this->assertDatabaseMissing('users', ['email' => 'inactive@example.net']);
    }
}
