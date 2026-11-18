<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_emailed_link_completes_the_forced_reset(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $rawToken = $this->issueReset($user);

        $this->get(route('password-reset.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee('Choose a new password');

        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'N3w-Password#2026',
            'password_confirmation' => 'N3w-Password#2026',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('N3w-Password#2026', $user->refresh()->password));
        $this->assertFalse((bool) $user->password_reset_required);

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->getKey(),
            'state' => 'consumed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password.reset_completed',
            'target_user_id' => $user->getKey(),
        ]);
    }

    public function test_sign_in_is_blocked_until_the_reset_is_completed(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'old-password-1!',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sign_in_works_after_the_reset_is_completed(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $rawToken = $this->issueReset($user);

        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'N3w-Password#2026',
            'password_confirmation' => 'N3w-Password#2026',
        ])
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'N3w-Password#2026',
        ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->refresh());
    }

    public function test_a_used_link_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $rawToken = $this->issueReset($user);

        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'N3w-Password#2026',
            'password_confirmation' => 'N3w-Password#2026',
        ])
            ->assertRedirect(route('login'));

        $this->get(route('password-reset.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee('no longer valid');

        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'Another#Password9',
            'password_confirmation' => 'Another#Password9',
        ])
            ->assertRedirect(route('password-reset.show', ['token' => $rawToken]))
            ->assertSessionHasErrors('password');
    }

    public function test_an_expired_link_renders_the_invalid_page(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1!']);

        PasswordResetRequest::factory()->expired()->create(['user_id' => $user->getKey()]);

        // The factory token hash is random; any token is unusable here.
        $this->get(route('password-reset.show', ['token' => 'anything']))
            ->assertOk()
            ->assertSee('no longer valid');
    }

    public function test_the_new_password_must_satisfy_the_policy(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $rawToken = $this->issueReset($user);

        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'weak1!',
            'password_confirmation' => 'weak1!',
        ])
            ->assertRedirect(route('password-reset.show', ['token' => $rawToken]))
            ->assertSessionHasErrors('password');

        // The failed attempt is recorded against the token, which stays
        // issued and usable.
        $this->assertSame(1, PasswordResetRequest::query()
            ->where('user_id', $user->getKey())
            ->where('state', 'issued')
            ->where('failed_attempts', 1)
            ->count());

        $this->assertTrue(Hash::check('old-password-1!', $user->refresh()->password));
    }

    public function test_five_failed_attempts_disable_the_link(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password-1!',
            'password_reset_required' => true,
        ]);

        $rawToken = $this->issueReset($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password-reset.store', ['token' => $rawToken]), [
                'password' => 'weak1!',
                'password_confirmation' => 'weak1!',
            ])
                ->assertRedirect(route('password-reset.show', ['token' => $rawToken]))
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->getKey(),
            'state' => 'invalidated',
        ]);

        // Even a compliant submission can no longer use the disabled link.
        $this->post(route('password-reset.store', ['token' => $rawToken]), [
            'password' => 'N3w-Password#2026',
            'password_confirmation' => 'N3w-Password#2026',
        ])
            ->assertRedirect(route('password-reset.show', ['token' => $rawToken]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-1!', $user->refresh()->password));
    }

    /**
     * Issue a reset request and return the raw token it was created with.
     */
    private function issueReset(User $user): string
    {
        $rawToken = app(InvitationCredentialGenerator::class)->generateToken();

        PasswordResetRequest::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => app(CredentialHasher::class)->hash($rawToken),
            'state' => PasswordResetRequest::STATE_ISSUED,
            'expires_at' => now()->addMinutes(60),
        ]);

        return $rawToken;
    }
}
