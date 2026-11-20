<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administrative profile editing on the user details page (§3.1, §6.3).
 *
 * Controllers only validate the request shape; the action owns the
 * authorization invariants (the `users.profile.manage` permission, the
 * self-management restriction, and the protected-target restrictions), the
 * transaction, and the `user.profile_updated` audit. Deactivated accounts
 * have no implicit binding here, so they must be reactivated first.
 */
class UserProfileController extends Controller
{
    public function __construct(
        private readonly UpdateUserProfileAction $updateUserProfileAction,
    ) {}

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        // Self-editing belongs to the profile page (§6.5).
        if ($request->user()->is($user)) {
            abort(403, 'You cannot edit your own profile through administrative profile editing.');
        }

        try {
            $this->updateUserProfileAction->execute(
                $request->user(),
                $user,
                $validated,
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }

        return redirect()->route('settings.users.show', $user->getKey());
    }
}
