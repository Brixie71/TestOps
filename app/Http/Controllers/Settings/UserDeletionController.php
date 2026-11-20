<?php

namespace App\Http\Controllers\Settings;

use App\Actions\PermanentlyDeleteUserAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Permanent deletion of a previously deactivated account (§7.3).
 *
 * Super Admin only, enforced by route middleware and inside the action. The
 * acting Super Admin confirms their own password and records a reason; the
 * action owns the transaction, dependency checks, credential revocation, and
 * the audit record.
 */
class UserDeletionController extends Controller
{
    public function __construct(
        private readonly PermanentlyDeleteUserAction $deleteUserAction,
    ) {}

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->deleteUserAction->execute(
            $request->user(),
            $user,
            $validated['current_password'],
            $validated['reason'],
        );

        return redirect()->route('settings.users');
    }
}
