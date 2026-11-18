<?php

namespace App\Actions;

use App\Models\InvitationLinkRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Models\UserInvitation;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationCredentialGenerator;
use App\Services\Auth\InvitationLinkRequestIssuer;
use App\Services\Auth\InvitationRateLimiter;
use App\Services\Settings\AccountLinkExpirationSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issue a new account offer: Email Only creation (name optional) with a
 * reserved email ledger row and randomly generated credentials that are
 * never exposed to the inviting administrator.
 *
 * An account whose previous invitation was cancelled may be invited again:
 * the cancelled invitation stays untouched as history and a brand-new
 * invitation lifecycle (fresh credentials, generation 1) is recorded on
 * the same user row. The existing user row is locked inside the
 * transaction so parallel issuances cannot create two live invitations
 * for one account. Active and deactivated accounts can never be invited.
 *
 * The invitation transaction commits first; the invitation email is sent
 * afterwards by the caller through the delivery service, so a transport
 * failure leaves the invitation live for manual retry instead of rolling
 * the account back.
 */
class IssueUserInvitationAction
{
    /**
     * The two supported invitation methods.
     */
    private const SUPPORTED_METHODS = [
        UserInvitation::METHOD_INVITATION_LINK,
        UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
    ];

    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly InvitationCredentialGenerator $generator,
        private readonly InvitationLinkRequestIssuer $issuer,
        private readonly InvitationRateLimiter $rateLimiter,
        private readonly AccountLinkExpirationSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  string  $method  One of the UserInvitation METHOD_* constants.
     * @param  string  $acceptanceFlow  One of the UserInvitation ACCEPTANCE_FLOW_* constants.
     * @return array{invitation: UserInvitation, linkRequest: InvitationLinkRequest, token: string, recoveryCode: string}
     *
     * @throws ValidationException When the email address is already
     *                             registered or the method is unsupported.
     */
    public function execute(
        User $actor,
        string $email,
        string $method = UserInvitation::METHOD_INVITATION_LINK,
        string $acceptanceFlow = UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
        ?string $name = null,
        ?string $ipAddress = null,
    ): array {
        $email = strtolower(trim($email));

        $name = $name === null ? null : trim($name);

        if (! in_array($method, self::SUPPORTED_METHODS, true)) {
            throw ValidationException::withMessages([
                'method' => 'The requested account creation method is not supported.',
            ]);
        }

        $this->rateLimiter->assertNewInvitationAllowed($ipAddress);

        $recoveryCode = $this->generator->generateRecoveryCode();

        /** @var array{invitation: UserInvitation, linkRequest: InvitationLinkRequest, token: string} $issued */
        $issued = DB::transaction(function () use (
            $actor,
            $email,
            $method,
            $acceptanceFlow,
            $name,
            $ipAddress,
            $recoveryCode
        ): array {
            $now = now();

            // Lock on any account holding this email (including
            // soft-deleted) so concurrent issuances serialize per account
            // and eligibility is decided under the lock.
            $existingUser = User::query()
                ->withTrashed()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            $reissue = false;

            if ($existingUser === null) {
                try {
                    $invitedUser = User::query()->create([
                        'name' => $name === '' ? null : $name,
                        'email' => $email,
                        'account_status' => 'invited',
                        'email_verified_at' => null,
                        'password' => Str::random(24),
                        'remember_token' => Str::random(10),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'email' => self::EMAIL_REGISTERED_MESSAGE,
                    ]);
                }

                $invitedUser->emailAddresses()->create([
                    'email' => $email,
                    'status' => UserEmailAddress::STATUS_RESERVED,
                    'verified_at' => null,
                ]);
            } else {
                $this->assertReissueAllowed($existingUser);

                // The invited account is reused untouched; its reserved
                // ledger row already exists.
                $invitedUser = $existingUser;
                $reissue = true;
            }

            $token = $this->generator->generateToken();

            $invitation = UserInvitation::query()->create([
                'user_id' => $invitedUser->getKey(),
                'invited_email' => $email,
                'token_hash' => $this->hasher->hash($token),
                'expires_at' => $now->copy()->addMinutes(
                    $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK),
                ),
                'created_by' => $actor->getKey(),
                'method' => $method,
                'acceptance_flow' => $acceptanceFlow,
                'credential_generation' => 1,
                'recovery_code_hash' => $this->hasher->hash($recoveryCode),
                'recovery_code_expires_at' => $now->copy()->addMinutes(
                    $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_RECOVERY_CODE),
                ),
                'recovery_attempts' => 0,
                'sent_count' => 0,
            ]);

            $issuedLink = $this->issuer->issue($invitation, $actor, $ipAddress, $now);

            if ($reissue) {
                // Re-inviting an existing account is not a second account
                // creation: only the invitation issuance is audited.
                $this->auditLogger->log(
                    $actor,
                    $invitedUser,
                    'invitation.issued',
                    null,
                    [
                        'invited_email' => $email,
                        'method' => $method,
                        'expires_at' => $invitation->expires_at->toIso8601String(),
                        'credential_generation' => (int) $invitation->credential_generation,
                        'reissued' => true,
                    ],
                );
            } else {
                $this->auditLogger->log(
                    $actor,
                    $invitedUser,
                    'user.account_created',
                    null,
                    [
                        'email' => $email,
                        'method' => $method,
                        'acceptance_flow' => $acceptanceFlow,
                    ],
                );

                $this->auditLogger->log(
                    $actor,
                    $invitedUser,
                    'invitation.issued',
                    null,
                    [
                        'invited_email' => $email,
                        'method' => $method,
                        'expires_at' => $invitation->expires_at->toIso8601String(),
                        'credential_generation' => (int) $invitation->credential_generation,
                    ],
                );
            }

            return [
                'invitation' => $invitation,
                'linkRequest' => $issuedLink['linkRequest'],
                'token' => $issuedLink['token'],
            ];
        });

        return [
            'invitation' => $issued['invitation'],
            'linkRequest' => $issued['linkRequest'],
            'token' => $issued['token'],
            'recoveryCode' => $recoveryCode,
        ];
    }

    /**
     * Neutral duplicate-registration error, identical for every ineligible
     * state so callers learn nothing about other accounts.
     */
    private const EMAIL_REGISTERED_MESSAGE = 'This email address is already registered.';

    /**
     * Only an invited account whose previous invitation is cancelled may
     * be invited again. Active, deactivated (soft-deleted), and accounts
     * with a still-outstanding invitation are rejected with the same
     * neutral answer as an unknown registration.
     */
    private function assertReissueAllowed(User $user): void
    {
        if ($user->trashed()
            || $user->account_status !== 'invited'
            || $user->invitations()->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'email' => self::EMAIL_REGISTERED_MESSAGE,
            ]);
        }

        $latestInvitation = $user->invitations()
            ->latest('id')
            ->first();

        if ($latestInvitation === null || ! $latestInvitation->isCancelled()) {
            throw ValidationException::withMessages([
                'email' => self::EMAIL_REGISTERED_MESSAGE,
            ]);
        }
    }
}
