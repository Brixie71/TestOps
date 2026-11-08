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
 * Remove an existing project member as one transactionally atomic
 * membership change.
 *
 * Inside one transaction owned by this action: the project row is locked
 * and reloaded, the actor and target are reloaded (fail closed for the
 * actor), the membership is reloaded, authorization and invariants are
 * re-evaluated against current database state, the pivot row is deleted,
 * and the removal plus its audit record are written in the same
 * transaction. Ownership is never mutated here: removing the designated
 * owner is refused until ownership is transferred or unassigned by the
 * dedicated ownership actions.
 */
class RemoveProjectMemberAction
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
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);

        DB::transaction(function () use ($actor, $project, $user, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

            // Explicitly resolvable soft-deleted targets stay detectable.
            $member = $this->rules->reloadTargetUser($user);

            if (! Gate::forUser($actor)->allows('manageMembers', $project)) {
                throw new AuthorizationException(
                    'You are not authorized to remove project members.'
                );
            }

            $currentRole = $this->rules->currentMembershipRole($project, $member);

            if ($currentRole === null) {
                throw ValidationException::withMessages([
                    'user' => 'The user is not a member of this project.',
                ]);
            }

            // A soft-deleted account must not remain project state to be
            // cleaned up here; its eligibility is rejected explicitly.
            if ($member->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'Soft-deleted accounts are ineligible for project membership.',
                ]);
            }

            $isSelf = (int) $member->getKey() === (int) $actor->getKey();
            $isOwner = $project->owner_id !== null
                && (int) $project->owner_id === (int) $member->getKey();

            // The designated owner keeps membership and the PROJECT_ADMIN
            // role until ownership is transferred or unassigned.
            if ($isOwner) {
                throw ValidationException::withMessages([
                    'user' => 'The designated project owner cannot be removed until ownership is transferred or unassigned.',
                ]);
            }

            // Self-removal is reserved for PROJECT_ADMIN members; ordinary
            // members cannot manage their own membership in this slice.
            if ($isSelf && $actor->role?->scope !== 'system') {
                $adminRole = $this->rules->canonicalAdminRole();

                if (! $adminRole->is($currentRole)) {
                    throw ValidationException::withMessages([
                        'user' => 'Only PROJECT_ADMIN members may remove their own membership.',
                    ]);
                }
            }

            // Never remove the last active PROJECT_ADMIN member.
            if (
                $this->rules->canonicalAdminRole()->is($currentRole)
                && $this->rules->countActiveAdminMembers($project) <= 1
            ) {
                throw ValidationException::withMessages([
                    'user' => 'The project must retain at least one active PROJECT_ADMIN member.',
                ]);
            }

            $project->members()->detach($member->getKey());

            $this->auditLogger->log(
                $actor,
                $member,
                'project.member.removed',
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => $currentRole->getKey(),
                    'project_role_code' => $currentRole->code,
                ],
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => null,
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
