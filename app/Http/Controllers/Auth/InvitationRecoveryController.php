<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecoverUserInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use App\Services\Auth\InvitationRateLimitException;
use App\Services\Notifications\InvitationDeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guest-facing invitation recovery: the email address and recovery code
 * taken from the original invitation email exchange for a fresh link sent
 * to the same address.
 *
 * The credential rotation commits first; the replacement email then goes
 * out through the delivery service, so a transport failure is recorded as
 * a failed delivery without cancelling the invitation or undoing the
 * rotation.
 */
class InvitationRecoveryController extends Controller
{
    public function __construct(
        private readonly RecoverUserInvitationAction $recoveryAction,
        private readonly InvitationDeliveryService $deliveryService,
    ) {}

    public function show(Request $request): View
    {
        return view('invitations.recover');
    }

    public function store(Request $request): View
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'recovery_code' => ['required', 'string', 'max:20'],
        ]);

        try {
            $recovered = $this->recoveryAction->execute(
                $credentials['email'],
                $credentials['recovery_code'],
                $request->ip(),
            );
        } catch (InvitationRateLimitException $exception) {
            throw ValidationException::withMessages([
                'recovery' => $exception->getMessage(),
            ]);
        }

        // The rotation transaction has committed; send the replacement
        // link for the freshly issued generation. Delivery failures are
        // recorded by the service and never cancel the invitation.
        if ($recovered['linkRequest']->method === UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP) {
            $this->deliveryService->sendDirectAccountSetupLink(
                $recovered['linkRequest'],
                $recovered['token'],
            );
        } else {
            $this->deliveryService->sendInvitationLink(
                $recovered['linkRequest'],
                $recovered['token'],
                '',
            );
        }

        return view('invitations.recovery-success', [
            'email' => strtolower(trim($credentials['email'])),
        ]);
    }
}
