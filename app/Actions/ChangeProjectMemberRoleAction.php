<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Projects\ProjectMembershipRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Change an existing project member's project-scoped role as one
 * transactionally atomic membership change.
 *
 * Role changes are role-assignment operations: the acting user must
 * satisfy the assignRoles policy ability. Inside one transaction owned by
 * this action: the project row is locked and reloaded, the actor and
 * target are reloaded (fail closed for the actor), the new project role
 * and the current membership are reloaded, authorization and invariants
 * are re-evaluated against current database state, the pivot role is
 * updated, newly granted PROJECT_ADMIN members may automatically become
 * the permanent owner through the approved automatic assignment trigger,
 * and the role change plus its audit record are written in the same
 * transaction.
 */
class ChangeProjectMemberRoleAction
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
        int $newRoleId,
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);

        DB::transaction(function () use ($actor, $project, $user, $newRoleId, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

            // Explicitly resolvable soft-deleted targets stay detectable.
            $member = $this->rules->reloadTargetUser($user);

            if (! Gate::forUser($actor)->allows('assignRoles', $project)) {
                throw new AuthorizationException(
                    'You are not authorized to change project member roles.'
                );
            }

            $newRole = $this->rules->resolveProjectRole($newRoleId);
            $currentRole = $this->rules->currentMembershipRole($project, $member);

            if ($currentRole === null) {
                throw ValidationException::withMessages([
                    'user' => 'The user is not a member of this project.',
                ]);
            }

            // No-op role changes are rejected rather than recorded.
            if ($currentRole->is($newRole)) {
                throw ValidationException::withMessages([
                    'role' => 'The member already holds this project role.',
                ]);
            }

            // PROJECT_ADMIN cannot change their own project role. System
            // roles (SUPER_ADMIN/SYSTEM_ADMIN working through their grants)
            // are not restricted by this member-level rule.
            $isSystemRoleActor = $actor->role !== null && $actor->role->scope === 'system';

            if (! $isSystemRoleActor && (int) $member->getKey() === (int) $actor->getKey()) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot change your own project role.',
                ]);
            }

            // The designated owner keeps the PROJECT_ADMIN role until
            // ownership is transferred or unassigned.
            if ($project->owner_id !== null && (int) $project->owner_id === (int) $member->getKey()) {
                throw ValidationException::withMessages([
                    'role' => 'The designated project owner keeps the PROJECT_ADMIN role until ownership is transferred or unassigned.',
                ]);
            }

            // Never remove the last active PROJECT_ADMIN member.
            if ($this->reducesLastAdmin($currentRole, $newRole, $project)) {
                throw ValidationException::withMessages([
                    'role' => 'The project must retain at least one active PROJECT_ADMIN member.',
                ]);
            }

            $project->members()->updateExistingPivot($member->getKey(), ['role_id' => $newRole->getKey()]);

            $this->auditLogger->log(
                $actor,
                $member,
                'project.member.role_changed',
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => $currentRole->getKey(),
                    'project_role_code' => $currentRole->code,
                ],
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => $newRole->getKey(),
                    'project_role_code' => $newRole->code,
                ],
                $reason,
            );

            if ($this->isCanonicalAdminRole($newRole) && ! $this->isCanonicalAdminRole($currentRole)) {
                $this->rules->maybeAssignOwnershipAutomatically($actor, $project, $member, $reason);
            }

            $result = $project;
        });

        return $result;
    }

    private function reducesLastAdmin(Role $currentRole, Role $newRole, Project $project): bool
    {
        if (! $this->isCanonicalAdminRole($currentRole) || $this->isCanonicalAdminRole($newRole)) {
            return false;
        }

        return $this->rules->countActiveAdminMembers($project) <= 1;
    }

    private function isCanonicalAdminRole(Role $role): bool
    {
        return $this->rules->canonicalAdminRole()->getKey() === $role->getKey();
    }
}
