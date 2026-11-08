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
 * Change the assignment type of an owned project's designated owner
 * between permanent and temporary as one transactionally atomic ownership
 * change.
 *
 * Only SUPER_ADMIN and SYSTEM_ADMIN (through the system-scope
 * projects.manage-ownership permission) may change the assignment type.
 * The owner stays unchanged and must remain invariant-compliant at the
 * moment of the change: active, non-soft-deleted, a project member, and
 * holding the canonical project-scoped PROJECT_ADMIN role. Transferring
 * ownership away from an ineligible current owner is the sanctioned
 * alternative (transfer and unassignment do not require current-owner
 * eligibility). Inside one transaction: the project row is locked, actor
 * and current owner are reloaded, authorization and invariants are
 * re-evaluated against current state, the assignment type is written
 * through forceFill, and the mutation plus its audit record share one
 * transaction.
 */
class ChangeProjectOwnerAssignmentTypeAction
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
        string $assignmentType,
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);
        $this->rules->assertAssignmentType($assignmentType);

        DB::transaction(function () use ($actor, $project, $assignmentType, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

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
                    'owner' => 'The project does not have a designated owner whose assignment type could change.',
                ]);
            }

            // No-op changes are rejected rather than recorded.
            if ($project->owner_assignment_type === $assignmentType) {
                throw ValidationException::withMessages([
                    'assignment_type' => 'The designated owner already has this assignment type.',
                ]);
            }

            // The designated-owner invariant must hold: owners that lost
            // eligibility keep their ownership, but eligibility is required
            // again before this operation. Transfer and unassignment remain
            // the paths that resolve ineligible ownership.
            $currentOwner = User::query()->withTrashed()->find($project->owner_id);

            if ($currentOwner === null) {
                throw ValidationException::withMessages([
                    'owner' => 'The designated project owner no longer exists, so the ownership invariant cannot be maintained. Use ownership transfer or unassignment instead.',
                ]);
            }

            $this->rules->assertEligibleOwner($project, $currentOwner);

            $project->forceFill([
                'owner_assignment_type' => $assignmentType,
            ])->save();

            $this->auditLogger->log(
                $actor,
                $currentOwner,
                'project.ownership.assignment_type_changed',
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $project->owner_id,
                    'owner_assignment_type' => $assignmentType === 'temporary' ? 'permanent' : 'temporary',
                ],
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $project->owner_id,
                    'owner_assignment_type' => $project->owner_assignment_type,
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
