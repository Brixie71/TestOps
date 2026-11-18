<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\AccountLinkExpirationSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
     * Reserved for the future email-change workflow; never wired into the
     * account activation workflow.
     */
    public const PURPOSE_EMAIL_CHANGE = 'email_change';

    public const STATE_ISSUED = 'issued';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_EXPIRED = 'expired';

    public const STATE_INVALIDATED = 'invalidated';

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
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Issue a fresh verification request, invalidating the outstanding one
     * for the same user and purpose.
     *
     * @return array{request: EmailVerificationRequest, token: string}
     *
     * @throws EmailVerificationException Never; purpose validation throws
     *                                    InvalidArgumentException instead.
     */
    public function issue(
        User $user,
        string $purpose,
        string $email,
        ?CarbonInterface $now = null,
    ): array {
        $this->assertSupportedPurpose($purpose);

        $now ??= Carbon::now();

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

        return $request;
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

    private function assertSupportedPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::SUPPORTED_PURPOSES, true)) {
            throw new InvalidArgumentException(
                "Unsupported email verification purpose [{$purpose}].",
            );
        }
    }
}
