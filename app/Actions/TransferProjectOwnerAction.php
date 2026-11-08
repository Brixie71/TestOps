<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Projects\ProjectMembershipRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transfer the designated ownership of an owned project to another
 * eligible PROJECT_ADMIN member as one transactionally atomic ownership
 * change.
 *
 * Only SUPER_ADMIN and SYSTEM_ADMIN (through the system-scope
 * projects.manage-ownership permission) may transfer ownership. The new
 * assignment type is explicit and is never inherited from the previous
 * owner. The previous owner keeps membership and the PROJECT_ADMIN role;
 * eligibility rules (active, non-soft-deleted, member, canonical
 * PROJECT_ADMIN) apply to the incoming owner only, so ownership held by a
 * soft-deleted or otherwise ineligible current owner stays resolvable.
 * Inside one transaction: the project row is locked, actor and target are
 * reloaded, authorization and invariants are re-evaluated against current
 * state, the owner and assignment type are written through forceFill, and
 * the mutation plus its audit record share one transaction.
 */
class TransferProjectOwnerAction
{
    public function __construct(
        private readonly ProjectMembershipRules $rules,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws AuthorizationException when the actor is not authorized
     */
    public function execute(
        User $actor,
        Project $project,
        User $user,
        string $assignmentType,
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);
        $this->rules->assertAssignmentType($assignmentType);

        DB::transaction(function () use ($actor, $project, $user, $assignmentType, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

            // Explicitly resolvable soft-deleted targets stay detectable.
            $newOwner = $this->rules->reloadTargetUser($user);

            if (! Gate::forUser($actor)->allows('manageOwnership', $project)) {
                throw new AuthorizationException(
                    'You are not authorized to manage project ownership.'
                );
            }

            if (
                $project->owner_id === null
                || $project->owner_assignment_type === null
            ) {
                throw ValidationException::withMessages([
                    'owner' => 'The project does not have a designated owner to transfer. Use assignment instead.',
                ]);
            }

            // Transferring to the current owner is a no-op; assignment-type
            // changes are the separate operation for that need.
            if ((int) $project->owner_id === (int) $newOwner->getKey()) {
                throw ValidationException::withMessages([
                    'user' => 'The user is already the designated project owner.',
                ]);
            }

            // Eligibility applies to the incoming owner only.
            $this->rules->assertEligibleOwner($project, $newOwner);

            $previousOwnerId = $project->owner_id;
            $previousAssignmentType = $project->owner_assignment_type;

            $project->forceFill([
                'owner_id' => $newOwner->getKey(),
                'owner_assignment_type' => $assignmentType,
            ])->save();

            $this->auditLogger->log(
                $actor,
                $newOwner,
                'project.ownership.transferred',
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $previousOwnerId,
                    'owner_assignment_type' => $previousAssignmentType,
                ],
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $project->owner_id,
                    'owner_assignment_type' => $project->owner_assignment_type,
                    'previous_owner_id' => $previousOwnerId,
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
