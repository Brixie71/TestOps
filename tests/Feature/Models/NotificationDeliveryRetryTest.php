<?php

namespace Tests\Feature\Models;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class NotificationDeliveryRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_delivery_retry_belongs_to_its_original_delivery(): void
    {
        $delivery = NotificationDelivery::factory()->failed()->create();

        $retry = NotificationDeliveryRetry::factory()
            ->for($delivery, 'delivery')
            ->create();

        $this->assertTrue($retry->delivery->is($delivery));
    }

    public function test_retry_attempt_numbers_cannot_be_duplicated_for_one_delivery(): void
    {
        $delivery = NotificationDelivery::factory()->failed()->create();

        NotificationDeliveryRetry::factory()
            ->for($delivery, 'delivery')
            ->create(['attempt_number' => 1]);

        $this->expectException(QueryException::class);

        NotificationDeliveryRetry::factory()
            ->for($delivery, 'delivery')
            ->create(['attempt_number' => 1]);
    }

    public function test_pending_notification_delivery_retry_can_be_finalized_once(): void
    {
        $retry = NotificationDeliveryRetry::factory()->pending()->create();

        $retry->update([
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'completed_at' => now(),
        ]);

        $this->assertSame(
            NotificationDeliveryRetry::STATUS_FAILED,
            NotificationDeliveryRetry::query()->findOrFail($retry->getKey())->status,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Notification delivery retry records cannot be updated.');

        $retry->update([
            'status' => NotificationDeliveryRetry::STATUS_DELIVERED,
        ]);
    }

    public function test_finalized_notification_delivery_retry_cannot_be_updated(): void
    {
        $retry = NotificationDeliveryRetry::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Notification delivery retry records cannot be updated.');

        $retry->update([
            'status' => NotificationDeliveryRetry::STATUS_DELIVERED,
        ]);
    }

    public function test_notification_delivery_retry_cannot_be_deleted(): void
    {
        $retry = NotificationDeliveryRetry::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Notification delivery retry records cannot be deleted.');

        $retry->delete();
    }
}
