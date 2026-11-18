<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CompleteForcedPasswordResetAction;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordResetException;
use App\Services\Auth\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing forced password-reset flow (§11.3).
 *
 * The emailed single-use link authorizes the reset; the account holder
 * chooses the new password, which must satisfy the approved policy. Failed
 * completion attempts record against the token and disable it after the
 * configured limit. Every unusable state renders a credential-free invalid
 * page.
 */
class ForcedPasswordResetController extends Controller
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly CompleteForcedPasswordResetAction $completeAction,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function show(Request $request, string $token): View
    {
        $resetRequest = PasswordResetRequest::query()
            ->where('token_hash', $this->hasher->hash($token))
            ->first();

        if ($resetRequest === null
            || $resetRequest->state !== PasswordResetRequest::STATE_ISSUED
            || $resetRequest->expires_at->lessThan(now())
        ) {
            return view('invitations.invalid', [
                'title' => 'Password reset unavailable',
                'message' => 'This password reset link is no longer valid. Please request a fresh link from your administrator.',
            ]);
        }

        return view('auth.password-reset', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $this->validateNewPassword($request, $token);

        try {
            $this->completeAction->execute($token, $validated['password']);
        } catch (PasswordResetException $exception) {
            return redirect()
                ->route('password-reset.show', ['token' => $token])
                ->withErrors(['password' => $exception->getMessage()]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password was reset. You can now sign in with the new password.');
    }

    /**
     * Validate the new password fields; a failure records one attempt
     * against the token (§11.3 failed-attempt policy) before redirecting
     * back with the errors.
     *
     * @return array{password: string}
     */
    private function validateNewPassword(Request $request, string $token): array
    {
        $validator = validator($request->all(), [
            'password' => [
                'required',
                'string',
                'max:64',
                'confirmed',
                new PasswordPolicy,
            ],
        ]);

        if ($validator->fails()) {
            $this->passwordResetService->recordFailedAttempt($token);

            return redirect()
                ->route('password-reset.show', ['token' => $token])
                ->withErrors($validator)
                ->throwResponse();
        }

        return $validator->validated();
    }
}
