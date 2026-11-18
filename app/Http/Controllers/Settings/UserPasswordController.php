<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ForceUserPasswordResetAction;
use App\Actions\SetUserPasswordAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordResetException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administrative password management (§11.2) on the user details page:
 * manually set a new password, or force a single-use email-link reset.
 * Controllers only validate the request shape; the actions own the
 * authorization invariants, the transactions, the session invalidation,
 * and the audit. Self-password management is denied here (§6.5).
 */
class UserPasswordController extends Controller
{
    public function __construct(
        private readonly SetUserPasswordAction $setUserPasswordAction,
        private readonly ForceUserPasswordResetAction $forceUserPasswordResetAction,
    ) {}

    /**
     * Manually set the target user's password.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'max:64',
                'confirmed',
                new PasswordPolicy,
            ],
        ]);

        try {
            $this->setUserPasswordAction->execute(
                $request->user(),
                $user,
                $validated['current_password'],
                $validated['password'],
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }

        return redirect()
            ->route('settings.users.show', $user->getKey())
            ->with('status', 'The password was updated. The user must sign in with the new password.');
    }

    /**
     * Force the target user to complete a password reset via an emailed
     * single-use link.
     */
    public function forceReset(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'confirmed' => ['accepted'],
        ]);

        try {
            $this->forceUserPasswordResetAction->execute($request->user(), $user);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (PasswordResetException $exception) {
            return redirect()
                ->route('settings.users.show', $user->getKey())
                ->withErrors(['force_reset' => $exception->getMessage()]);
        }

        return redirect()
            ->route('settings.users.show', $user->getKey())
            ->with('status', 'A password reset link was emailed to the user. Their sessions were ended and sign-in is blocked until the reset is completed.');
    }
}
