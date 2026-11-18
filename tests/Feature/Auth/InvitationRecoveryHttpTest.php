<?php

namespace Tests\Feature\Auth;

use App\Actions\IssueUserInvitationAction;
use App\Mail\InvitationLinkMailable;
use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class InvitationRecoveryHttpTest extends TestCase
{
    use RefreshDatabase;

    private const RECOVER_ENDPOINT = '/invitations/recover';

    private function issueInvitation(string $email): array
    {
        $admin = User::factory()->create();

        return app(IssueUserInvitationAction::class)->execute($admin, $email);
    }

    private function recoveryPost(string $email, string $code): TestResponse
    {
        return $this->from('/invitations/recover')
            ->post(self::RECOVER_ENDPOINT, [
                'email' => $email,
                'recovery_code' => $code,
            ]);
    }

    public function test_a_valid_recovery_sends_one_replacement_email_and_records_the_delivery(): void
    {
        Mail::fake();

        $issued = $this->issueInvitation('recover.http@example.net');

        $this->travel(2)->minutes(); // Past the resend cooldown.

        $this->recoveryPost('recover.http@example.net', $issued['recoveryCode'])
            ->assertOk()
            ->assertViewIs('invitations.recovery-success')
            ->assertSeeText('recover.http@example.net');

        // Exactly one replacement email, carrying the current-generation
        // acceptance link.
        Mail::assertSent(InvitationLinkMailable::class, 1);

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(2, $invitation->credential_generation);

        $newLinkRequest = InvitationLinkRequest::query()
            ->where('user_invitation_id', $invitation->getKey())
            ->where('credential_generation', 2)
            ->firstOrFail();

        $this->assertNotNull($newLinkRequest->delivery_id);

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $newLinkRequest->delivery_id,
            'notification_type' => 'invitation_link',
            'recipient_email' => 'recover.http@example.net',
            'status' => NotificationDelivery::STATUS_DELIVERED,
        ]);
    }

    public function test_a_valid_recovery_invalidates_the_previous_link(): void
    {
        Mail::fake();

        $issued = $this->issueInvitation('stale.recover.http@example.net');

        $this->travel(2)->minutes();

        $newAcceptPath = null;

        $this->recoveryPost('stale.recover.http@example.net', $issued['recoveryCode'])->assertOk();

        Mail::assertSent(InvitationLinkMailable::class, function (
            InvitationLinkMailable $mail
        ) use (&$newAcceptPath): bool {
            $newAcceptPath = (string) parse_url($mail->acceptUrl, PHP_URL_PATH);

            return true;
        });

        // The old link no longer renders the acceptance form for any
        // purpose; the newly emailed link still does.
        $this->get('/invitations/accept/'.$issued['token'])
            ->assertOk()
            ->assertViewIs('invitations.invalid')
            ->assertSeeText('no longer valid');

        $this->get($newAcceptPath)
            ->assertOk()
            ->assertViewIs('invitations.accept')
            ->assertSeeText('stale.recover.http@example.net');
    }

    public function test_a_delivery_failure_is_recorded_without_cancelling_the_invitation(): void
    {
        $issued = $this->issueInvitation('recover.fail@example.net');

        $this->travel(2)->minutes();

        Mail::shouldReceive('to')
            ->andReturnSelf()
            ->shouldReceive('send')
            ->andThrow(new TransportException('SMTP connection reset.'));

        // The response stays neutral: the visitor cannot distinguish a
        // failed transport from a successful send.
        $this->recoveryPost('recover.fail@example.net', $issued['recoveryCode'])
            ->assertOk()
            ->assertViewIs('invitations.recovery-success');

        $invitation = $issued['invitation']->refresh();

        $this->assertNull($invitation->revoked_at);
        $this->assertSame('pending', $invitation->state());
        $this->assertSame(2, $invitation->credential_generation);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_type' => 'invitation_link',
            'recipient_email' => 'recover.fail@example.net',
            'status' => NotificationDelivery::STATUS_FAILED,
        ]);

        $this->assertStringNotContainsString(
            'SMTP connection reset',
            (string) (NotificationDelivery::query()
                ->where('recipient_email', 'recover.fail@example.net')
                ->first()->failure_message),
        );
    }

    public function test_ineligible_recoveries_stay_neutral_and_send_nothing(): void
    {
        Mail::fake();

        $issued = $this->issueInvitation('ineligible.recover@example.net');

        $this->travel(2)->minutes();

        $this->recoveryPost('ineligible.recover@example.net', '000000')
            ->assertSessionHasErrors(['recovery']);

        $this->recoveryPost('unknown.person@example.net', $issued['recoveryCode'])
            ->assertSessionHasErrors(['recovery']);

        Mail::assertNothingSent();

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(1, $invitation->credential_generation);
        $this->assertSame(0, NotificationDelivery::query()->count());
    }

    public function test_the_resend_cooldown_remains_enforced_for_recovery(): void
    {
        Mail::fake();

        $issued = $this->issueInvitation('cooldown.recover@example.net');

        // Within 60 seconds of the initial send: blocked before any
        // rotation or delivery happens.
        $this->recoveryPost('cooldown.recover@example.net', $issued['recoveryCode'])
            ->assertSessionHasErrors(['recovery']);

        Mail::assertNothingSent();

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(1, $invitation->credential_generation);
        $this->assertNotNull($invitation->recovery_code_hash);
        $this->assertSame(0, NotificationDelivery::query()->count());
    }

    public function test_five_failed_lockout_attempts_block_recovery(): void
    {
        $issued = $this->issueInvitation('lockout.http@example.net');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->travelTo(now()->addMinutes($attempt + 1));

            $this->recoveryPost('lockout.http@example.net', '000000')
                ->assertSessionHasErrors(['recovery']);
        }

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(5, $invitation->recovery_attempts);
        $this->assertNotNull($invitation->recovery_locked_until);
        $this->assertTrue($invitation->recovery_locked_until->greaterThan(now()));

        $this->travel(1)->minute();

        $this->recoveryPost('lockout.http@example.net', $issued['recoveryCode'])
            ->assertSessionHasErrors(['recovery']);

        // A locked-out invitation is never rotated, delivered to, or
        // exposed by anything beyond the neutral answer.
        $this->assertSame(5, $invitation->refresh()->recovery_attempts);
        $this->assertSame(1, $invitation->credential_generation);
        $this->assertSame(0, NotificationDelivery::query()->count());
    }
}
