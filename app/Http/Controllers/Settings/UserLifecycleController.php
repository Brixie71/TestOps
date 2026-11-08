<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeactivateUserAction;
use App\Actions\ReactivateUserAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserLifecycleController extends Controller
{
    public function __construct(
        private readonly DeactivateUserAction $deactivateUserAction,
        private readonly ReactivateUserAction $reactivateUserAction,
    ) {}

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->deactivateUserAction->execute($request->user(), $user, $validated['reason']);

        return redirect()->route('settings.users');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->reactivateUserAction->execute($request->user(), $user);

        return redirect()->route('settings.users');
    }
}
