<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationHttpTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_ENDPOINT = '/email-verification/verify';

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmailVerificationService::class);
    }

    private function invitedUser(): User
    {
        $user = User::factory()->create(['account_status' => 'invited', 'email_verified_at' => null]);

        $user->emailAddresses()->first()->forceFill([
            'status' => 'reserved',
            'verified_at' => null,
        ])->save();

        return $user;
    }

    private function issuedFor(User $user): array
    {
        return $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );
    }

    public function test_a_valid_token_renders_the_confirmation_page_without_consuming(): void
    {
        $user = $this->invitedUser();

        $issued = $this->issuedFor($user);

        $this->get(self::VERIFY_ENDPOINT.'?token='.$issued['token'])
            ->assertOk()
            ->assertViewIs('email-verification.confirm')
            ->assertSeeText('Verify email');

        // The GET is a pure preview: the request stays issued and the
        // account stays invited, so a prefetch cannot burn the token.
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        $this->assertSame('invited', $user->refresh()->account_status);

        $this->assertSame(
            0,
            AuditLog::query()->where('action', 'email_verification.consumed')->count(),
        );
    }

    public function test_the_confirmation_post_completes_the_activation(): void
    {
        $user = $this->invitedUser();

        $issued = $this->issuedFor($user);

        $this->get(self::VERIFY_ENDPOINT.'?token='.$issued['token'])->assertOk();

        $this->post(self::VERIFY_ENDPOINT, ['token' => $issued['token']])
            ->assertOk()
            ->assertViewIs('email-verification.success');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'account_status' => 'active',
        ]);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'status' => 'current',
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_CONSUMED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.activated',
        ]);
    }

    public function test_a_token_cannot_be_replayed(): void
    {
        $user = $this->invitedUser();

        $issued = $this->issuedFor($user);

        $this->post(self::VERIFY_ENDPOINT, ['token' => $issued['token']])->assertOk();

        $this->post(self::VERIFY_ENDPOINT, ['token' => $issued['token']])
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->assertSame('active', $user->refresh()->account_status);

        // Only one consumption audit exists even after the replay attempt.
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'email_verification.consumed')->count(),
        );
    }

    public function test_an_expired_token_is_rejected_and_reclassified(): void
    {
        $user = $this->invitedUser();

        $issued = $this->issuedFor($user);

        $this->travel(6)->minutes();

        $this->get(self::VERIFY_ENDPOINT.'?token='.$issued['token'])
            ->assertOk()
            ->assertViewIs('email-verification.invalid')
            ->assertSeeText('expired');

        $this->post(self::VERIFY_ENDPOINT, ['token' => $issued['token']])
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'account_status' => 'invited',
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_EXPIRED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'email_verification.expired',
        ]);
    }

    public function test_a_missing_or_invalid_token_renders_the_invalid_page(): void
    {
        $this->get(self::VERIFY_ENDPOINT)
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->get(self::VERIFY_ENDPOINT.'?token=not-a-real-token')
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->post(self::VERIFY_ENDPOINT)
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->post(self::VERIFY_ENDPOINT, ['token' => 'not-a-real-token'])
            ->assertOk()
            ->assertViewIs('email-verification.invalid');
    }
}
