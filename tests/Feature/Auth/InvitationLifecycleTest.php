<?php

namespace Tests\Feature\Auth;

use App\Actions\AcceptInvitationAction;
use App\Actions\CancelUserInvitationAction;
use App\Actions\IssueUserInvitationAction;
use App\Actions\RecoverUserInvitationAction;
use App\Actions\ResendUserInvitationAction;
use App\Models\AuditLog;
use App\Models\InvitationLinkRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Auth\InvitationLinkException;
use App\Services\Auth\InvitationRateLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mail;
use Tests\TestCase;

class InvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Issue
    // ------------------------------------------------------------------

    public function test_issue_creates_the_account_ledger_invitation_and_first_link(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute(
            $admin,
            'new.member@example.com',
            UserInvitation::METHOD_INVITATION_LINK,
            UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
            null,
            '203.0.113.10',
        );

        $invitedUser = User::query()->where('email', 'new.member@example.com')->firstOrFail();

        $this->assertSame('invited', $invitedUser->account_status);
        $this->assertNull($invitedUser->name);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $invitedUser->getKey(),
            'email' => 'new.member@example.com',
            'status' => 'reserved',
            'verified_at' => null,
        ]);

        $invitation = $issued['invitation'];

        $this->assertSame(UserInvitation::METHOD_INVITATION_LINK, $invitation->method);
        $this->assertSame(UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY, $invitation->acceptance_flow);
        $this->assertSame('pending', $invitation->state());
        $this->assertSame(1, $invitation->credential_generation);
        $this->assertSame(1, $invitation->sent_count);
        $this->assertNotNull($invitation->recovery_code_hash);
        $this->assertDatabaseHas('invitation_link_requests', [
            'id' => $issued['linkRequest']->id,
            'ip_address' => '203.0.113.10',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->getKey(),
            'action' => 'user.account_created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->getKey(),
            'action' => 'invitation.issued',
        ]);
    }

    public function test_issue_rejects_an_already_registered_email(): void
    {
        $admin = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(IssueUserInvitationAction::class)->execute($admin, $admin->email);
    }

    public function test_the_database_never_contains_a_raw_token_or_recovery_code(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'stored.safe@example.com');

        $tokenHash = $issued['invitation']->token_hash;
        $recoveryHash = $issued['invitation']->recovery_code_hash;

        $this->assertNotSame($issued['token'], $tokenHash);
        $this->assertNotSame($issued['recoveryCode'], $recoveryHash);

        $linkRequest = $issued['linkRequest']->refresh();

        $this->assertNotSame($issued['token'], $linkRequest->token_hash);
    }

    public function test_model_serialization_never_exposes_credential_hashes(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'hidden.hashes@example.net');

        $serializedInvitation = $issued['invitation']->refresh()->toArray();
        $serializedLinkRequest = $issued['linkRequest']->refresh()->toArray();

        $this->assertArrayNotHasKey('token_hash', $serializedInvitation);
        $this->assertArrayNotHasKey('recovery_code_hash', $serializedInvitation);
        $this->assertArrayNotHasKey('token_hash', $serializedLinkRequest);
    }

    // ------------------------------------------------------------------
    // Resend (credential rotation)
    // ------------------------------------------------------------------

    public function test_resend_rotates_credentials_and_audits_the_generation(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'rotate.me@example.com');

        $previousTokenHash = $issued['invitation']->token_hash;

        $this->travel(2)->minutes();

        $resent = app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.5');

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(2, $invitation->credential_generation);
        $this->assertNotSame($issued['token'], $resent['token']);
        $this->assertNotSame($previousTokenHash, $invitation->token_hash);
        $this->assertSame(2, $invitation->sent_count);
        $this->assertSame(2, InvitationLinkRequest::query()->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.resent',
        ]);
    }

    public function test_the_cooldown_blocks_consecutive_resends(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'cooldown@example.com');

        $this->travel(10)->seconds();

        $this->expectException(InvitationRateLimitException::class);

        app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.7');
    }

    public function test_hourly_request_limits_apply_per_invitation(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'limit@example.com');

        // Five total requests per rolling hour: the initial send plus four.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->travelTo(now()->addMinutes(2));

            app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.9');
        }

        $this->expectException(InvitationRateLimitException::class);

        app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.9');
    }

    public function test_hourly_request_limits_apply_per_ip_across_initial_sends(): void
    {
        $admin = User::factory()->create();

        // Fixed base time so each send is spaced exactly 3 minutes apart;
        // all ten stay inside one 60-minute rolling window.
        $baseTime = now()->startOfMinute();

        for ($invitationIndex = 0; $invitationIndex < 10; $invitationIndex++) {
            $this->travelTo($baseTime->copy()->addMinutes($invitationIndex * 3));

            $lastInvitation = app(IssueUserInvitationAction::class)->execute(
                $admin,
                sprintf('ip.spam.%d@example.net', $invitationIndex),
                UserInvitation::METHOD_INVITATION_LINK,
                UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
                null,
                '198.51.100.99',
            );
        }

        // Ten initial sends already filled the per-IP rolling window; the
        // eleventh is blocked before any account row exists.
        $this->expectException(InvitationRateLimitException::class);

        app(IssueUserInvitationAction::class)->execute(
            $admin,
            'ip.spam.11@example.net',
            UserInvitation::METHOD_INVITATION_LINK,
            UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
            null,
            '198.51.100.99',
        );
    }

    // ------------------------------------------------------------------
    // Cancellation
    // ------------------------------------------------------------------

    public function test_cancel_marks_the_invitation_and_audits_the_reason(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'cancel.me@example.com');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Mistaken invitation');

        $invitation = $issued['invitation']->refresh();

        $this->assertTrue($invitation->isCancelled());
        $this->assertSame('Mistaken invitation', $invitation->cancelled_reason);
        $this->assertSame($admin->getKey(), $invitation->cancelled_by);
        $this->assertSame('cancelled', $invitation->state());

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->getKey(),
            'action' => 'invitation.cancelled',
        ]);
    }

    public function test_cancel_and_resend_after_cancellation_are_rejected(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'cancelled@example.com');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Duplicate request');

        try {
            app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Second attempt');

            $this->fail('A second cancellation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only pending invitations', $exception->getMessage());
        }

        $this->travel(2)->minutes();

        try {
            app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.11');

            $this->fail('A cancelled invitation must not receive a fresh link.');
        } catch (InvitationLinkException $exception) {
            $this->assertSame(InvitationLinkException::REASON_CANCELLED, $exception->reason);
        }
    }

    // ------------------------------------------------------------------
    // Recovery
    // ------------------------------------------------------------------

    public function test_recovery_rotates_credentials_for_a_live_invitation(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'recover.me@example.com');

        $this->travel(5)->minutes(); // Within the recovery-code window.

        $recovered = app(RecoverUserInvitationAction::class)->execute(
            'recover.me@example.com',
            $issued['recoveryCode'],
            '203.0.113.20',
        );

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(2, $invitation->credential_generation);
        $this->assertNotSame($issued['token'], $recovered['token']);
        $this->assertSame(2, $invitation->sent_count);
        $this->assertSame('pending', $invitation->state());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.recovered',
        ]);
    }

    public function test_invalid_recovery_attempts_are_neutral_and_counted(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'neutral@example.net');

        foreach ([1, 2, 3, 4] as $attempt) {
            $this->travelTo(now()->addMinutes($attempt));

            try {
                app(RecoverUserInvitationAction::class)->execute(
                    'neutral@example.net',
                    '000000',
                    '203.0.113.30',
                );

                $this->fail('Recovery with a wrong code must fail.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString(
                    'Recovery could not be completed',
                    $exception->getMessage(),
                );

                $this->assertDatabaseHas('user_invitations', [
                    'id' => $issued['invitation']->id,
                    'recovery_attempts' => $attempt,
                ]);
            }
        }

        // A wrong address yields the same neutral answer and no lockout.
        $this->travel(1)->minute();

        try {
            app(RecoverUserInvitationAction::class)->execute(
                'unknown.address@example.net',
                $issued['recoveryCode'],
                '203.0.113.30',
            );

            $this->fail('Recovery with an unknown address must fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Recovery could not be completed', $exception->getMessage());
        }

        $this->assertDatabaseHas('user_invitations', [
            'id' => $issued['invitation']->id,
            'recovery_attempts' => 4,
        ]);
    }

    public function test_five_failed_attempts_lock_recovery_for_thirty_minutes(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'lockout@example.net');

        try {
            app(RecoverUserInvitationAction::class)->execute('lockout@example.net', '000000');

            $this->fail('Recovery with a wrong code must fail.');
        } catch (ValidationException) {
        }

        $this->travel(2)->minutes();

        try {
            app(RecoverUserInvitationAction::class)->execute('lockout@example.net', '000000');

            $this->fail('Recovery with a wrong code must fail.');
        } catch (ValidationException) {
        }

        $this->travel(2)->minutes();

        try {
            app(RecoverUserInvitationAction::class)->execute('lockout@example.net', '000000');

            $this->fail('Recovery with a wrong code must fail.');
        } catch (ValidationException) {
        }

        $this->travel(2)->minutes();

        try {
            app(RecoverUserInvitationAction::class)->execute('lockout@example.net', '000000');

            $this->fail('Recovery with a wrong code must fail.');
        } catch (ValidationException) {
        }

        $this->travel(2)->minutes();

        try {
            app(RecoverUserInvitationAction::class)->execute('lockout@example.net', '000000');

            $this->fail('The fifth failure must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Recovery could not be completed', $exception->getMessage());
        }

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(5, $invitation->recovery_attempts);
        $this->assertNotNull($invitation->recovery_locked_until);
        $this->assertTrue($invitation->recovery_locked_until->greaterThan(now()));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.recovery_locked',
        ]);

        // Even the correct code stays locked during the window.
        $this->expectException(InvitationRateLimitException::class);

        app(RecoverUserInvitationAction::class)->execute('lockout@example.net', $issued['recoveryCode']);
    }

    public function test_recovery_of_a_cancelled_invitation_is_neutral(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'cancel.rec@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'No longer needed');

        $this->expectException(ValidationException::class);

        app(RecoverUserInvitationAction::class)->execute(
            'cancel.rec@example.net',
            $issued['recoveryCode'],
        );
    }

    // ------------------------------------------------------------------
    // Stale-link invalidation after credential rotation
    // ------------------------------------------------------------------

    public function test_the_previous_link_is_rejected_after_a_resend(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'stale.resend@example.net');

        $this->travel(2)->minutes();

        $resent = app(ResendUserInvitationAction::class)->execute($issued['invitation'], $admin, '203.0.113.15');

        try {
            app(AcceptInvitationAction::class)->execute($issued['token'], 'Accept-Password-1');

            $this->fail('An invalidated pre-rotation link must be rejected.');
        } catch (InvitationLinkException $exception) {
            $this->assertSame(InvitationLinkException::REASON_INVALIDATED, $exception->reason);
        }

        // Nothing was consumed by the rejected stale link.
        $this->assertSame(
            0,
            InvitationLinkRequest::query()->whereNotNull('consumed_at')->count(),
        );
        $this->assertSame(
            0,
            AuditLog::query()->where('action', 'invitation.accepted')->count(),
        );

        app(AcceptInvitationAction::class)->execute($resent['token'], 'Accept-Password-1');

        $invitedUser = User::query()->where('email', 'stale.resend@example.net')->firstOrFail();
        $this->assertNotNull($invitedUser->password);
    }

    public function test_the_previous_link_is_rejected_after_a_recovery(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'stale.recovery@example.net');

        $this->travel(5)->minutes(); // Within the recovery-code window.

        $recovered = app(RecoverUserInvitationAction::class)->execute(
            'stale.recovery@example.net',
            $issued['recoveryCode'],
            '203.0.113.21',
        );

        try {
            app(AcceptInvitationAction::class)->execute($issued['token'], 'Accept-Password-1');

            $this->fail('An invalidated pre-recovery link must be rejected.');
        } catch (InvitationLinkException $exception) {
            $this->assertSame(InvitationLinkException::REASON_INVALIDATED, $exception->reason);
        }

        app(AcceptInvitationAction::class)->execute($recovered['token'], 'Accept-Password-1');

        $invitedUser = User::query()->where('email', 'stale.recovery@example.net')->firstOrFail();
        $this->assertNotNull($invitedUser->password);
    }

    // ------------------------------------------------------------------
    // Single-use recovery code
    // ------------------------------------------------------------------

    public function test_a_recovered_code_cannot_recover_again(): void
    {
        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'one.code@example.net');

        $this->travel(5)->minutes(); // Within the recovery-code window.

        app(RecoverUserInvitationAction::class)->execute(
            'one.code@example.net',
            $issued['recoveryCode'],
            '203.0.113.22',
        );

        $invitation = $issued['invitation']->refresh();

        $this->assertNull($invitation->recovery_code_hash);
        $this->assertNull($invitation->recovery_code_expires_at);

        // Even the correct original code no longer authenticates recovery,
        // and the neutral answer does not expose what changed.
        $this->travel(2)->minutes(); // Past the resend cooldown.

        try {
            app(RecoverUserInvitationAction::class)->execute(
                'one.code@example.net',
                $issued['recoveryCode'],
                '203.0.113.22',
            );

            $this->fail('A consumed recovery code must not complete a second recovery.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Recovery could not be completed',
                $exception->getMessage(),
            );
        }

        $this->assertSame(2, $invitation->credential_generation);
    }

    // ------------------------------------------------------------------
    // Single-use acceptance invariant
    // ------------------------------------------------------------------

    public function test_a_second_acceptance_of_the_same_link_is_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'single.use@example.net');

        app(AcceptInvitationAction::class)->execute($issued['token'], 'Accept-Password-1');

        try {
            app(AcceptInvitationAction::class)->execute($issued['token'], 'Accept-Password-2');

            $this->fail('A second acceptance of the same link must be rejected.');
        } catch (InvitationLinkException $exception) {
            $this->assertSame(InvitationLinkException::REASON_CONSUMED, $exception->reason);
        }

        $this->assertSame(1, InvitationLinkRequest::query()
            ->whereNotNull('consumed_at')
            ->count());
    }
}
