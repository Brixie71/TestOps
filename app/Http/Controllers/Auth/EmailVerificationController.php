<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CompleteAccountActivationAction;
use App\Http\Controllers\Controller;
use App\Models\EmailVerificationRequest;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Guest-facing account email verification as a deliberate two-step flow:
 * the GET link renders a confirmation page without consuming the token
 * (so mail-scanner prefetches cannot burn it), and the CSRF-protected POST
 * consumes the token, completing the activation with all of its account
 * updates atomically.
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly CompleteAccountActivationAction $activationAction,
    ) {}

    public function show(Request $request): View
    {
        $token = (string) $request->query('token');

        if ($token === '') {
            return view('email-verification.invalid', [
                'message' => 'This email verification link is no longer valid.',
            ]);
        }

        $verificationRequest = $this->verificationOutcome($token);

        if ($verificationRequest === null) {
            return view('email-verification.invalid', [
                'message' => 'This email verification link is no longer valid.',
            ]);
        }

        // Classification only: the state is never mutated here, so a
        // prefetch cannot consume or reclassify anything.
        $message = match (true) {
            $verificationRequest->state === EmailVerificationRequest::STATE_CONSUMED => 'This email verification link has already been used.',
            $verificationRequest->state === EmailVerificationRequest::STATE_EXPIRED => 'This email verification link has expired. Please request a new one.',
            $verificationRequest->state === EmailVerificationRequest::STATE_INVALIDATED => 'This email verification link is no longer valid.',
            $verificationRequest->purpose !== EmailVerificationService::PURPOSE_ACCOUNT_ACTIVATION => 'This email verification link is not valid for this action.',
            $verificationRequest->expires_at->lessThan(now()) => 'This email verification link has expired. Please request a new one.',
            default => null,
        };

        if ($message !== null) {
            return view('email-verification.invalid', [
                'message' => $message,
            ]);
        }

        return view('email-verification.confirm', ['token' => $token]);
    }

    public function store(Request $request): View
    {
        $token = (string) $request->input('token');

        if ($token === '') {
            return view('email-verification.invalid', [
                'message' => 'This email verification link is no longer valid.',
            ]);
        }

        try {
            $this->activationAction->execute($token);
        } catch (EmailVerificationException $exception) {
            return view('email-verification.invalid', [
                'message' => $exception->getMessage(),
            ]);
        }

        return view('email-verification.success');
    }

    /**
     * Resolve the verification request for a token without mutating any
     * lifecycle state; a null return means the invalid view should render.
     * Consumed/expired/invalidated handling on the POST side re-classifies
     * expiry through the activation action, exactly as before.
     */
    private function verificationOutcome(string $token): ?EmailVerificationRequest
    {
        $request_ = EmailVerificationRequest::query()
            ->where('token_hash', $this->hasher->hash($token))
            ->first();

        if ($request_ === null) {
            return null;
        }

        return $request_;
    }
}
