<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancel a pending invitation by an administrator.
 *
 * Only pending invitations can be cancelled; accepted ones have become
 * accounts themselves. The cancelled-by administrator and reason are
 * recorded on the invitation and audited.
 */
class CancelUserInvitationAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        User $actor,
        UserInvitation $invitation,
        string $reason,
    ): UserInvitation {
        $cancelledInvitation = DB::transaction(function () use ($actor, $invitation, $reason): UserInvitation {
            $locked = UserInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isAccepted() || $locked->isCancelled()) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only pending invitations can be cancelled.',
                ]);
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'cancelled_reason' => $reason,
                'cancelled_by' => $actor->getKey(),
            ])->save();

            $this->auditLogger->log(
                $actor,
                $locked->user,
                'invitation.cancelled',
                null,
                [
                    'cancelled_reason' => $reason,
                    'cancelled_by_id' => (int) $actor->getKey(),
                ],
            );

            return $locked;
        });

        return $cancelledInvitation;
    }
}
