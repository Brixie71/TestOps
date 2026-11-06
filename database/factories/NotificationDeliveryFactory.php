<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'recipient_email' => fake()->unique()->safeEmail(),
            'notification_type' => 'test_notification',
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'failure_code' => null,
            'failure_message' => null,
            'user_message' => null,
            'delivered_at' => now(),
        ];
    }

    /**
     * Indicate that the original delivery failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDelivery::STATUS_FAILED,
            'failure_code' => 'mail_transport_failed',
            'failure_message' => 'The mail transport rejected the message.',
            'user_message' => 'Your email could not be delivered. Please try again later.',
            'delivered_at' => null,
        ]);
    }
}
