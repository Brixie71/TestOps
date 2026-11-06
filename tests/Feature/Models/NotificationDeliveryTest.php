<?php

namespace Tests\Feature\Models;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_delivery_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $delivery = NotificationDelivery::factory()
            ->create(['user_id' => $user->getKey()]);

        $this->assertTrue($delivery->user->is($user));
    }

    public function test_notification_delivery_survives_permanent_user_deletion_with_null_user(): void
    {
        $user = User::factory()->create();

        $delivery = NotificationDelivery::factory()
            ->create(['user_id' => $user->getKey()]);

        $user->forceDelete();

        $storedDelivery = NotificationDelivery::query()->findOrFail($delivery->getKey());

        $this->assertNull($storedDelivery->user_id);
        $this->assertSame(
            NotificationDelivery::STATUS_DELIVERED,
            $storedDelivery->status,
        );
    }

    public function test_notification_delivery_has_multiple_retries_in_attempt_order(): void
    {
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

        $retries = $delivery->retries()->get();

        $this->assertCount(3, $retries);
        $this->assertSame([1, 2, 3], $retries->pluck('attempt_number')->all());
    }

    public function test_notification_delivery_effective_status_uses_latest_retry_without_changing_original_status(): void
    {
        $delivery = NotificationDelivery::factory()->failed()->create();

        NotificationDeliveryRetry::factory()
            ->count(2)
            ->for($delivery, 'delivery')
            ->state(new Sequence(
                ['attempt_number' => 1],
                ['attempt_number' => 2],
            ))
            ->delivered()
            ->create();

        $this->assertSame(
            NotificationDeliveryRetry::STATUS_DELIVERED,
            $delivery->effectiveStatus(),
        );

        $this->assertSame(
            NotificationDelivery::STATUS_FAILED,
            NotificationDelivery::query()
                ->findOrFail($delivery->getKey())
                ->status,
        );
    }

    public function test_notification_delivery_cannot_be_updated(): void
    {
        $delivery = NotificationDelivery::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Notification delivery records cannot be updated.');

        $delivery->status = NotificationDelivery::STATUS_FAILED;
        $delivery->save();
    }

    public function test_notification_delivery_cannot_be_deleted(): void
    {
        $delivery = NotificationDelivery::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Notification delivery records cannot be deleted.');

        $delivery->delete();
    }
}
