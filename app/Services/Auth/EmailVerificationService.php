<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationAttemptCounter;
use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\AccountLinkExpirationSettings;
use App\Services\Settings\EmailVerificationLimitSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Purpose-aware email verification lifecycle.
 *
 * Requests move through explicit states: issued, consumed, expired, or
 * invalidated. The service participates in the caller's transaction so a
 * consumption (and the account or email-registry update it enables) commits
 * atomically as one unit; it never opens a transaction of its own.
 *
 * Issuing a replacement invalidates every outstanding issued request for
 * the same user and purpose, and expired issued requests are reclassified
 * as expired when they are touched again, so they never remain indefinitely
 * classified as issued. Only the token hash is stored or audited; the raw
 * token exists solely for the email link.
 */
class EmailVerificationService
{
    /**
     * Activation of a newly invited account after password setup.
     */
    public const PURPOSE_ACCOUNT_ACTIVATION = 'account_activation';

    /**
     * Self-service email change confirmation (§9.4); the token authorizes
     * moving the account to a new address.
     */
    public const PURPOSE_EMAIL_CHANGE = 'email_change';

    public const STATE_ISSUED = 'issued';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_EXPIRED = 'expired';

    public const STATE_INVALIDATED = 'invalidated';

    /**
     * Failed attempts allowed per token before it is invalidated (§10.4).
     */
    public const MAX_TOKEN_FAILED_ATTEMPTS = 5;

    /**
     * Failed attempts allowed per request across replacement tokens (§10.5).
     */
    public const MAX_REQUEST_FAILED_ATTEMPTS = 10;

    /**
     * Lockout duration once the request-level maximum is reached (§10.5).
     */
    public const REQUEST_LOCKOUT_MINUTES = 30;

    /**
     * Cooldown between replacements for the same user and purpose (§10.3).
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Verification emails allowed per account per rolling hour (§10.6).
     */
    public const ACCOUNT_HOURLY_LIMIT = 5;

    /**
     * The supported purposes; unknown values are rejected outright.
     */
    private const SUPPORTED_PURPOSES = [
        self::PURPOSE_ACCOUNT_ACTIVATION,
        self::PURPOSE_EMAIL_CHANGE,
    ];

    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly InvitationCredentialGenerator $generator,
        private readonly AccountLinkExpirationSettings $settings,
        private readonly EmailVerificationLimitSettings $limitSettings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Issue a fresh verification request, invalidating the outstanding one
     * for the same user and purpose.
     *
     * @return array{request: EmailVerificationRequest, token: string}
     *
     * @throws EmailVerificationRateLimitException When the resend cooldown,
     *                                             the rolling-hour account
     *                                             or IP limit, or the
     *                                             request lockout refuses a
     *                                             replacement
     * @throws EmailVerificationException Never; purpose validation throws
     *                                    InvalidArgumentException instead.
     */
    public function issue(
        User $user,
        string $purpose,
        string $email,
        ?CarbonInterface $now = null,
        ?string $ipAddress = null,
    ): array {
        $this->assertSupportedPurpose($purpose);

        $now ??= Carbon::now();

        // Limits gate replacements only: the very first issuance for a user
        // and purpose is always allowed, and rate-limited requests leave any
        // outstanding token and pending email reservation untouched (§10.7).
        if ($this->hasPriorIssuance($user, $purpose)) {
            $this->assertReplacementAllowed($user, $purpose, $ipAddress, $now);
        }

        // The partial unique index allows a single issued request for this
        // user and purpose; those rows are locked so a concurrent
        // replacement cannot race past the invalidation update.
        $outstanding = EmailVerificationRequest::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->where('state', self::STATE_ISSUED)
            ->lockForUpdate()
            ->get();

        foreach ($outstanding as $outstandingRequest) {
            // An outstanding request past its expiry is reclassified as
            // expired rather than invalidated when it is superseded; both
            // states are recorded distinctly for audit purposes.
            if ($outstandingRequest->expires_at->lessThan($now)) {
                $outstandingRequest->forceFill([
                    'state' => self::STATE_EXPIRED,
                    'expired_at' => $now,
                ])->save();

                $this->auditLogger->log($user, $user, 'email_verification.expired', null, [
                    'purpose' => $purpose,
                ]);
            } else {
                $outstandingRequest->forceFill([
                    'state' => self::STATE_INVALIDATED,
                    'invalidated_at' => $now,
                ])->save();

                $this->auditLogger->log($user, $user, 'email_verification.invalidated', null, [
                    'purpose' => $purpose,
                ]);
            }
        }

        $token = $this->generator->generateToken();

        $request = EmailVerificationRequest::query()->create([
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'email' => $email,
            'token_hash' => $this->hasher->hash($token),
            'state' => self::STATE_ISSUED,
            'expires_at' => $now->copy()->addMinutes(
                $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION),
            ),
            'issued_at' => $now,
            'requested_ip' => $ipAddress,
            'failed_attempts' => 0,
        ]);

        $this->auditLogger->log($user, $user, 'email_verification.issued', null, [
            'purpose' => $purpose,
            'expires_at' => $request->expires_at->toIso8601String(),
        ]);

        return ['request' => $request, 'token' => $token];
    }

    /**
     * Consume one outstanding request by its raw token, expecting the given
     * purpose.
     *
     * The consuming caller is responsible for the account or email-registry
     * update; both this transition and the resulting update happen inside
     * the caller's transaction, so they commit or roll back together.
     *
     * @throws EmailVerificationException With a reason identifying the
     *                                    failure; no credentials are in
     *                                    any message.
     */
    public function consume(string $rawToken, string $purpose): EmailVerificationRequest
    {
        $this->assertSupportedPurpose($purpose);

        $request = EmailVerificationRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->lockForUpdate()
            ->first();

        if ($request === null) {
            throw EmailVerificationException::notFound();
        }

        if ($request->purpose !== $purpose) {
            throw EmailVerificationException::purposeMismatch();
        }

        $now = Carbon::now();

        if ($request->state === self::STATE_ISSUED
            && $request->expires_at->lessThan($now)
        ) {
            $request->forceFill([
                'state' => self::STATE_EXPIRED,
                'expired_at' => $now,
            ])->save();

            $this->auditLogger->log(null, $request->user, 'email_verification.expired', null, [
                'purpose' => $purpose,
            ]);

            throw EmailVerificationException::expired();
        }

        if ($request->state === self::STATE_CONSUMED) {
            throw EmailVerificationException::alreadyConsumed();
        }

        if ($request->state === self::STATE_INVALIDATED) {
            throw EmailVerificationException::invalidated();
        }

        if ($request->state === self::STATE_EXPIRED) {
            throw EmailVerificationException::expired();
        }

        $request->forceFill([
            'state' => self::STATE_CONSUMED,
            'consumed_at' => $now,
        ])->save();

        $this->auditLogger->log(null, $request->user, 'email_verification.consumed', null, [
            'purpose' => $purpose,
        ]);

        // A successful verification resets the request-level counter (§10.5).
        EmailVerificationAttemptCounter::query()
            ->where('user_id', $request->user_id)
            ->where('purpose', $purpose)
            ->delete();

        return $request;
    }

    /**
     * Invalidate every outstanding request for one user and purpose without
     * issuing a replacement (§9.5, §9.6). Requests already past their expiry
     * are reclassified as expired instead, exactly as they are when a
     * replacement supersedes them.
     */
    public function invalidateOutstanding(
        User $user,
        string $purpose,
        ?CarbonInterface $now = null,
    ): int {
        $this->assertSupportedPurpose($purpose);

        $now ??= Carbon::now();

        $outstanding = EmailVerificationRequest::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->where('state', self::STATE_ISSUED)
            ->lockForUpdate()
            ->get();

        foreach ($outstanding as $outstandingRequest) {
            $expired = $outstandingRequest->expires_at->lessThan($now);

            $outstandingRequest->forceFill([
                'state' => $expired ? self::STATE_EXPIRED : self::STATE_INVALIDATED,
                ($expired ? 'expired_at' : 'invalidated_at') => $now,
            ])->save();

            $this->auditLogger->log($user, $user, $expired
                ? 'email_verification.expired'
                : 'email_verification.invalidated', null, [
                    'purpose' => $purpose,
                ]);
        }

        return $outstanding->count();
    }

    /**
     * Record a rejected confirmation attempt against the token's request
     * (§10.4, §10.5).
     *
     * Called after the caller's transaction has rolled back, so the counters
     * survive a failed consumption attempt. Unknown tokens and tokens issued
     * for another purpose cannot be attributed to an account and are ignored.
     * Counters never release the pending email reservation.
     */
    public function recordRejectedAttempt(
        string $rawToken,
        string $purpose,
        ?CarbonInterface $now = null,
    ): void {
        $this->assertSupportedPurpose($purpose);

        $request = EmailVerificationRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->first();

        if ($request === null || $request->purpose !== $purpose) {
            return;
        }

        $now ??= Carbon::now();

        DB::transaction(function () use ($request, $purpose, $now): void {
            $request->forceFill([
                'failed_attempts' => (int) $request->failed_attempts + 1,
            ])->save();

            // §10.4: five failures retire the token. Terminal states are
            // already unusable, so only an outstanding token is invalidated.
            if ($request->failed_attempts >= self::MAX_TOKEN_FAILED_ATTEMPTS
                && $request->state === self::STATE_ISSUED
            ) {
                $request->forceFill([
                    'state' => self::STATE_INVALIDATED,
                    'invalidated_at' => $now,
                ])->save();

                $this->auditLogger->log(null, $request->user, 'email_verification.invalidated', null, [
                    'purpose' => $purpose,
                    'reason' => 'failed_attempts_exceeded',
                ]);
            }

            $counter = EmailVerificationAttemptCounter::query()->firstOrCreate(
                ['user_id' => $request->user_id, 'purpose' => $purpose],
                ['failed_attempts' => 0],
            );

            $counter->forceFill([
                'failed_attempts' => (int) $counter->failed_attempts + 1,
            ]);

            // §10.5: reaching the request-level maximum arms the lockout.
            if ($counter->failed_attempts >= self::MAX_REQUEST_FAILED_ATTEMPTS
                && ! $counter->isLocked($now)
            ) {
                $counter->forceFill([
                    'locked_until' => $now->copy()->addMinutes(self::REQUEST_LOCKOUT_MINUTES),
                ]);

                $this->auditLogger->log(null, $request->user, 'email_verification.locked', null, [
                    'purpose' => $purpose,
                    'locked_until' => $counter->locked_until->toIso8601String(),
                ]);
            }

            $counter->save();
        });
    }

    /**
     * Persist the expiry reclassification for a token whose consume()
     * attempt raised REASON_EXPIRED inside a caller transaction that has
     * since rolled back. Idempotent: only an issued, past-expiry request is
     * transitioned, and the audit event is written once.
     */
    public function markExpiredByToken(string $rawToken): void
    {
        $request = EmailVerificationRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->first();

        if ($request === null
            || $request->state !== self::STATE_ISSUED
            || ! $request->expires_at->isPast()
        ) {
            return;
        }

        $now = Carbon::now();

        $request->forceFill([
            'state' => self::STATE_EXPIRED,
            'expired_at' => $now,
        ])->save();

        $this->auditLogger->log(null, $request->user, 'email_verification.expired', null, [
            'purpose' => $request->purpose,
        ]);
    }

    /**
     * Whether this user already had a request issued for this purpose, which
     * makes the next issuance a replacement subject to the §10 limits.
     */
    private function hasPriorIssuance(User $user, string $purpose): bool
    {
        return EmailVerificationRequest::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->exists();
    }

    /**
     * Enforce the request lockout, the resend cooldown, and both rolling-hour
     * issuance limits before any mutation happens (§10.3-§10.8).
     *
     * @throws EmailVerificationRateLimitException when issuance is refused
     */
    private function assertReplacementAllowed(
        User $user,
        string $purpose,
        ?string $ipAddress,
        CarbonInterface $now,
    ): void {
        $counter = EmailVerificationAttemptCounter::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->first();

        if ($counter !== null && $counter->isLocked($now)) {
            throw EmailVerificationRateLimitException::locked();
        }

        if ($counter !== null
            && $counter->failed_attempts >= self::MAX_REQUEST_FAILED_ATTEMPTS
        ) {
            // An elapsed lockout starts a clean cycle. The counter is never
            // reset by token replacement, only by this expiry or a success.
            $counter->forceFill([
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        $nowTimestamp = Carbon::instance($now)->getTimestamp();

        $lastIssuedAt = EmailVerificationRequest::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->max('issued_at');

        if ($lastIssuedAt !== null) {
            $cooldownEnds = Carbon::parse($lastIssuedAt)->getTimestamp()
                + self::RESEND_COOLDOWN_SECONDS;

            if ($cooldownEnds > $nowTimestamp) {
                throw EmailVerificationRateLimitException::resendCooldown(
                    $cooldownEnds - $nowTimestamp,
                );
            }
        }

        $windowStart = Carbon::instance($now)->subHour();

        $issuedInWindow = EmailVerificationRequest::query()
            ->where('user_id', $user->getKey())
            ->where('issued_at', '>=', $windowStart)
            ->count();

        if ($issuedInWindow >= self::ACCOUNT_HOURLY_LIMIT) {
            throw EmailVerificationRateLimitException::accountLimit();
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $issuedFromIp = EmailVerificationRequest::query()
                ->where('requested_ip', $ipAddress)
                ->where('issued_at', '>=', $windowStart)
                ->count();

            if ($issuedFromIp >= $this->limitSettings->getIpHourlyLimit()) {
                throw EmailVerificationRateLimitException::ipLimit();
            }
        }
    }

    private function assertSupportedPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::SUPPORTED_PURPOSES, true)) {
            throw new InvalidArgumentException(
                "Unsupported email verification purpose [{$purpose}].",
            );
        }
    }
}
