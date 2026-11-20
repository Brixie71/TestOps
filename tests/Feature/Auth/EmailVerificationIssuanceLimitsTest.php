<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationAttemptCounter;
use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Auth\EmailVerificationRateLimitException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Email-verification issuance limits and failed-attempt counters
 * (§10.3-§10.8).
 */
class EmailVerificationIssuanceLimitsTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmailVerificationService::class);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function issueActivation(User $user): array
    {
        return $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );
    }

    public function test_the_first_issuance_is_never_rate_limited(): void
    {
        $user = $this->user();

        $issued = $this->issueActivation($user);

        $this->assertSame(EmailVerificationRequest::STATE_ISSUED, $issued['request']->state);
        $this->assertNotNull($issued['request']->issued_at);
    }

    public function test_a_replacement_inside_the_resend_cooldown_is_refused_without_side_effects(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        $this->travel(30)->seconds();

        try {
            $this->issueActivation($user);

            $this->fail('The resend cooldown must refuse the replacement.');
        } catch (EmailVerificationRateLimitException $exception) {
            $this->assertSame(
                EmailVerificationRateLimitException::REASON_RESEND_COOLDOWN,
                $exception->reason,
            );
        }

        $this->assertDatabaseCount('email_verification_requests', 1);
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $first['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_a_replacement_is_allowed_once_the_cooldown_elapses(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        $this->travel(61)->seconds();

        $second = $this->issueActivation($user);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $first['request']->id,
            'state' => EmailVerificationRequest::STATE_INVALIDATED,
        ]);
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $second['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_the_account_hourly_limit_refuses_replacement_without_invalidating_the_token(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        for ($issuance = 1; $issuance < EmailVerificationService::ACCOUNT_HOURLY_LIMIT; $issuance++) {
            $this->travel(61)->seconds();
            $this->issueActivation($user);
        }

        $this->assertDatabaseCount('email_verification_requests', EmailVerificationService::ACCOUNT_HOURLY_LIMIT);

        $latest = EmailVerificationRequest::query()
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->sole();

        $this->travel(61)->seconds();

        try {
            $this->issueActivation($user);

            $this->fail('The account hourly limit must refuse the replacement.');
        } catch (EmailVerificationRateLimitException $exception) {
            $this->assertSame(
                EmailVerificationRateLimitException::REASON_ACCOUNT_LIMIT,
                $exception->reason,
            );
        }

        $this->assertDatabaseCount('email_verification_requests', EmailVerificationService::ACCOUNT_HOURLY_LIMIT);
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $latest->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
        $this->assertNotSame($first['request']->id, $latest->id);
    }

    public function test_the_account_limit_uses_a_rolling_hour(): void
    {
        $user = $this->user();

        $this->issueActivation($user);

        for ($issuance = 1; $issuance < EmailVerificationService::ACCOUNT_HOURLY_LIMIT; $issuance++) {
            $this->travel(61)->seconds();
            $this->issueActivation($user);
        }

        // The whole window has rolled over: the next issuance is allowed.
        $this->travel(61)->minutes();

        $issued = $this->issueActivation($user);

        $this->assertSame(EmailVerificationRequest::STATE_ISSUED, $issued['request']->state);
    }

    public function test_the_configured_ip_hourly_limit_refuses_replacement_from_the_same_network(): void
    {
        $user = $this->user();

        $first = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
            now()->subMinutes(5),
            '203.0.113.7',
        );

        // Fill the per-IP window with issuances for other accounts.
        for ($row = 1; $row < 10; $row++) {
            EmailVerificationRequest::factory()->create([
                'purpose' => EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
                'requested_ip' => '203.0.113.7',
                'issued_at' => now()->subMinutes(2),
            ]);
        }

        $this->travel(61)->seconds();

        try {
            $this->service->issue(
                $user,
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
                $user->email,
                null,
                '203.0.113.7',
            );

            $this->fail('The per-IP hourly limit must refuse the replacement.');
        } catch (EmailVerificationRateLimitException $exception) {
            $this->assertSame(
                EmailVerificationRateLimitException::REASON_IP_LIMIT,
                $exception->reason,
            );
        }

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $first['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_rejected_attempts_lock_the_account_at_the_request_level_maximum(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        $this->travel(61)->seconds();
        $second = $this->issueActivation($user);

        for ($attempt = 1; $attempt <= EmailVerificationService::MAX_REQUEST_FAILED_ATTEMPTS; $attempt++) {
            $this->service->recordRejectedAttempt(
                $first['token'],
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            );
        }

        $counter = EmailVerificationAttemptCounter::query()->sole();

        $this->assertSame(EmailVerificationService::MAX_REQUEST_FAILED_ATTEMPTS, $counter->failed_attempts);
        $this->assertNotNull($counter->locked_until);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'email_verification.locked',
            'target_user_id' => $user->getKey(),
        ]);

        $this->travel(61)->seconds();

        try {
            $this->issueActivation($user);

            $this->fail('A locked request must not issue a replacement token.');
        } catch (EmailVerificationRateLimitException $exception) {
            $this->assertSame(
                EmailVerificationRateLimitException::REASON_LOCKED,
                $exception->reason,
            );
        }

        // The outstanding token and the account stay intact.
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $second['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_the_lockout_expires_after_thirty_minutes(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        $this->travel(61)->seconds();
        $this->issueActivation($user);

        for ($attempt = 1; $attempt <= EmailVerificationService::MAX_REQUEST_FAILED_ATTEMPTS; $attempt++) {
            $this->service->recordRejectedAttempt(
                $first['token'],
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            );
        }

        $this->travel(EmailVerificationService::REQUEST_LOCKOUT_MINUTES + 1)->minutes();

        $issued = $this->issueActivation($user);

        $this->assertSame(EmailVerificationRequest::STATE_ISSUED, $issued['request']->state);

        $counter = EmailVerificationAttemptCounter::query()->sole();

        $this->assertSame(0, $counter->failed_attempts);
        $this->assertNull($counter->locked_until);
    }

    public function test_a_successful_verification_resets_the_request_level_counter(): void
    {
        $user = $this->user();

        $first = $this->issueActivation($user);

        $this->travel(61)->seconds();
        $second = $this->issueActivation($user);

        $this->service->recordRejectedAttempt(
            $first['token'],
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
        );

        $this->assertDatabaseCount('email_verification_attempt_counters', 1);

        $this->service->consume(
            $second['token'],
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
        );

        $this->assertDatabaseCount('email_verification_attempt_counters', 0);
    }

    public function test_the_token_level_counter_retires_the_token_after_five_attempts(): void
    {
        $user = $this->user();

        $issued = $this->issueActivation($user);

        // Drive the request into a terminal state without consuming it by
        // replacing it, then reject against the retired token.
        $this->travel(61)->seconds();
        $this->issueActivation($user);

        for ($attempt = 1; $attempt <= EmailVerificationService::MAX_TOKEN_FAILED_ATTEMPTS; $attempt++) {
            $this->service->recordRejectedAttempt(
                $issued['token'],
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            );
        }

        $retired = EmailVerificationRequest::query()->findOrFail($issued['request']->id);

        $this->assertSame(EmailVerificationService::MAX_TOKEN_FAILED_ATTEMPTS, $retired->failed_attempts);
        $this->assertSame(EmailVerificationRequest::STATE_INVALIDATED, $retired->state);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'email_verification.invalidated',
            'target_user_id' => $user->getKey(),
        ]);
    }

    public function test_an_unknown_token_is_not_counted_against_any_account(): void
    {
        $this->service->recordRejectedAttempt(
            'not-a-real-token',
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
        );

        $this->assertDatabaseCount('email_verification_attempt_counters', 0);
        $this->assertDatabaseCount('email_verification_requests', 0);
    }

    public function test_a_rate_limited_email_change_keeps_the_pending_reservation_and_token(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Current#Password1']);

        UserEmailAddress::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'pending@example.com',
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            'pending@example.com',
            now()->subMinutes(10),
        );

        // Fill the account window so the next replacement is refused.
        for ($row = 0; $row < EmailVerificationService::ACCOUNT_HOURLY_LIMIT - 1; $row++) {
            EmailVerificationRequest::factory()->create([
                'user_id' => $user->getKey(),
                'purpose' => EmailVerificationService::PURPOSE_EMAIL_CHANGE,
                'email' => 'pending@example.com',
                'state' => EmailVerificationRequest::STATE_INVALIDATED,
                'issued_at' => now()->subMinutes(5),
            ]);
        }

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.update'), [
                'email' => 'another@example.com',
                'current_password' => 'Current#Password1',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => 'pending@example.com',
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }
}
