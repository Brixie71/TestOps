<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryRetry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDeliveryRetry>
 */
class NotificationDeliveryRetryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_delivery_id' => NotificationDelivery::factory(),
            'attempt_number' => 1,
            'status' => NotificationDeliveryRetry::STATUS_FAILED,
            'failure_code' => 'mail_transport_failed',
            'failure_message' => 'The mail transport rejected the message.',
            'user_message' => 'Your email could not be delivered. Please try again later.',
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ];
    }

    /**
     * Indicate that the attempt is in flight and not yet finalized.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryRetry::STATUS_PENDING,
            'failure_code' => null,
            'failure_message' => null,
            'user_message' => null,
            'attempted_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the attempt succeeded.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryRetry::STATUS_DELIVERED,
            'failure_code' => null,
            'failure_message' => null,
            'user_message' => null,
            'attempted_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(10),
        ]);
    }
}
