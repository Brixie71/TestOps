<?php

namespace Tests\Feature\Auth;

use App\Actions\IssueUserInvitationAction;
use App\Actions\ResendUserInvitationAction;
use App\Models\InvitationLinkRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\InvitationDeliveryService;
use App\Services\Notifications\NotificationDeliveryRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class InvitationDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ResendUserInvitationAction $resendAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resendAction = app(ResendUserInvitationAction::class);
    }

    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => $scope],
            ['name' => $code],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_a_successful_resend_records_one_delivered_row_and_links_it(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'delivery.ok@example.net');

        // Issue does not send; the controller would. Send manually to prove
        // the delivery linkage on the re-issued link request.
        $this->travel(2)->minutes();

        $resent = $this->resendAction->execute($issued['invitation'], $admin, '203.0.113.40');

        $delivery = app(InvitationDeliveryService::class)
            ->sendInvitationLink(
                $resent['linkRequest'],
                $resent['token'],
                '',
            );

        $this->assertNotNull($delivery);
        $this->assertSame(NotificationDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame('invitation_link', $delivery->notification_type);
        $this->assertSame('delivery.ok@example.net', $delivery->recipient_email);

        $this->assertDatabaseHas('invitation_link_requests', [
            'id' => $resent['linkRequest']->id,
            'delivery_id' => $delivery->getKey(),
        ]);

        Mail::assertSentCount(1);
    }

    public function test_a_transport_failure_is_classified_and_never_throws(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'delivery.fail@example.net');

        Mail::shouldReceive('to')
            ->andReturnSelf()
            ->shouldReceive('send')
            ->andThrow(new TransportException('SMTP connection reset.'));

        $delivery = app(InvitationDeliveryService::class)
            ->sendInvitationLink($issued['linkRequest'], $issued['token'], $issued['recoveryCode']);

        $this->assertNotNull($delivery);
        $this->assertSame(NotificationDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame('mail_transport_failed', $delivery->failure_code);
        $this->assertStringNotContainsString(
            'SMTP connection reset',
            (string) $delivery->failure_message,
        );
        $this->assertStringNotContainsString(
            $issued['token'],
            (string) $delivery->failure_message,
        );

        $this->assertDatabaseHas('invitation_link_requests', [
            'id' => $issued['linkRequest']->id,
            'delivery_id' => $delivery->getKey(),
        ]);
    }

    public function test_the_retry_closure_rotates_credentials_and_resends(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $issued = app(IssueUserInvitationAction::class)->execute($admin, 'retry.me@example.net');

        $failedDelivery = NotificationDelivery::factory()->failed()->create([
            'user_id' => $issued['invitation']->user_id,
            'recipient_email' => 'retry.me@example.net',
            'notification_type' => 'invitation_link',
        ]);

        $issued['linkRequest']->forceFill(['delivery_id' => $failedDelivery->getKey()])->save();

        Mail::fake();

        // Respect the resend cooldown before the manual retry.
        $this->travel(2)->minutes();

        $retry = app(NotificationDeliveryRetryService::class)->retry(
            $admin,
            $failedDelivery,
            $this->resendAction->retryClosure($admin),
        );

        $this->assertNotNull($retry);

        $invitation = $issued['invitation']->refresh();

        $this->assertSame(2, $invitation->credential_generation);
        $this->assertSame(2, $invitation->sent_count);

        // The retry sent the rotated link for the new generation, linked to
        // a fresh delivered transport attempt through the recorder.
        Mail::assertSentCount(1);

        $newLinkRequest = InvitationLinkRequest::query()
            ->latest('id')
            ->first();

        $this->assertNotSame($issued['linkRequest']->id, $newLinkRequest->id);
        $this->assertSame(2, $newLinkRequest->credential_generation);
        $this->assertNotNull($newLinkRequest->delivery_id);
    }

    public function test_the_retry_closure_reports_a_missing_link_request(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $failedDelivery = NotificationDelivery::factory()->failed()->create([
            'notification_type' => 'invitation_link',
        ]);

        // The retry service records a closure failure as an unsuccessful
        // attempt; it never rethrows out of retry().
        $retry = app(NotificationDeliveryRetryService::class)->retry(
            $admin,
            $failedDelivery,
            $this->resendAction->retryClosure($admin),
        );

        $this->assertSame(NotificationDeliveryRetry::STATUS_FAILED, $retry->status);
        $this->assertSame('delivery_failed', $retry->failure_code);
        $this->assertSame(0, InvitationLinkRequest::query()->count());
    }
}
