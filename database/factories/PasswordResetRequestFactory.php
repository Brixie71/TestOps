<?php

namespace Database\Factories;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasswordResetRequest>
 */
class PasswordResetRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => app(CredentialHasher::class)->hash(
                app(InvitationCredentialGenerator::class)->generateToken()
            ),
            'state' => PasswordResetRequest::STATE_ISSUED,
            'failed_attempts' => 0,
            'expires_at' => now()->addMinutes(60),
        ];
    }

    /**
     * Consumed state with a consumption timestamp.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PasswordResetRequest::STATE_CONSUMED,
            'consumed_at' => now(),
        ]);
    }

    /**
     * Expired state beyond the expiry timestamp.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PasswordResetRequest::STATE_EXPIRED,
            'expires_at' => now()->subMinutes(10),
            'expired_at' => now(),
        ]);
    }

    /**
     * Invalidated state.
     */
    public function invalidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PasswordResetRequest::STATE_INVALIDATED,
            'invalidated_at' => now(),
        ]);
    }
}
