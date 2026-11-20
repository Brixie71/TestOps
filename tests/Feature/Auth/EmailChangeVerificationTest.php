<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailChangeVerificationMailable;
use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailChangeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Current#Password1';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function user(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }

    /**
     * Request a change through the profile form and return the emailed token.
     */
    private function requestChange(User $user, string $email): string
    {
        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => $email,
            'current_password' => self::PASSWORD,
        ])->assertSessionHasNoErrors();

        $mailable = Mail::sent(EmailChangeVerificationMailable::class)->last();

        $this->assertNotNull($mailable);

        return basename(parse_url($mailable->verificationUrl, PHP_URL_PATH));
    }

    public function test_the_confirmation_get_does_not_consume_the_token(): void
    {
        $user = $this->user();
        $token = $this->requestChange($user, 'new-address@example.com');

        $this->get(route('email-change.confirm.show', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('email-verification.change-confirm')
            ->assertSee('new-address@example.com');

        $this->assertSame(1, EmailVerificationRequest::query()
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->count());

        $this->assertNotSame('new-address@example.com', $user->refresh()->email);
    }

    public function test_confirming_the_link_moves_the_address_and_reserves_the_old_one(): void
    {
        $user = $this->user();
        $previousEmail = (string) $user->email;
        $token = $this->requestChange($user, 'new-address@example.com');

        $this->post(route('email-change.confirm.store', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('email-verification.change-success');

        $user->refresh();

        $this->assertSame('new-address@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => 'new-address@example.com',
            'status' => UserEmailAddress::STATUS_CURRENT,
        ]);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => $previousEmail,
            'status' => UserEmailAddress::STATUS_PREVIOUS,
        ]);

        $this->assertDatabaseMissing('user_email_addresses', [
            'user_id' => $user->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'purpose' => EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            'email' => 'new-address@example.com',
            'state' => EmailVerificationRequest::STATE_CONSUMED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.email.changed',
            'target_user_id' => $user->getKey(),
        ]);
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $user = $this->user();
        $token = $this->requestChange($user, 'new-address@example.com');

        $this->post(route('email-change.confirm.store', ['token' => $token]))->assertOk();

        $this->post(route('email-change.confirm.store', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('email-verification.invalid')
            ->assertSeeText('already been used');
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = $this->user();
        $token = $this->requestChange($user, 'new-address@example.com');

        EmailVerificationRequest::query()
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->update(['expires_at' => now()->subMinute()]);

        $this->post(route('email-change.confirm.store', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('email-verification.invalid')
            ->assertSeeText('expired');

        $this->assertNotSame('new-address@example.com', $user->refresh()->email);

        $this->assertDatabaseHas('email_verification_requests', [
            'purpose' => EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            'state' => EmailVerificationRequest::STATE_EXPIRED,
        ]);
    }

    public function test_a_replaced_token_cannot_move_the_address(): void
    {
        $user = $this->user();
        $firstToken = $this->requestChange($user, 'first@example.com');

        // §10.3: a replacement respects the 60-second resend cooldown.
        $this->travel(61)->seconds();

        $this->requestChange($user, 'second@example.com');

        $this->post(route('email-change.confirm.store', ['token' => $firstToken]))
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->assertNotSame('first@example.com', $user->refresh()->email);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => 'second@example.com',
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_an_account_activation_token_is_rejected_by_the_email_change_endpoint(): void
    {
        $user = $this->user();

        $issued = app(EmailVerificationService::class)->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->get(route('email-change.confirm.show', ['token' => $issued['token']]))
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->post(route('email-change.confirm.store', ['token' => $issued['token']]))
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->get(route('email-change.confirm.show', ['token' => 'not-a-real-token']))
            ->assertOk()
            ->assertViewIs('email-verification.invalid');

        $this->post(route('email-change.confirm.store', ['token' => 'not-a-real-token']))
            ->assertOk()
            ->assertViewIs('email-verification.invalid');
    }
}
