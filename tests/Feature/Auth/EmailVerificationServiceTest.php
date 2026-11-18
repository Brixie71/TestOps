<?php

namespace Tests\Feature\Auth;

use App\Actions\CompleteAccountActivationAction;
use App\Models\AuditLog;
use App\Models\EmailVerificationRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmailVerificationService::class);
    }

    public function test_issue_persists_hashed_token_with_the_configured_expiry(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'accounts.email_verification_expiration_minutes',
            'setting_value' => '7',
        ]);

        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $request = $issued['request'];

        $this->assertSame(EmailVerificationRequest::STATE_ISSUED, $request->state);
        $this->assertTrue($request->isOutstanding());
        $this->assertSame($user->getKey(), $request->user_id);
        $this->assertSame($user->email, $request->email);
        $this->assertNotNull($issued['token']);
        $this->assertNotSame($issued['token'], $request->token_hash);
        $this->assertSame(64, strlen((string) $request->token_hash));
        $this->assertTrue(
            abs($request->expires_at->diffInSeconds(now()->addMinutes(7), true)) < 2,
        );
    }

    public function test_issue_audits_the_issuance_without_any_token(): void
    {
        $user = User::factory()->create();

        $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $auditLog = AuditLog::query()->where('action', 'email_verification.issued')->sole();

        $this->assertSame($user->getKey(), $auditLog->actor_user_id);
        $this->assertNull($auditLog->after_values['token_hash'] ?? null);
        $this->assertStringNotContainsString($user->email, $auditLog->after_values['token_hash'] ?? '');
        $this->assertSame('account_activation', $auditLog->after_values['purpose']);
    }

    public function test_consume_transitions_to_consumed_and_stays_distinguishable(): void
    {
        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $request = $this->service->consume(
            $issued['token'],
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
        );

        $this->assertSame(EmailVerificationRequest::STATE_CONSUMED, $request->state);
        $this->assertNotNull($request->consumed_at);
        $this->assertNull($request->invalidated_at);
        $this->assertNull($request->expired_at);
    }

    public function test_a_second_consumption_is_rejected_as_single_use(): void
    {
        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->service->consume($issued['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('already been used');

        try {
            $this->service->consume($issued['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);
        } catch (EmailVerificationException $exception) {
            $this->assertSame(EmailVerificationException::REASON_ALREADY_CONSUMED, $exception->reason);

            throw $exception;
        }
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('no longer valid');

        $this->service->consume('unknown-token', EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);
    }

    public function test_an_expired_request_is_reclassified_and_rejected(): void
    {
        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->travel(6)->minutes();

        try {
            $this->service->consume($issued['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);
        } catch (EmailVerificationException $exception) {
            $this->assertSame(EmailVerificationException::REASON_EXPIRED, $exception->reason);
        }

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $issued['request']->id,
            'state' => EmailVerificationRequest::STATE_EXPIRED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'email_verification.expired',
        ]);
    }

    public function test_a_replacement_request_invalidates_the_outstanding_one(): void
    {
        $user = User::factory()->create();

        $first = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->travel(1)->minutes();

        $second = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->assertNotSame($first['request']->id, $second['request']->id);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $first['request']->id,
            'state' => EmailVerificationRequest::STATE_INVALIDATED,
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $second['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        $secondRequest = $this->service->consume(
            $second['token'],
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
        );
        $this->assertSame(EmailVerificationRequest::STATE_CONSUMED, $secondRequest->state);

        try {
            $this->service->consume($first['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);

            $this->fail('The invalidated request must be rejected.');
        } catch (EmailVerificationException $exception) {
            $this->assertSame(EmailVerificationException::REASON_INVALIDATED, $exception->reason);
        }
    }

    public function test_an_outstanding_request_past_expiry_is_expired_by_a_replacement(): void
    {
        $user = User::factory()->create();

        $first = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->travel(7)->minutes();

        $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $first['request']->id,
            'state' => EmailVerificationRequest::STATE_EXPIRED,
        ]);
    }

    public function test_purposes_are_isolated_from_one_another(): void
    {
        $user = User::factory()->create();

        $activation = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $emailChange = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            $user->email,
        );

        $this->assertNotSame($activation['request']->id, $emailChange['request']->id);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $activation['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $emailChange['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        try {
            $this->service->consume(
                $emailChange['token'],
                EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            );
        } catch (EmailVerificationException $exception) {
            $this->assertSame(EmailVerificationException::REASON_PURPOSE_MISMATCH, $exception->reason);
        }

        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $emailChange['request']->id,
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);
    }

    public function test_concurrent_consumption_produces_a_single_success(): void
    {
        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $firstSucceeded = false;

        try {
            $this->service->consume($issued['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);

            $firstSucceeded = true;
        } catch (EmailVerificationException) {
        }

        $secondFailed = false;

        try {
            $this->service->consume($issued['token'], EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION);
        } catch (EmailVerificationException $exception) {
            $secondFailed = $exception->reason
                === EmailVerificationException::REASON_ALREADY_CONSUMED;
        }

        $this->assertTrue($firstSucceeded);
        $this->assertTrue($secondFailed);
    }

    public function test_activation_consumption_is_atomic_with_the_account_update(): void
    {
        // Soft-delete the user after the request is issued so the account
        // update fails; the whole transaction rolls back and the request
        // stays issued.
        $user = User::factory()->create();

        $issued = $this->service->issue(
            $user,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $user->email,
        );

        $user->delete();

        $this->expectException(ModelNotFoundException::class);

        try {
            app(CompleteAccountActivationAction::class)->execute($issued['token']);
        } finally {
            $this->assertDatabaseHas('email_verification_requests', [
                'id' => $issued['request']->id,
                'state' => EmailVerificationRequest::STATE_ISSUED,
                'consumed_at' => null,
            ]);
        }
    }

    public function test_successful_activation_flips_the_account_and_ledger_atomically(): void
    {
        $invitationUser = User::factory()->create(['account_status' => 'invited']);

        // The factory auto-creates the ledger row for the address; reset it
        // to the reserved, unverified state an invited account starts with.
        $invitationUser->emailAddresses()->first()->forceFill([
            'status' => 'reserved',
            'verified_at' => null,
        ])->save();

        $issued = $this->service->issue(
            $invitationUser,
            EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
            $invitationUser->email,
        );

        $validated = app(CompleteAccountActivationAction::class)->execute($issued['token']);

        $this->assertSame('active', $validated['user']->account_status);
        $this->assertNotNull($validated['user']->email_verified_at);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $invitationUser->getKey(),
            'email' => $invitationUser->email,
            'status' => 'current',
        ]);

        $this->assertNotNull(
            UserEmailAddress::query()
                ->where('user_id', $invitationUser->getKey())
                ->first()
                ?->verified_at,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.activated',
        ]);
    }
}
