<?php

namespace App\Actions;

use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Support\Facades\DB;

/**
 * Complete an account activation by consuming an account_activation
 * verification token.
 *
 * Consumption and the account updates commit atomically: the user's
 * email_verified_at is set, the reserved ledger row becomes the current
 * address, and the invited account flips to active in the same transaction
 * as the verification lifecycle transition. Accounts only reach protected
 * access after successful verification.
 */
class CompleteAccountActivationAction
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{user: User, verificationRequest: EmailVerificationRequest}
     *
     * @throws EmailVerificationException With a typed
     *                                    reason for
     *                                    every unusable
     *                                    verification
     *                                    token.
     */
    public function execute(string $rawToken): array
    {
        try {
            /** @var array{user: User, verificationRequest: EmailVerificationRequest} $activation */
            $activation = DB::transaction(function () use ($rawToken): array {
                $verificationRequest = $this->verificationService->consume(
                    $rawToken,
                    EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION,
                );

                $now = now();

                $invitedUser = User::query()
                    ->whereKey($verificationRequest->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $ledgerRow = UserEmailAddress::query()
                    ->where('user_id', $invitedUser->getKey())
                    ->where('email', $verificationRequest->email)
                    ->lockForUpdate()
                    ->first();

                if ($ledgerRow === null) {
                    $ledgerRow = new UserEmailAddress([
                        'user_id' => $invitedUser->getKey(),
                        'email' => $verificationRequest->email,
                    ]);
                }

                $ledgerRow->forceFill([
                    'status' => UserEmailAddress::STATUS_CURRENT,
                    'verified_at' => $now,
                ])->save();

                $invitedUser->forceFill([
                    'email_verified_at' => $now,
                    'account_status' => 'active',
                ])->save();

                $this->auditLogger->log(
                    $invitedUser,
                    $invitedUser,
                    'user.activated',
                    null,
                    [
                        'email' => $verificationRequest->email,
                        'account_status' => 'active',
                    ],
                );

                return [
                    'user' => $invitedUser->refresh(),
                    'verificationRequest' => $verificationRequest,
                ];
            });

            return $activation;
        } catch (EmailVerificationException $exception) {
            // The transaction rolled back, which would also have undone the
            // expiry reclassification consume() performed; persist it again
            // outside the transaction so the state change survives.
            if ($exception->reason === EmailVerificationException::REASON_EXPIRED) {
                $this->verificationService->markExpiredByToken($rawToken);
            }

            throw $exception;
        }
    }
}
