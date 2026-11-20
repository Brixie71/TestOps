<?php

namespace App\Http\Controllers\Settings;

use App\Actions\CancelEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administrative cancellation of another user's pending email change
 * (§9.6): requires the `users.email.manage` permission, a recorded reason,
 * and the protected-target restrictions enforced in the action. The
 * account holder is notified at their current verified address after the
 * commit; a delivery failure never rolls back the cancellation.
 */
class UserEmailChangeController extends Controller
{
    public function __construct(
        private readonly CancelEmailChangeAction $cancelEmailChangeAction,
    ) {}

    public function cancel(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->cancelEmailChangeAction->execute(
                $request->user(),
                $user,
                null,
                $validated['reason'],
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }

        return redirect()
            ->route('settings.users.show', $user->getKey())
            ->with('status', 'The pending email change was cancelled and the user was notified at their current verified address.');
    }
}
