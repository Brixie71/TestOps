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
 * Unassign the designated ownership of an owned project as one
 * transactionally atomic ownership change.
 *
 * Only SUPER_ADMIN and SYSTEM_ADMIN (through the system-scope
 * projects.manage-ownership permission) may unassign ownership. Both
 * owner fields are cleared together (the schema pairing constraint
 * requires the pair). The former owner keeps membership and the
 * PROJECT_ADMIN role, and unassignment remains available even when the
 * current owner is soft-deleted or otherwise ineligible: this action is
 * one of the sanctioned paths for resolving that state. Inside one
 * transaction: the project row is locked, the actor is reloaded,
 * authorization and state are re-validated against current database
 * values, the owner fields are cleared through forceFill, and the
 * mutation plus its audit record share one transaction.
 */
class UnassignProjectOwnerAction
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
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);

        DB::transaction(function () use ($actor, $project, $reason, &$result): void {
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
                    'owner' => 'The project does not have a designated owner to unassign.',
                ]);
            }

            $previousOwnerId = $project->owner_id;
            $previousAssignmentType = $project->owner_assignment_type;

            // The former owner is resolved for the audit target, soft-deleted
            // state included; a permanently deleted owner stays auditable
            // through the id values alone.
            $formerOwner = User::query()->withTrashed()->find($previousOwnerId);

            $project->forceFill([
                'owner_id' => null,
                'owner_assignment_type' => null,
            ])->save();

            $this->auditLogger->log(
                $actor,
                $formerOwner,
                'project.ownership.unassigned',
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => $previousOwnerId,
                    'owner_assignment_type' => $previousAssignmentType,
                ],
                [
                    'project_id' => $project->getKey(),
                    'owner_id' => null,
                    'owner_assignment_type' => null,
                    'previous_owner_id' => $previousOwnerId,
                    'previous_owner_assignment_type' => $previousAssignmentType,
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
