<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Complete a self-service email change by consuming the emailed
 * single-use token (§9.4).
 *
 * The token consumption, the registry transition (the pending address
 * becomes current and the old one becomes a reserved previous address), and
 * the account email update commit atomically. A token whose pending request
 * was replaced or cancelled in the meantime cannot change the address.
 */
class CompleteEmailChangeAction
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws EmailVerificationException For every unusable token
     */
    public function execute(string $rawToken): User
    {
        try {
            return DB::transaction(function () use ($rawToken): User {
                $request = $this->verificationService->consume(
                    $rawToken,
                    EmailVerificationService::PURPOSE_EMAIL_CHANGE,
                );

                $user = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user_id);

                $pending = UserEmailAddress::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', UserEmailAddress::STATUS_PENDING)
                    ->where('email', $request->email)
                    ->lockForUpdate()
                    ->first();

                if ($pending === null) {
                    // The pending request was replaced or cancelled after the
                    // token was issued; the link must not move the address.
                    throw EmailVerificationException::invalidated();
                }

                $previousEmail = (string) $user->email;

                UserEmailAddress::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', UserEmailAddress::STATUS_CURRENT)
                    ->get()
                    ->each(function (UserEmailAddress $address): void {
                        $address->forceFill([
                            'status' => UserEmailAddress::STATUS_PREVIOUS,
                        ])->save();
                    });

                $pending->forceFill([
                    'status' => UserEmailAddress::STATUS_CURRENT,
                    'verified_at' => Carbon::now(),
                ])->save();

                $user->forceFill([
                    'email' => $request->email,
                    'email_verified_at' => Carbon::now(),
                ])->save();

                $this->auditLogger->log(
                    $user,
                    $user,
                    'user.email.changed',
                    ['email' => $previousEmail],
                    ['email' => $request->email],
                );

                return $user;
            });
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
