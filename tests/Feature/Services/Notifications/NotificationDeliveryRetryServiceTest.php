<?php

namespace Tests\Feature\Services\Notifications;

use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationDeliveryRetryService;
use App\Services\Notifications\NotificationRetryException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class NotificationDeliveryRetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationDeliveryRetryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationDeliveryRetryService::class);
    }

    public function test_failed_delivery_can_be_retried(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $attemptedFor = [];

        $retry = $this->service->retry(
            $admin,
            $delivery,
            function (NotificationDelivery $attempted) use (&$attemptedFor): void {
                $attemptedFor[] = $attempted->getKey();
            },
        );

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);
        $this->assertSame(1, $retry->attempt_number);
        $this->assertNotNull($retry->completed_at);
        $this->assertSame([$delivery->getKey()], $attemptedFor);
    }

    public function test_successful_delivery_cannot_be_retried(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->create();

        $this->expectException(NotificationRetryException::class);
        $this->expectExceptionMessage('Only failed notification deliveries can be retried.');

        $this->service->retry($admin, $delivery, function (): void {
            $this->fail('The delivery attempt must not run for a successful delivery.');
        });
    }

    public function test_successful_retry_is_recorded_without_modifying_the_original_delivery(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $retry = $this->service->retry($admin, $delivery, function (): void {
            // Transport succeeds silently.
        });

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);

        $storedDelivery = NotificationDelivery::query()->findOrFail($delivery->getKey());

        $this->assertSame(NotificationDelivery::STATUS_FAILED, $storedDelivery->status);
        $this->assertSame('mail_transport_failed', $storedDelivery->failure_code);
        $this->assertNull($storedDelivery->delivered_at);
        $this->assertSame(
            NotificationDeliveryRetry::STATUS_DELIVERED,
            $storedDelivery->effectiveStatus(),
        );
    }

    public function test_failed_retry_is_recorded_without_modifying_the_original_delivery(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $retry = $this->service->retry($admin, $delivery, function (): void {
            throw new TransportException('SMTP temporarily unavailable.');
        });

        $this->assertSame(NotificationDeliveryRetry::STATUS_FAILED, $retry->status);
        $this->assertSame('mail_transport_failed', $retry->failure_code);
        $this->assertNotNull($retry->failure_message);
        $this->assertNotNull($retry->user_message);
        $this->assertNotNull($retry->completed_at);
        $this->assertStringNotContainsString('SMTP temporarily unavailable.', (string) $retry->failure_message);

        $storedDelivery = NotificationDelivery::query()->findOrFail($delivery->getKey());

        $this->assertSame(NotificationDelivery::STATUS_FAILED, $storedDelivery->status);
        $this->assertSame('mail_transport_failed', $storedDelivery->failure_code);
    }

    public function test_retry_attempt_numbers_increment_correctly(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $failingAttempt = function (): void {
            throw new TransportException('SMTP down.');
        };

        $this->service->retry($admin, $delivery, $failingAttempt);

        $this->travel(6)->minutes();
        $this->service->retry($admin, $delivery, $failingAttempt);

        $this->travel(6)->minutes();
        $thirdRetry = $this->service->retry($admin, $delivery, $failingAttempt);

        $this->assertSame(3, $thirdRetry->attempt_number);

        $this->assertSame(
            [1, 2, 3],
            NotificationDeliveryRetry::query()
                ->where('notification_delivery_id', $delivery->getKey())
                ->orderBy('attempt_number')
                ->pluck('attempt_number')
                ->all(),
        );
    }

    public function test_fourth_manual_retry_is_rejected(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        NotificationDeliveryRetry::factory()
            ->count(3)
            ->for($delivery, 'delivery')
            ->state(new Sequence(
                ['attempt_number' => 1],
                ['attempt_number' => 2],
                ['attempt_number' => 3],
            ))
            ->create();

        $attemptRan = false;

        try {
            $this->service->retry($admin, $delivery, function () use (&$attemptRan): void {
                $attemptRan = true;
            });

            $this->fail('The fourth manual retry must be rejected.');
        } catch (NotificationRetryException $exception) {
            $this->assertStringContainsString(
                'The maximum of 3 manual retries has been reached.',
                $exception->getMessage(),
            );
        }

        $this->assertFalse($attemptRan);
        $this->assertDatabaseCount('notification_delivery_retries', 3);
    }

    public function test_cooldown_is_enforced(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $this->service->retry($admin, $delivery, function (): void {
            throw new TransportException('SMTP down.');
        });

        $this->expectException(NotificationRetryException::class);
        $this->expectExceptionMessage('A manual retry is not allowed for another');

        $this->service->retry($admin, $delivery, function (): void {
            $this->fail('The retry attempt must not run during the cooldown.');
        });
    }

    public function test_successful_retry_outcome_is_audited(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $user = User::factory()->create();
        $delivery = NotificationDelivery::factory()
            ->failed()
            ->create(['user_id' => $user->getKey()]);

        $this->service->retry($admin, $delivery, function (): void {
            // Transport succeeds silently.
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.retry',
            'actor_user_id' => $admin->getKey(),
            'target_user_id' => $user->getKey(),
        ]);

        $auditLog = AuditLog::query()->sole();

        $this->assertSame([
            'notification_delivery_id' => $delivery->getKey(),
            'notification_type' => $delivery->notification_type,
            'attempt_number' => 1,
            'outcome' => NotificationDeliveryRetry::STATUS_DELIVERED,
        ], $auditLog->after_values);
    }

    public function test_failed_retry_outcome_is_audited(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $this->service->retry($admin, $delivery, function (): void {
            throw new TransportException('SMTP down.');
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.retry',
            'actor_user_id' => $admin->getKey(),
            'target_user_id' => null,
        ]);

        $auditLog = AuditLog::query()->sole();

        $this->assertSame(
            NotificationDeliveryRetry::STATUS_FAILED,
            $auditLog->after_values['outcome'],
        );
    }

    public function test_user_without_system_role_cannot_retry(): void
    {
        $ordinaryUser = User::factory()->create();
        $delivery = NotificationDelivery::factory()->failed()->create();

        $this->expectException(NotificationRetryException::class);
        $this->expectExceptionMessage('You are not authorized to retry notification deliveries.');

        $this->service->retry($ordinaryUser, $delivery, function (): void {
            $this->fail('The delivery attempt must not run for an unauthorized actor.');
        });
    }

    public function test_project_scoped_role_cannot_retry(): void
    {
        $projectUser = $this->createUserWithRole('PROJECT_ADMIN', 'project');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $this->expectException(NotificationRetryException::class);
        $this->expectExceptionMessage('You are not authorized to retry notification deliveries.');

        $this->service->retry($projectUser, $delivery, function (): void {
            $this->fail('The delivery attempt must not run for an unauthorized actor.');
        });
    }

    public function test_system_admin_can_retry(): void
    {
        $systemAdmin = $this->createUserWithRole('SYSTEM_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $retry = $this->service->retry($systemAdmin, $delivery, function (): void {
            // Transport succeeds silently.
        });

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $retry->status);
    }

    public function test_concurrent_retry_requests_cannot_bypass_the_cooldown_or_limit(): void
    {
        $admin = $this->createUserWithRole('SUPER_ADMIN', 'system');
        $delivery = NotificationDelivery::factory()->failed()->create();

        $concurrentRejection = null;

        $outerRetry = $this->service->retry(
            $admin,
            $delivery,
            function () use ($admin, $delivery, &$concurrentRejection): void {
                // A second request arriving while the first attempt's
                // transport is still running must be rejected before it
                // can send anything or consume an extra attempt.
                try {
                    $this->service->retry($admin, $delivery, function (): void {
                        $this->fail('The concurrent attempt must not reach the transport.');
                    });
                } catch (NotificationRetryException $exception) {
                    $concurrentRejection = $exception;
                }
            },
        );

        $this->assertSame(NotificationDeliveryRetry::STATUS_DELIVERED, $outerRetry->status);
        $this->assertSame(1, $outerRetry->attempt_number);
        $this->assertInstanceOf(NotificationRetryException::class, $concurrentRejection);
        $this->assertStringContainsString(
            'A manual retry is not allowed for another',
            $concurrentRejection->getMessage(),
        );
        $this->assertDatabaseCount('notification_delivery_retries', 1);
    }

    /**
     * Create a user assigned to the given role, matching the established
     * test convention for role-based authorization.
     */
    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->create([
            'name' => $code,
            'code' => $code,
            'scope' => $scope,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
