<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\InvitationLinkRequest;
use App\Services\Auth\CredentialHasher;
use App\Services\Auth\InvitationLinkException;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Guest-facing invitation acceptance page (also the password-setup page for
 * direct account setup).
 *
 * The GET action renders the acceptance form for usable links only; every
 * unusable state renders a credential-free invalid page. POST acceptance is
 * throttled at the route level and fully validated inside the action.
 */
class InvitationAcceptanceController extends Controller
{
    public function __construct(
        private readonly CredentialHasher $hasher,
        private readonly AcceptInvitationAction $acceptAction,
    ) {}

    public function show(Request $request, string $token): View
    {
        $outcome = $this->linkOutcome($token);

        if ($outcome['invitationLink'] === null) {
            return view('invitations.invalid', [
                'title' => $outcome['title'],
                'message' => $outcome['message'],
            ]);
        }

        return view('invitations.accept', [
            'token' => $token,
            'email' => $outcome['invitationLink']->recipient_email,
            'method' => $outcome['invitationLink']->method,
        ]);
    }

    public function store(Request $request, string $token): View
    {
        $credentials = $request->validate([
            'password' => [
                'required',
                'string',
                'max:64',
                'confirmed',
                new PasswordPolicy,
            ],
        ]);

        $outcome = $this->linkOutcome($token);

        if ($outcome['invitationLink'] === null) {
            return view('invitations.invalid', [
                'title' => $outcome['title'],
                'message' => $outcome['message'],
            ]);
        }

        try {
            $this->acceptAction->execute($token, $credentials['password']);
        } catch (InvitationLinkException $exception) {
            return view('invitations.invalid', [
                'title' => 'Invitation link unavailable',
                'message' => $exception->getMessage(),
            ]);
        }

        return view('invitations.accept-success', [
            'email' => $outcome['invitationLink']->recipient_email,
        ]);
    }

    /**
     * Classify the supplied link token without exposing any credential; a
     * null invitationLink means the invalid view should render with the
     * returned title and message.
     *
     * @return array{invitationLink: ?InvitationLinkRequest, title: string, message: string}
     */
    private function linkOutcome(string $token): array
    {
        $linkRequest = InvitationLinkRequest::query()
            ->where('token_hash', $this->hasher->hash($token))
            ->first();

        if ($linkRequest === null) {
            return [
                'invitationLink' => null,
                'title' => 'Invitation link unavailable',
                'message' => 'This invitation link is no longer valid.',
            ];
        }

        if ($linkRequest->consumed_at !== null) {
            return [
                'invitationLink' => null,
                'title' => 'Invitation link already used',
                'message' => 'This invitation link has already been used.',
            ];
        }

        $now = now();

        if ($linkRequest->expires_at->lessThan($now)) {
            return [
                'invitationLink' => null,
                'title' => 'Invitation link expired',
                'message' => 'This invitation link has expired. Please request a fresh link.',
            ];
        }

        $invitation = $linkRequest->invitation;

        // A superseded generation means a fresh link was issued through a
        // resend or recovery; this older link no longer renders an
        // acceptance form.
        if ((int) $invitation->credential_generation !== (int) $linkRequest->credential_generation) {
            return [
                'invitationLink' => null,
                'title' => 'Invitation link unavailable',
                'message' => 'This invitation link is no longer valid.',
            ];
        }

        return match (true) {
            $invitation->isCancelled() => [
                'invitationLink' => null,
                'title' => 'Invitation cancelled',
                'message' => 'This invitation was cancelled. Please contact an administrator.',
            ],
            $invitation->isAccepted() => [
                'invitationLink' => null,
                'title' => 'Invitation already accepted',
                'message' => 'This invitation has already been accepted.',
            ],
            default => ['invitationLink' => $linkRequest, 'title' => '', 'message' => ''],
        };
    }
}
