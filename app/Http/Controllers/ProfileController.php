<?php

namespace App\Http\Controllers;

use App\Actions\CancelEmailChangeAction;
use App\Actions\ChangeOwnPasswordAction;
use App\Actions\RequestEmailChangeAction;
use App\Actions\UpdateUserProfileAction;
use App\Models\NotificationDelivery;
use App\Models\UserEmailAddress;
use App\Models\UserNotificationPreference;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own profile (§3.1, §9.4, §9.6).
 *
 * Profile and email changes are self-service: profile fields are edited
 * directly, while the current email address stays active until a new one is
 * verified through an emailed single-use link. Either the pending request or
 * the whole change can be cancelled with the current password. Password
 * changes are self-service too, confirmed with the current password.
 * Credential and identity rules live in the actions; this controller
 * validates the request shape and renders the state.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly RequestEmailChangeAction $requestEmailChangeAction,
        private readonly CancelEmailChangeAction $cancelEmailChangeAction,
        private readonly UpdateUserProfileAction $updateUserProfileAction,
        private readonly ChangeOwnPasswordAction $changeOwnPasswordAction,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.index', [
            'user' => $user,
            'pendingEmailChange' => UserEmailAddress::query()
                ->where('user_id', $user->getKey())
                ->where('status', UserEmailAddress::STATUS_PENDING)
                ->first(),
            // §14.3: optional categories default to enabled until the user
            // saves a preference; security notifications are mandatory.
            'notificationPreferences' => $user->notificationPreference,
            // §15.6: the account holder's own recent deliveries. Only the
            // user-safe fields are rendered; internal diagnostics stay out.
            'notificationDeliveries' => NotificationDelivery::query()
                ->where('user_id', $user->getKey())
                ->with('retries')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Update the signed-in user's own profile fields (§3.1, §6.5).
     *
     * Only name, contact number, job title, and department are accepted.
     * Email, password, role, and account status keep their separate
     * workflows, so any extra submitted input is ignored.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $this->updateUserProfileAction->execute($user, $user, $validated);

        return redirect()
            ->route('profile')
            ->with('status', 'Your profile was updated.');
    }

    /**
     * Change the signed-in user's own password (§6.5, §11.4).
     *
     * The current password must be confirmed and the new password must
     * satisfy the approved policy. The current session stays signed in,
     * every other database session is ended, and existing OAuth tokens are
     * left untouched.
     */
    public function updatePassword(Request $request): RedirectResponse
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

        $this->changeOwnPasswordAction->execute(
            $request->user(),
            $validated['current_password'],
            $validated['password'],
            $request->session()->getId(),
        );

        return redirect()
            ->route('profile')
            ->with('status', 'Your password was changed. Your other signed-in sessions have been ended.');
    }

    /**
     * Update the signed-in user's optional notification categories (§14.3).
     *
     * Security and account activity notifications are mandatory and are not
     * configurable here. An unchecked optional category is stored as
     * disabled; the preference is created on first save.
     */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $request->validate([
            'project_activity_enabled' => ['nullable', 'boolean'],
            'system_announcements_enabled' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'project_activity_enabled' => $request->boolean('project_activity_enabled'),
                'system_announcements_enabled' => $request->boolean('system_announcements_enabled'),
            ],
        );

        return redirect()
            ->route('profile')
            ->with('status', 'Your notification preferences were updated.');
    }

    /**
     * Start or replace a self-service email change.
     */
    public function requestEmailChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'current_password' => ['required', 'string'],
        ]);

        $this->requestEmailChangeAction->execute(
            $request->user(),
            $validated['current_password'],
            $validated['email'],
            $request->ip(),
        );

        return redirect()
            ->route('profile')
            ->with('status', 'A verification link was sent to your new address. Your current email stays active until you confirm it.');
    }

    /**
     * Cancel the signed-in user's own pending email change.
     */
    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $this->cancelEmailChangeAction->execute(
            $request->user(),
            $request->user(),
            $validated['current_password'],
        );

        return redirect()
            ->route('profile')
            ->with('status', 'Your pending email change was cancelled. Your current email remains unchanged.');
    }
}
