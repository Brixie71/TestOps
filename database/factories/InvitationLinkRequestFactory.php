<?php

namespace Database\Factories;

use App\Models\InvitationLinkRequest;
use App\Models\UserInvitation;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationLinkRequest>
 */
class InvitationLinkRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_invitation_id' => UserInvitation::factory(),
            'recipient_email' => fake()->safeEmail(),
            'credential_generation' => 1,
            'method' => UserInvitation::METHOD_INVITATION_LINK,
            'token_hash' => app(CredentialHasher::class)->hash(
                app(InvitationCredentialGenerator::class)->generateToken()
            ),
            'expires_at' => now()->addMinutes(15),
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Consumed by a successful acceptance.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now(),
        ]);
    }

    /**
     * Direct account-setup link.
     */
    public function directSetup(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
        ]);
    }
}
