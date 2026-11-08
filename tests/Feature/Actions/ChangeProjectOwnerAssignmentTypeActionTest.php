<?php

namespace Tests\Feature\Actions;

use App\Actions\ChangeProjectOwnerAssignmentTypeAction;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ChangeProjectOwnerAssignmentTypeActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Representation converted for the interim phase';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_permanent_owner_changes_to_temporary_with_the_owner_unchanged(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $project->owner;
        $previousOwnerId = $project->owner_id;

        // Factory owners are not pivot members; the eligible-owner invariant
        // requires the designated owner to be a member with the canonical
        // admin role.
        $project->members()->attach($previousOwnerId, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $project, 'temporary', self::REASON);

        $project->refresh();

        $this->assertSame($previousOwnerId, $project->owner_id);
        $this->assertSame($owner->id, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);

        $audit = $this->auditRow('project.ownership.assignment_type_changed', $actor->id, $owner->id);

        $this->assertSame('permanent', $audit->before_values['owner_assignment_type']);
        $this->assertSame('temporary', $audit->after_values['owner_assignment_type']);
        $this->assertSame($previousOwnerId, $audit->after_values['owner_id']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_temporary_owner_changes_to_permanent(): void
    {
        $project = Project::factory()->withTemporaryOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $project->members()->attach($project->owner_id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $project, 'permanent', self::REASON);

        $project->refresh();

        $this->assertSame('permanent', $project->owner_assignment_type);
        $this->assertNotNull($project->owner_id);
    }

    public function test_system_admin_changes_assignment_types_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');
        $project = Project::factory()->create();
        $project->members()->attach($project->owner_id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $project, 'temporary', self::REASON);

        $this->assertSame('temporary', $project->fresh()->owner_assignment_type);
    }

    public function test_system_admin_without_ownership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        $project = Project::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, 'temporary', self::REASON);
    }

    public function test_project_admin_is_never_authorized_to_change_assignment_types(): void
    {
        $project = Project::factory()->create();
        $admin = $this->attachAdminMember($project);

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, 'temporary', self::REASON);
    }

    public function test_change_on_an_unowned_project_is_rejected(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected rejection on the unowned project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner', $exception->errors());
        }
    }

    public function test_no_op_type_change_is_rejected(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        try {
            $this->action()->execute($actor, $project, 'permanent', self::REASON);
            $this->fail('Expected no-op rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment_type', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assignment_type_changed',
        ]);
    }

    public function test_change_is_rejected_when_the_owner_is_no_longer_a_member(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $project->members()->detach($project->owner_id);

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected rejection when the owner lost membership.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_change_is_rejected_when_the_owner_role_was_downgraded(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $project->members()->updateExistingPivot(
            $project->owner_id,
            ['role_id' => $this->projectRole('PROJECT_VIEWER')->id],
        );

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected rejection when the owner role was downgraded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_change_is_rejected_when_the_owner_is_soft_deleted(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        User::query()->withTrashed()->find($project->owner_id)->delete();

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected rejection when the owner was soft-deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_change_is_rejected_when_the_owner_is_invited(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $project->members()->attach($project->owner_id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        User::query()->find($project->owner_id)
            ->forceFill(['account_status' => 'invited'])
            ->save();

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected rejection when the owner became invited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_designated_owner_cannot_be_force_deleted_by_schema()
    {
        // The owner_id foreign key is restrictive, so the "owner no longer
        // exists" state is prevented by the schema rather than the action;
        // the action's missing-owner guard remains defense in depth.
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        User::query()->withTrashed()->find($project->owner_id)->forceDelete();
    }

    public function test_invalid_assignment_types_are_rejected(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        try {
            $this->action()->execute($actor, $project, 'forever', self::REASON);
            $this->fail('Expected rejection for the assignment type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment_type', $exception->errors());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_audit_failure_rolls_back_the_type_change(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $project->members()->attach($project->owner_id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, 'temporary', self::REASON);
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    private function attachAdminMember(Project $project): User
    {
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return $member;
    }

    private function action(): ChangeProjectOwnerAssignmentTypeAction
    {
        return $this->app->make(ChangeProjectOwnerAssignmentTypeAction::class);
    }

    private function systemUser(string $code): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('code', $code)->where('scope', 'system')->firstOrFail();
        $user->role()->associate($role);
        $user->save();

        return $user;
    }

    private function assignRole(User $user, Role $role): void
    {
        $user->role()->associate($role);
        $user->save();
    }

    private function projectRole(string $code): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => 'project'],
            [
                'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                'description' => 'Test helper project role.',
            ],
        );
    }

    private function auditRow(string $action, int $actorId, int $targetId): AuditLog
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('actor_user_id', $actorId)
            ->where('target_user_id', $targetId)
            ->firstOrFail();
    }
}
