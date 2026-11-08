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

/**
 * Add a project member with an explicit project-scoped role as one
 * transactionally atomic membership change.
 *
 * Adding a member always assigns a project role, so the acting user must
 * satisfy BOTH the manageMembers and the assignRoles policy abilities.
 * Inside one transaction owned by this action: the project row is locked
 * (serialization root for per-project membership and ownership changes),
 * the actor, target, membership, and role state are reloaded under the
 * lock, authorization and invariants are re-evaluated against the current
 * database state, the pivot row is created, newly granted PROJECT_ADMIN
 * members may automatically become the permanent owner through the
 * approved automatic assignment trigger, and the membership mutation plus
 * its audit record are written in the same transaction.
 */
class AddProjectMemberAction
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
        int $roleId,
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);

        DB::transaction(function () use ($actor, $project, $user, $roleId, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

            // Explicitly resolvable soft-deleted targets stay detectable.
            $member = $this->rules->reloadTargetUser($user);

            if (
                ! Gate::forUser($actor)->allows('manageMembers', $project)
                || ! Gate::forUser($actor)->allows('assignRoles', $project)
            ) {
                throw new AuthorizationException(
                    'You are not authorized to add project members.'
                );
            }

            // Adding a member always assigns an explicit project-scoped role.
            $role = $this->rules->resolveProjectRole($roleId);
            $this->rules->assertMemberEligible($member);
            $this->rules->assertNotAlreadyMember($project, $member);

            $project->members()->attach($member->getKey(), ['role_id' => $role->getKey()]);

            $this->auditLogger->log(
                $actor,
                $member,
                'project.member.added',
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => null,
                ],
                [
                    'project_id' => $project->getKey(),
                    'user_id' => $member->getKey(),
                    'project_role_id' => $role->getKey(),
                    'project_role_code' => $role->code,
                ],
                $reason,
            );

            if ($this->isCanonicalAdminRole($role)) {
                $this->rules->maybeAssignOwnershipAutomatically($actor, $project, $member, $reason);
            }

            $result = $project;
        });

        return $result;
    }

    private function isCanonicalAdminRole(Role $role): bool
    {
        return $this->rules->canonicalAdminRole()->getKey() === $role->getKey();
    }
}
