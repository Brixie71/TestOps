<?php

namespace Tests\Feature\Services\Notifications;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\NotificationDeliveryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class NotificationDeliveryRecorderTest extends TestCase
{
    use RefreshDatabase;

    private NotificationDeliveryRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new NotificationDeliveryRecorder;
    }

    public function test_successful_delivery_is_recorded(): void
    {
        $user = User::factory()->create();

        $delivery = $this->recorder->recordSuccess(
            $user,
            'recipient@example.test',
            'test_notification',
        );

        $this->assertNotNull($delivery);

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->getKey(),
            'user_id' => $user->getKey(),
            'recipient_email' => 'recipient@example.test',
            'notification_type' => 'test_notification',
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'failure_code' => null,
            'failure_message' => null,
            'user_message' => null,
        ]);

        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_failed_delivery_is_recorded(): void
    {
        $user = User::factory()->create();

        $delivery = $this->recorder->recordFailure(
            $user,
            'recipient@example.test',
            'test_notification',
            new TransportException('SMTP connection refused.'),
        );

        $this->assertNotNull($delivery);

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->getKey(),
            'user_id' => $user->getKey(),
            'recipient_email' => 'recipient@example.test',
            'notification_type' => 'test_notification',
            'status' => NotificationDelivery::STATUS_FAILED,
            'failure_code' => 'mail_transport_failed',
            'user_message' => 'Your email could not be delivered. Please try again later.',
            'delivered_at' => null,
        ]);

        $this->assertNotNull($delivery->failure_message);
    }

    public function test_failure_details_are_sanitized(): void
    {
        $delivery = $this->recorder->recordFailure(
            null,
            'recipient@example.test',
            'test_notification',
            new TransportException('Raw transport text must never be stored.'),
        );

        $this->assertNotNull($delivery);

        $this->assertStringNotContainsString(
            'Raw transport text must never be stored.',
            (string) $delivery->failure_message,
        );

        $this->assertSame('mail_transport_failed', $delivery->failure_code);

        $this->assertStringContainsString(
            TransportException::class,
            (string) $delivery->failure_message,
        );
    }

    public function test_user_facing_failure_explanation_is_separate_from_internal_diagnostics(): void
    {
        $delivery = $this->recorder->recordFailure(
            null,
            'recipient@example.test',
            'test_notification',
            new TransportException('Internal diagnostic text.'),
        );

        $this->assertNotNull($delivery);

        $this->assertNotSame($delivery->failure_message, $delivery->user_message);

        $this->assertStringNotContainsString('TransportException', (string) $delivery->user_message);

        $this->assertStringNotContainsString('Internal diagnostic text.', (string) $delivery->user_message);
    }

    public function test_non_transport_exceptions_use_a_generic_failure_classification(): void
    {
        $delivery = $this->recorder->recordFailure(
            null,
            'recipient@example.test',
            'test_notification',
            new RuntimeException('Unexpected failure.'),
        );

        $this->assertSame('delivery_failed', $delivery->failure_code);
    }

    public function test_sensitive_values_are_never_persisted(): void
    {
        $sensitiveText = 'Reset link https://example.test/reset?token=abc123secret password=hunter2';

        $delivery = $this->recorder->recordFailure(
            null,
            'recipient@example.test',
            'test_notification',
            new TransportException($sensitiveText),
        );

        $this->assertNotNull($delivery);

        $storedRow = json_encode(
            NotificationDelivery::query()->findOrFail($delivery->getKey())->getAttributes(),
            JSON_THROW_ON_ERROR,
        );

        $this->assertStringNotContainsString('abc123secret', $storedRow);
        $this->assertStringNotContainsString('hunter2', $storedRow);
        $this->assertStringNotContainsString('https://example.test', $storedRow);
    }

    public function test_delivery_failure_does_not_roll_back_a_successful_account_operation(): void
    {
        DB::transaction(function (): void {
            $user = User::factory()->create();

            $recorded = $this->recorder->recordFailure(
                $user,
                $user->email,
                'test_notification',
                new TransportException('SMTP unavailable.'),
            );

            $this->assertNotNull($recorded);
        });

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }
}
