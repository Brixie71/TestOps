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
 * Enable or disable automatic Representative Admin ownership assignment
 * for a project as one transactionally atomic setting change.
 *
 * Only SUPER_ADMIN and SYSTEM_ADMIN (through the system-scope
 * projects.manage-ownership permission) may change the setting. Changing
 * the setting alone never assigns an owner and never replaces one:
 * automatic assignment stays triggered only by the membership actions
 * when a member is newly granted PROJECT_ADMIN on an unowned project.
 * Inside one transaction: the project row is locked, the actor is
 * reloaded, authorization is re-evaluated against current state, the
 * setting is written through forceFill, and the mutation plus its audit
 * record share one transaction.
 */
class ChangeProjectAutoAssignmentSettingAction
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
        bool $enabled,
        string $reason,
    ): Project {
        $reason = $this->rules->requireReason($reason);

        DB::transaction(function () use ($actor, $project, $enabled, $reason, &$result): void {
            // Project-row lock serializes per-project membership and ownership.
            $project = $this->rules->lockReloadProject($project);

            // Fail closed when the actor disappeared or was soft-deleted.
            $actor = $this->rules->reloadActiveActor($actor);

            if (! Gate::forUser($actor)->allows('manageOwnership', $project)) {
                throw new AuthorizationException(
                    'You are not authorized to manage project ownership.'
                );
            }

            // No-op changes are rejected rather than recorded.
            if ($project->auto_assign_representative_owner === $enabled) {
                throw ValidationException::withMessages([
                    'auto_assign_representative_owner' => 'The automatic assignment setting already has this value.',
                ]);
            }

            $previousValue = $project->auto_assign_representative_owner;

            $project->forceFill([
                'auto_assign_representative_owner' => $enabled,
            ])->save();

            $this->auditLogger->log(
                $actor,
                null,
                'project.ownership.auto_assignment_setting_changed',
                [
                    'project_id' => $project->getKey(),
                    'auto_assign_representative_owner' => $previousValue,
                ],
                [
                    'project_id' => $project->getKey(),
                    'auto_assign_representative_owner' => $project->auto_assign_representative_owner,
                ],
                $reason,
            );

            $result = $project;
        });

        return $result;
    }
}
