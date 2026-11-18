<?php

namespace App\Services\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Forced password-reset lifecycle (§11.3).
 *
 * Approved configuration (user-confirmed): the reset link expires after
 * sixty minutes; at most one reset link may be issued per account every
 * sixty seconds and five per rolling hour; five failed completion attempts
 * per token disable it. The per-IP cap used by self-service request flows
 * is intentionally omitted: forced resets are issued by an authenticated
 * administrator, so there is no guest requester to rate limit by IP.
 *
 * The service participates in the caller's transaction so consumption and
 * the resulting account update commit atomically; it never opens a
 * transaction of its own. Only the token hash is stored or audited; the raw
 * token exists solely for the email link.
 */
class PasswordResetService
{
    /**
     * Reset link validity window, in minutes.
     */
    public const EXPIRY_MINUTES = 60;

    /**
     * Minimum spacing between two issued links for the same account.
     */
    public const COOLDOWN_SECONDS = 60;

    /**
     * Maximum links issued within the rolling hour, per account.
     */
    public const MAX_ISSUED_PER_HOUR = 5;

    /**
     * Failed completion attempts allowed per token before it is disabled.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly InvitationCredentialGenerator $generator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Issue a fresh reset request, invalidating the outstanding one for the
     * same user.
     *
     * @return array{request: PasswordResetRequest, token: string}
     *
     * @throws PasswordResetException When the cooldown or hourly limit is
     *                                exhausted.
     */
    public function issue(
        User $user,
        ?CarbonInterface $now = null,
    ): array {
        $now ??= Carbon::now();

        $lastIssued = PasswordResetRequest::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($lastIssued !== null
            && $lastIssued->created_at->greaterThan($now->copy()->subSeconds(self::COOLDOWN_SECONDS))
        ) {
            throw PasswordResetException::tooSoon();
        }

        $issuedInWindow = PasswordResetRequest::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();

        if ($issuedInWindow >= self::MAX_ISSUED_PER_HOUR) {
            throw PasswordResetException::tooManyIssued();
        }

        // The partial unique index allows a single issued request for this
        // user; outstanding rows are locked so a concurrent replacement
        // cannot race past the invalidation update.
        $outstanding = PasswordResetRequest::query()
            ->where('user_id', $user->getKey())
            ->where('state', PasswordResetRequest::STATE_ISSUED)
            ->lockForUpdate()
            ->get();

        foreach ($outstanding as $outstandingRequest) {
            $outstandingRequest->forceFill([
                'state' => PasswordResetRequest::STATE_INVALIDATED,
                'invalidated_at' => $now,
            ])->save();

            $this->auditLogger->log(null, $user, 'password_reset.invalidated', null, [
                'reason' => 'superseded',
            ]);
        }

        $token = $this->generator->generateToken();

        $request = PasswordResetRequest::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => $this->hasher->hash($token),
            'state' => PasswordResetRequest::STATE_ISSUED,
            'failed_attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        $this->auditLogger->log(null, $user, 'password_reset.issued', null, [
            'expires_at' => $request->expires_at->toIso8601String(),
        ]);

        return ['request' => $request, 'token' => $token];
    }

    /**
     * Consume one outstanding request by its raw token for a successful
     * completion. The consuming caller performs the password update inside
     * the same transaction, so the lifecycle transition and the update
     * commit or roll back together.
     *
     * @throws PasswordResetException With a typed reason for every unusable
     *                                token.
     */
    public function consume(string $rawToken): PasswordResetRequest
    {
        $request = PasswordResetRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->lockForUpdate()
            ->first();

        if ($request === null) {
            throw PasswordResetException::notFound();
        }

        $now = Carbon::now();

        if ($request->state === PasswordResetRequest::STATE_ISSUED
            && $request->expires_at->lessThan($now)
        ) {
            $request->forceFill([
                'state' => PasswordResetRequest::STATE_EXPIRED,
                'expired_at' => $now,
            ])->save();

            $this->auditLogger->log(null, $request->user, 'password_reset.expired', null, []);

            throw PasswordResetException::expired();
        }

        if ($request->state === PasswordResetRequest::STATE_CONSUMED) {
            throw PasswordResetException::consumed();
        }

        if ($request->state === PasswordResetRequest::STATE_INVALIDATED) {
            throw PasswordResetException::invalidated();
        }

        if ($request->state === PasswordResetRequest::STATE_EXPIRED) {
            throw PasswordResetException::expired();
        }

        $request->forceFill([
            'state' => PasswordResetRequest::STATE_CONSUMED,
            'consumed_at' => $now,
        ])->save();

        $this->auditLogger->log(null, $request->user, 'password_reset.consumed', null, []);

        return $request;
    }

    /**
     * Record one failed completion attempt against the token and disable it
     * once the attempt limit is exhausted. Runs outside any transaction; a
     * missing or already-terminal token is a no-op.
     */
    public function recordFailedAttempt(string $rawToken): void
    {
        $request = PasswordResetRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->first();

        if ($request === null || $request->state !== PasswordResetRequest::STATE_ISSUED) {
            return;
        }

        $attempts = $request->failed_attempts + 1;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $request->forceFill([
                'state' => PasswordResetRequest::STATE_INVALIDATED,
                'invalidated_at' => Carbon::now(),
                'failed_attempts' => $attempts,
            ])->save();

            $this->auditLogger->log(null, $request->user, 'password_reset.invalidated', null, [
                'reason' => 'failed_attempts_exceeded',
            ]);

            return;
        }

        $request->forceFill(['failed_attempts' => $attempts])->save();
    }

    /**
     * Reclassify an issued, past-expiry token as expired after a consume()
     * attempt raised REASON-expired inside a caller transaction that has
     * since rolled back. Idempotent.
     */
    public function markExpiredByToken(string $rawToken): void
    {
        $request = PasswordResetRequest::query()
            ->where('token_hash', $this->hasher->hash($rawToken))
            ->first();

        if ($request === null
            || $request->state !== PasswordResetRequest::STATE_ISSUED
            || ! $request->expires_at->isPast()
        ) {
            return;
        }

        $now = Carbon::now();

        $request->forceFill([
            'state' => PasswordResetRequest::STATE_EXPIRED,
            'expired_at' => $now,
        ])->save();

        $this->auditLogger->log(null, $request->user, 'password_reset.expired', null, []);
    }
}
