<?php

namespace App\Http\Controllers\Settings;

use App\Actions\AssignUserRoleAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly AssignUserRoleAction $assignUserRoleAction,
    ) {}

    /**
     * Assign (or clear) a user account's system role. Controllers only
     * validate the request shape; the action owns the transaction, the
     * authorization invariants, and the audit.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where('scope', 'system'),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->assignUserRoleAction->execute(
            $request->user(),
            $user,
            $validated['role_id'] ?? null,
        );

        return redirect()
            ->route('settings.users.show', $user->getKey())
            ->with('status', 'The system role was updated.');
    }
}
