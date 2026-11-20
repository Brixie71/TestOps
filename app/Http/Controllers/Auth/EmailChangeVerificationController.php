<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CompleteEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Models\EmailVerificationRequest;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Contracts\View\View;

/**
 * Guest-facing confirmation for a self-service email change (§9.4).
 *
 * The emailed single-use link authorizes the change: the GET renders a
 * confirmation page without consuming the token (so mail-scanner prefetches
 * cannot burn it), and the CSRF-protected POST consumes it, moving the
 * account to the new address atomically.
 */
class EmailChangeVerificationController extends Controller
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly CompleteEmailChangeAction $completeAction,
        private readonly EmailVerificationService $verificationService,
    ) {}

    public function show(string $token): View
    {
        $verificationRequest = EmailVerificationRequest::query()
            ->where('token_hash', $this->hasher->hash($token))
            ->first();

        if ($verificationRequest === null
            || $verificationRequest->purpose !== EmailVerificationService::PURPOSE_EMAIL_CHANGE
        ) {
            return view('email-verification.invalid', [
                'message' => 'This email change link is no longer valid.',
            ]);
        }

        $message = match (true) {
            $verificationRequest->state === EmailVerificationRequest::STATE_CONSUMED => 'This email change link has already been used.',
            $verificationRequest->state === EmailVerificationRequest::STATE_EXPIRED => 'This email change link has expired. Start a new email change from your profile.',
            $verificationRequest->state === EmailVerificationRequest::STATE_INVALIDATED => 'This email change link is no longer valid.',
            $verificationRequest->expires_at->lessThan(now()) => 'This email change link has expired. Start a new email change from your profile.',
            default => null,
        };

        if ($message !== null) {
            return view('email-verification.invalid', ['message' => $message]);
        }

        return view('email-verification.change-confirm', [
            'token' => $token,
            'email' => $verificationRequest->email,
        ]);
    }

    public function store(string $token): View
    {
        if ($token === '') {
            return view('email-verification.invalid', [
                'message' => 'This email change link is no longer valid.',
            ]);
        }

        try {
            $this->completeAction->execute($token);
        } catch (EmailVerificationException $exception) {
            $this->verificationService->recordRejectedAttempt(
                $token,
                EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            );

            return view('email-verification.invalid', [
                'message' => match ($exception->reason) {
                    EmailVerificationException::REASON_ALREADY_CONSUMED => 'This email change link has already been used.',
                    EmailVerificationException::REASON_EXPIRED => 'This email change link has expired. Start a new email change from your profile.',
                    default => 'This email change link is no longer valid.',
                },
            ]);
        }

        return view('email-verification.change-success');
    }
}
