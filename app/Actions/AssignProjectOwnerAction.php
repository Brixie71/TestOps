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
 * Assign the first designated owner of an unassigned project as one
 * transactionally atomic ownership change.
 *
 * Only SUPER_ADMIN and SYSTEM_ADMIN (through the system-scope
 * projects.manage-ownership permission) may assign ownership. Inside one
 * transaction owned by this action: the project row is locked (the same
 * serialization root as the membership actions), the actor and target are
 * reloaded, authorization and invariants are re-evaluated against current
 * database state, the owner and assignment type are written through
 * forceFill, and the mutation plus its audit record share one transaction.
 *
 * This action never replaces an existing owner: an already-owned project
 * is rejected, leaving transfer and unassignment as the only paths.
 */
class AssignProjectOwnerAction
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
                $project->owner_id !== null
                || $project->owner_assignment_type !== null
            ) {
                throw ValidationException::withMessages([
                    'owner' => 'The project already has a designated owner. Use ownership transfer or unassignment instead.',
                ]);
            }

            $this->rules->assertEligibleOwner($project, $newOwner);

            $project->forceFill([
                'owner_id' => $newOwner->getKey(),
                'owner_assignment_type' => $assignmentType,
            ])->save();

            $this->auditLogger->log(
                $actor,
                $newOwner,
                'project.ownership.assigned',
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => null,
                    'owner_assignment_type' => null,
                ],
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $project->owner_id,
                    'owner_assignment_type' => $project->owner_assignment_type,
                    'transfer_type' => 'manual',
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
