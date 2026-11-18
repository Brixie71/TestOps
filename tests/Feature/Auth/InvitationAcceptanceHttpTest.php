<?php

namespace Tests\Feature\Auth;

use App\Actions\IssueUserInvitationAction;
use App\Actions\ResendUserInvitationAction;
use App\Models\AuditLog;
use App\Models\EmailVerificationRequest;
use App\Models\InvitationLinkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationAcceptanceHttpTest extends TestCase
{
    use RefreshDatabase;

    private function acceptUrlFor(string $token): string
    {
        return '/invitations/accept/'.$token;
    }

    private function issueLiveInvitation(): array
    {
        $admin = User::factory()->create();

        return app(IssueUserInvitationAction::class)->execute($admin, 'accept.me@example.net');
    }

    private function showInvitedUser(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    public function test_a_usable_link_renders_the_acceptance_page(): void
    {
        $issued = $this->issueLiveInvitation();

        $this->get($this->acceptUrlFor($issued['token']))
            ->assertOk()
            ->assertViewIs('invitations.accept')
            ->assertSeeText('accept.me@example.net');
    }

    public function test_an_unknown_link_renders_the_invalid_page(): void
    {
        $this->get($this->acceptUrlFor('never-issued-token'))
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('no longer valid');
    }

    public function test_a_consumed_link_is_rejected(): void
    {
        $issued = $this->issueLiveInvitation();

        $issued['linkRequest']->forceFill(['consumed_at' => now()])->save();

        $this->get($this->acceptUrlFor($issued['token']))
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('already been used');
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $issued = $this->issueLiveInvitation();

        $this->travel(16)->minutes();

        $this->get($this->acceptUrlFor($issued['token']))
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('expired');

        $this->assertSame(
            'invited',
            $this->showInvitedUser('accept.me@example.net')->account_status,
        );
    }

    public function test_a_cancelled_invitation_link_is_rejected(): void
    {
        $issued = $this->issueLiveInvitation();

        $issued['invitation']->forceFill([
            'revoked_at' => now(),
            'cancelled_reason' => 'Not needed',
        ])->save();

        $this->get($this->acceptUrlFor($issued['token']))
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('cancelled');
    }

    public function test_acceptance_sets_the_password_and_issues_verification(): void
    {
        Mail::fake();

        $issued = $this->issueLiveInvitation();

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-1',
            'password_confirmation' => 'Accept-Passphrase-1',
        ])
            ->assertOk()
            ->assertViewIs('invitations.accept-success');

        $invitedUser = $this->showInvitedUser('accept.me@example.net');

        $this->assertTrue(Hash::check('Accept-Passphrase-1', $invitedUser->password));
        $this->assertSame('invited', $invitedUser->account_status);

        $this->assertDatabaseHas('user_invitations', [
            'id' => $issued['invitation']->id,
            'accepted_at' => $issued['invitation']->refresh()->accepted_at,
        ]);

        $this->assertDatabaseHas('invitation_link_requests', [
            'id' => $issued['linkRequest']->id,
            'consumed_at' => $issued['linkRequest']->refresh()->consumed_at,
        ]);

        // The verification email went out once, addressed to the invited
        // recipient; the account stays invited until it is consumed.
        Mail::assertSentCount(1);

        $this->assertDatabaseHas('email_verification_requests', [
            'user_id' => $invitedUser->getKey(),
            'purpose' => EmailVerificationRequest::PURPOSE_ACCOUNT_ACTIVATION,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.accepted',
        ]);
    }

    public function test_the_verification_email_carries_no_raw_token_in_the_database(): void
    {
        Mail::fake();

        $issued = $this->issueLiveInvitation();

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-1',
            'password_confirmation' => 'Accept-Passphrase-1',
        ])->assertOk();

        $verificationRequest = EmailVerificationRequest::query()->sole();

        $this->assertSame(64, strlen((string) $verificationRequest->token_hash));
        $this->assertStringNotContainsString(
            $issued['token'],
            (string) $verificationRequest->token_hash,
        );
    }

    public function test_a_second_acceptance_of_the_consumed_link_is_rejected(): void
    {
        Mail::fake();

        $issued = $this->issueLiveInvitation();

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-1',
            'password_confirmation' => 'Accept-Passphrase-1',
        ])->assertOk();

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-2',
            'password_confirmation' => 'Accept-Passphrase-2',
        ])
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('already been used');

        // The first password is still on the account; no second rotation
        // happened through the same link.
        $invitedUser = $this->showInvitedUser('accept.me@example.net');

        $this->assertTrue(Hash::check('Accept-Passphrase-1', $invitedUser->password));
    }

    public function test_a_rotated_link_replaces_the_previous_one_on_the_page(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'rotation.page@example.net');

        $this->travel(2)->minutes();

        $resent = app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.60');

        // The old link no longer renders the acceptance form.
        $this->get($this->acceptUrlFor($issued['token']))
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('no longer valid');

        // The fresh link for the current generation still does.
        $this->get($this->acceptUrlFor($resent['token']))
            ->assertOk()
            ->assertViewIs('invitations.accept')
            ->assertSeeText('rotation.page@example.net');
    }

    public function test_the_older_link_cannot_be_submitted_after_rotation(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'rotation.post@example.net');

        $this->travel(2)->minutes();

        app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.61');

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-1',
            'password_confirmation' => 'Accept-Passphrase-1',
        ])
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('no longer valid');

        $invitedUser = $this->showInvitedUser('rotation.post@example.net');

        $this->assertSame('invited', $invitedUser->account_status);
        $this->assertNull(EmailVerificationRequest::query()->first());
    }

    public function test_password_validation_rejects_weak_or_unconfirmed_values(): void
    {
        $issued = $this->issueLiveInvitation();

        $this->from($this->acceptUrlFor($issued['token']))
            ->post($this->acceptUrlFor($issued['token']), [
                'password' => 'short1a',
                'password_confirmation' => 'short1a',
            ])
            ->assertSessionHasErrors(['password']);

        $this->from($this->acceptUrlFor($issued['token']))
            ->post($this->acceptUrlFor($issued['token']), [
                'password' => 'Unconfirmed-Passphrase-1',
                'password_confirmation' => 'different-value',
            ])
            ->assertSessionHasErrors(['password']);

        $this->assertNull(InvitationLinkRequest::query()->find($issued['linkRequest']->id)->consumed_at);

        $this->assertSame(
            'pending',
            $issued['invitation']->refresh()->state(),
        );
    }

    public function test_acceptance_validation_states_are_never_issued_after_the_link_dies(): void
    {
        $issued = $this->issueLiveInvitation();

        $this->travel(16)->minutes();

        $this->post($this->acceptUrlFor($issued['token']), [
            'password' => 'Accept-Passphrase-1',
            'password_confirmation' => 'Accept-Passphrase-1',
        ])
            ->assertOk()
            ->assertViewIs('invitations.invalid');

        $this->assertNull(EmailVerificationRequest::query()->first());

        $this->assertSame(
            0,
            AuditLog::query()->where('action', 'invitation.accepted')->count(),
        );
    }
}
