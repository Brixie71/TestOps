<?php

namespace App\Actions;

use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\EmailVerificationRateLimitException;
use App\Services\Auth\EmailVerificationService;
use App\Services\Notifications\InvitationDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Start a self-service email change (§9.4, §9.5).
 *
 * The account holder confirms their current password and supplies the new
 * address; the address is normalized, checked against every reserved address,
 * reserved as this account's pending address, and verified through a
 * single-use emailed token. The current verified address stays active until
 * the new one is verified. A second request replaces the pending one: the
 * previous pending address is released and its outstanding token invalidated
 * before the new token is issued.
 */
class RequestEmailChangeAction
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly InvitationDeliveryService $deliveryService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException When the confirmation password is wrong,
     *                             the address is unchanged, it is already
     *                             reserved by another account, or issuance is
     *                             refused by the verification limits
     */
    public function execute(
        User $user,
        string $currentPassword,
        string $newEmail,
        ?string $ipAddress = null,
    ): void {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $email = Str::lower(trim($newEmail));

        /** @var array{request: EmailVerificationRequest, token: string} $issued */
        try {
            $issued = DB::transaction(function () use ($user, $email, $ipAddress): array {
                $user = User::query()->lockForUpdate()->findOrFail($user->getKey());

                if (Str::lower((string) $user->email) === $email) {
                    throw ValidationException::withMessages([
                        'email' => 'Enter an email address that differs from your current one.',
                    ]);
                }

                $this->assertAvailable($user, $email);

                UserEmailAddress::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', UserEmailAddress::STATUS_PENDING)
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (UserEmailAddress $address) => $address->delete());

                $this->verificationService->invalidateOutstanding(
                    $user,
                    EmailVerificationService::PURPOSE_EMAIL_CHANGE,
                );

                UserEmailAddress::query()->create([
                    'user_id' => $user->getKey(),
                    'email' => $email,
                    'status' => UserEmailAddress::STATUS_PENDING,
                ]);

                $issued = $this->verificationService->issue(
                    $user,
                    EmailVerificationService::PURPOSE_EMAIL_CHANGE,
                    $email,
                    null,
                    $ipAddress,
                );

                $this->auditLogger->log($user, $user, 'user.email.change_requested', null, [
                    'pending_email' => $email,
                ]);

                return $issued;
            });
        } catch (EmailVerificationRateLimitException $exception) {
            // The transaction rolled back, so the outstanding token and the
            // pending reservation are untouched (§10.7).
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }

        // The verification email sends after the commit; a transport failure
        // is recorded as a failed delivery and never rolls back the request.
        $this->deliveryService->sendEmailChangeVerification($issued['request'], $issued['token']);
    }

    /**
     * Reject addresses held by another account or reserved in the registry.
     * The account's own addresses are excluded, so it may reclaim one.
     *
     * @throws ValidationException When the address is already reserved
     */
    private function assertAvailable(User $user, string $email): void
    {
        $takenByAccount = User::query()
            ->withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($user->getKey())
            ->exists();

        $takenByRegistry = UserEmailAddress::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('user_id', '!=', $user->getKey())
            ->exists();

        if ($takenByAccount || $takenByRegistry) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already reserved by another account.',
            ]);
        }
    }
}
