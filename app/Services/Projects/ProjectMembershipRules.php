<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Shared membership rules for the project membership actions.
 *
 * This service deliberately holds no authorization decisions:
 * authorization lives in ProjectPolicy and is evaluated through the Gate
 * inside each action using freshly reloaded actor and project state.
 */
class ProjectMembershipRules
{
    /**
     * Maximum length of a membership operation reason, shared with HTTP
     * validation when the HTTP slice lands.
     */
    private const MAX_REASON_LENGTH = 500;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Require a trimmed, non-empty reason with a maximum length.
     */
    public function requireReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for this operation.',
            ]);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => 'The reason may not exceed 500 characters.',
            ]);
        }

        return $reason;
    }

    /**
     * Acquire the project row lock and reload the project: the serialization
     * root for per-project membership and ownership changes.
     *
     * @throws ValidationException when the project no longer exists
     */
    public function lockReloadProject(Project $project): Project
    {
        $locked = Project::query()
            ->lockForUpdate()
            ->find($project->getKey());

        if ($locked === null) {
            throw ValidationException::withMessages([
                'project' => 'The project no longer exists.',
            ]);
        }

        return $locked;
    }

    /**
     * Reload the acting user while the project-row lock is held; fail
     * closed when the actor was permanently deleted or soft-deleted since
     * the request started.
     *
     * @throws AuthorizationException when the actor is missing or soft-deleted
     */
    public function reloadActiveActor(User $actor): User
    {
        $reloaded = User::query()->withTrashed()->find($actor->getKey());

        if (
            $reloaded === null
            || $reloaded->trashed()
        ) {
            throw new AuthorizationException(
                'You are not authorized to manage project memberships.'
            );
        }

        return $reloaded;
    }

    /**
     * Reload the target user, explicitly preserving soft-delete state so
     * eligibility rules can detect and reject soft-deleted accounts.
     *
     * @throws ValidationException when the user no longer exists at all
     */
    public function reloadTargetUser(User $user): User
    {
        $reloaded = User::query()->withTrashed()->find($user->getKey());

        if ($reloaded === null) {
            throw ValidationException::withMessages([
                'user' => 'The user no longer exists.',
            ]);
        }

        return $reloaded;
    }

    /**
     * Resolve the explicit canonically project-scoped role to assign.
     * System-scoped roles are never assignable through project membership.
     *
     * @throws ValidationException when the role is missing or system-scoped
     */
    public function resolveProjectRole(int $roleId): Role
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'The selected project role does not exist.',
            ]);
        }

        if ($role->scope !== 'project') {
            throw ValidationException::withMessages([
                'role' => 'Only existing project-scoped roles may be assigned through project membership.',
            ]);
        }

        return $role;
    }

    /**
     * Only active, non-soft-deleted accounts may become project members or
     * receive project roles.
     *
     * @throws ValidationException for ineligible accounts
     */
    public function assertMemberEligible(User $target): void
    {
        if ($target->trashed()) {
            throw ValidationException::withMessages([
                'user' => 'Soft-deleted accounts are ineligible for project membership.',
            ]);
        }

        if ($target->account_status !== 'active') {
            throw ValidationException::withMessages([
                'user' => 'Only active accounts may be added to projects or receive project roles.',
            ]);
        }
    }

    /**
     * @throws ValidationException when the user is already a member
     */
    public function assertNotAlreadyMember(Project $project, User $target): void
    {
        $exists = DB::table('project_user')
            ->where('project_id', $project->getKey())
            ->where('user_id', $target->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user' => 'The user is already a member of this project.',
            ]);
        }
    }

    /**
     * The current project-scoped membership role of the target within the
     * project, or null when not a member.
     */
    public function currentMembershipRole(Project $project, User $target): ?Role
    {
        /** @var Model|null $membership */
        $membership = $project->members()
            ->whereKey($target->getKey())
            ->first();

        if ($membership === null) {
            return null;
        }

        /** @var Role|null $role */
        $role = Role::query()->find($membership->pivot->role_id);

        return $role;
    }

    /**
     * Number of active (non-soft-deleted) PROJECT_ADMIN members of the
     * project.
     *
     * @throws RuntimeException when the canonical PROJECT_ADMIN project role is missing
     */
    public function countActiveAdminMembers(Project $project): int
    {
        $adminRole = $this->canonicalAdminRole();

        $activeAdmins = DB::table('project_user')
            ->join('users', 'users.id', '=', 'project_user.user_id')
            ->where('project_user.project_id', $project->getKey())
            ->where('project_user.role_id', $adminRole->getKey())
            ->whereNull('users.deleted_at')
            ->where('users.account_status', 'active')
            ->count();

        return (int) $activeAdmins;
    }

    /**
     * The canonical PROJECT_ADMIN project role; fail closed when seeding
     * has not run because the last-admin invariants cannot be evaluated.
     *
     * @throws RuntimeException when the canonical project role is missing
     */
    public function canonicalAdminRole(): Role
    {
        $adminRole = Role::query()
            ->where('code', 'PROJECT_ADMIN')
            ->where('scope', 'project')
            ->first();

        if ($adminRole === null) {
            throw new RuntimeException(
                'The canonical PROJECT_ADMIN project role is missing, so project membership invariants cannot be enforced. Run ProjectRoleSeeder before performing project membership changes.'
            );
        }

        return $adminRole;
    }

    /**
     * The schema pairing constraint only allows these assignment types for
     * a designated owner; anything else is rejected before mutating state.
     *
     * @throws ValidationException for unknown assignment types
     */
    public function assertAssignmentType(string $assignmentType): void
    {
        if (! in_array($assignmentType, ['permanent', 'temporary'], true)) {
            throw ValidationException::withMessages([
                'assignment_type' => 'The assignment type must be permanent or temporary.',
            ]);
        }
    }

    /**
     * Eligibility for becoming or remaining the designated project owner:
     * an active, non-soft-deleted project member holding the canonical
     * project-scoped PROJECT_ADMIN role.
     *
     * @throws ValidationException for ineligible targets
     */
    public function assertEligibleOwner(Project $project, User $target): void
    {
        $this->assertMemberEligible($target);

        $role = $this->currentMembershipRole($project, $target);

        if (
            $role === null
            || ! $this->canonicalAdminRole()->is($role)
        ) {
            throw ValidationException::withMessages([
                'user' => 'Designated project owners must be active project members holding the PROJECT_ADMIN project role.',
            ]);
        }
    }

    /**
     * Automatic Representative Admin ownership assignment.
     *
     * Fires only when a member is newly granted PROJECT_ADMIN while the
     * project has no designated owner, no assignment type, and the
     * per-project setting is enabled. Existing owners (permanent or
     * temporary) are never replaced; the setting being enabled alone never
     * assigns an owner. Returns whether an assignment happened.
     */
    public function maybeAssignOwnershipAutomatically(
        User $actor,
        Project $project,
        User $newAdmin,
        string $reason,
    ): bool {
        $project->refresh();

        if (
            ! $project->auto_assign_representative_owner
            || $project->owner_id !== null
            || $project->owner_assignment_type !== null
        ) {
            return false;
        }

        $beforeValues = [
            'project_id' => $project->getKey(),
            'owner_id' => null,
            'owner_assignment_type' => null,
        ];

        $project->forceFill([
            'owner_id' => $newAdmin->getKey(),
            'owner_assignment_type' => 'permanent',
        ])->save();

        $this->auditLogger->log(
            $actor,
            $newAdmin,
            'project.ownership.assigned',
            $beforeValues,
            [
                'project_id' => $project->getKey(),
                'owner_id' => $project->owner_id,
                'owner_assignment_type' => $project->owner_assignment_type,
                'transfer_type' => 'automatic',
            ],
            $reason,
        );

        return true;
    }
}
