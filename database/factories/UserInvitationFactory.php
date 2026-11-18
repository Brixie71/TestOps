<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasher = app(CredentialHasher::class);
        $generator = app(InvitationCredentialGenerator::class);

        return [
            'user_id' => User::factory(),
            'invited_email' => fake()->unique()->safeEmail(),
            'token_hash' => $hasher->hash($generator->generateToken()),
            'expires_at' => now()->addMinutes(15),
            'method' => UserInvitation::METHOD_INVITATION_LINK,
            'acceptance_flow' => UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
            'credential_generation' => 1,
            'recovery_code_hash' => $hasher->hash($generator->generateRecoveryCode()),
            'recovery_code_expires_at' => now()->addMinutes(15),
            'recovery_attempts' => 0,
            'sent_count' => 0,
        ];
    }

    /**
     * Cancelled state with a recorded reason.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'cancelled_reason' => 'Cancelled by an administrator.',
        ]);
    }

    /**
     * Accepted state; the offered account already used the invitation.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Direct account setup instead of an invitation link.
     */
    public function directSetup(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
        ]);
    }
}
