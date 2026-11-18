<?php

namespace App\Http\Controllers\Settings;

use App\Actions\CancelUserInvitationAction;
use App\Actions\IssueUserInvitationAction;
use App\Actions\ResendUserInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\InvitationLinkRequest;
use App\Models\UserInvitation;
use App\Services\Notifications\InvitationDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administrative account creation through invitations or direct setup.
 *
 * Controllers only validate request shape; the actions own the
 * transaction, the authorization invariants, and the audits. Credentials
 * are emailed to the invited recipient; no private response or view ever
 * contains a token, recovery code, or password.
 */
class UserInvitationController extends Controller
{
    public function __construct(
        private readonly IssueUserInvitationAction $issueAction,
        private readonly ResendUserInvitationAction $resendAction,
        private readonly CancelUserInvitationAction $cancelAction,
        private readonly InvitationDeliveryService $deliveryService,
    ) {}

    /**
     * Issue an account offer to one email address.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'method' => ['required', 'in:'.implode(',', [
                UserInvitation::METHOD_INVITATION_LINK,
                UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP,
            ])],
            'acceptance_flow' => ['nullable', 'in:'.implode(',', [
                UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
                UserInvitation::ACCEPTANCE_FLOW_REVIEW_THEN_PASSWORD,
            ])],
        ]);

        $email = strtolower(trim($validated['email']));

        $issued = $this->issueAction->execute(
            $request->user(),
            $email,
            $validated['method'],
            $validated['acceptance_flow'] ?? UserInvitation::ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY,
            $validated['name'] ?? null,
            $request->ip(),
        );

        $this->deliver(
            $issued['linkRequest'],
            $issued['token'],
            $issued['recoveryCode'],
        );

        return back()->with('status', 'Account offer issued. The credentials were emailed to '.$email.'.');
    }

    /**
     * Send a fresh invitation link, rotating the credentials.
     */
    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        $resent = $this->resendAction->execute($invitation, $request->user(), $request->ip());

        $this->deliver($resent['linkRequest'], $resent['token']);

        return back()->with('status', 'A fresh invitation link was sent to the recipient.');
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancel(Request $request, UserInvitation $invitation): RedirectResponse
    {
        $validated = $request->validate([
            'cancelled_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->cancelAction->execute($request->user(), $invitation, $validated['cancelled_reason']);

        return back()->with('status', 'The invitation was cancelled.');
    }

    /**
     * Send the invitation email after the account transaction, recording
     * the immutable delivery outcome. The recovery code only exists for the
     * initial invitation-link email.
     */
    private function deliver(
        InvitationLinkRequest $linkRequest,
        string $token,
        ?string $recoveryCode = null,
    ): void {
        $isDirectSetup = $linkRequest->method === UserInvitation::METHOD_DIRECT_ACCOUNT_SETUP;

        if ($isDirectSetup) {
            $this->deliveryService->sendDirectAccountSetupLink($linkRequest, $token);

            return;
        }

        $this->deliveryService->sendInvitationLink($linkRequest, $token, $recoveryCode ?? '');
    }
}
