<?php

namespace Database\Factories;

use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerificationRequest>
 */
class EmailVerificationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'user_id' => User::factory()->state(['email' => $email]),
            'purpose' => EmailVerificationRequest::PURPOSE_ACCOUNT_ACTIVATION,
            'email' => $email,
            'token_hash' => app(CredentialHasher::class)->hash(
                app(InvitationCredentialGenerator::class)->generateToken()
            ),
            'state' => EmailVerificationRequest::STATE_ISSUED,
            'expires_at' => now()->addMinutes(5),
            'issued_at' => now(),
        ];
    }

    /**
     * Consumed state with a consumption timestamp.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => EmailVerificationRequest::STATE_CONSUMED,
            'consumed_at' => now(),
        ]);
    }

    /**
     * Expired state beyond the expiry timestamp.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => EmailVerificationRequest::STATE_EXPIRED,
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
            'state' => EmailVerificationRequest::STATE_INVALIDATED,
            'invalidated_at' => now(),
        ]);
    }

    /**
     * Reserved email_change purpose.
     */
    public function emailChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => EmailVerificationRequest::PURPOSE_EMAIL_CHANGE,
        ]);
    }
}
