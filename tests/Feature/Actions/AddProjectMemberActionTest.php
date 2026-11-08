<?php

namespace Tests\Feature\Actions;

use App\Actions\AddProjectMemberAction;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AddProjectMemberActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Onboarding for the release milestone';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_adds_member_with_explicit_project_role_and_audits(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->action()->execute($actor, $project, $member, $role->id, self::REASON);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $role->id,
        ]);

        $this->assertAuditRow('project.member.added', $actor->id, $member->id, [
            'project_id' => $project->id,
            'project_role_id' => $role->id,
            'project_role_code' => $role->code,
        ]);

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_system_admin_adds_members_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $projectA = Project::factory()->withoutOwner()->create();
        $projectB = Project::factory()->withoutOwner()->create();

        $action = $this->action();

        $action->execute($actor, $projectA, $member, $role->id, self::REASON);
        $action->execute($actor, $projectB, $member, $role->id, self::REASON);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $projectA->id,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $projectB->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_system_admin_without_membership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $role = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $role->permissions()->detach();
        $this->assignSystemRole($actor, $role);

        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
    }

    public function test_project_admin_with_both_permissions_adds_member_within_boundary(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->projectAdminMember($project);

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->action()->execute($admin, $project, $member, $role->id, self::REASON);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_project_admin_with_only_membership_permission_is_denied(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $admin = $this->projectAdminMember(
            $project,
            ['projects.manage-members'],
            'PROJECT_MEMBERSHIP_ONLY',
        );

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, $member, $role->id, self::REASON);
    }

    public function test_project_admin_with_only_role_assignment_permission_is_denied(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $admin = $this->projectAdminMember(
            $project,
            ['projects.assign-roles'],
            'PROJECT_ASSIGN_ONLY',
        );

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, $member, $role->id, self::REASON);
    }

    public function test_invited_account_is_ineligible(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create(['account_status' => 'invited']);
        $role = $this->projectRole('PROJECT_VIEWER');

        try {
            $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
            $this->fail('Expected a validation error for the invited account.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['user' => ['Only active accounts may be added to projects or receive project roles.']],
                $exception->errors(),
            );
        }

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_soft_deleted_account_is_ineligible(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();
        $member->delete();

        $role = $this->projectRole('PROJECT_VIEWER');

        try {
            $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
            $this->fail('Expected a validation error for the soft-deleted account.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['user' => ['Soft-deleted accounts are ineligible for project membership.']],
                $exception->errors(),
            );
        }
    }

    public function test_system_scoped_roles_are_never_assigned(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();

        $systemRole = Role::query()->where('code', 'SUPER_ADMIN')->where('scope', 'system')->firstOrFail();

        try {
            $this->action()->execute($actor, $project, $member, $systemRole->id, self::REASON);
            $this->fail('Expected a validation error for the system-scoped role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->action()->execute($actor, $project, $member, $role->id, self::REASON);

        try {
            $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
            $this->fail('Expected a validation error for the duplicate membership.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }
    }

    public function test_missing_actor_fails_closed(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $actor->forceDelete();

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
    }

    public function test_soft_deleted_actor_fails_closed(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $actor->delete();

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $member, $role->id, self::REASON);
    }

    public function test_whitespace_and_overlong_reasons_are_rejected(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        foreach (['   ', str_repeat('a', 501)] as $invalidReason) {
            try {
                $this->action()->execute($actor, $project, $member, $role->id, $invalidReason);
                $this->fail('Expected a validation error for the reason.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('reason', $exception->errors());
            }
        }
    }

    public function test_automatic_assignment_fires_for_newly_granted_admin_on_unassigned_project(): void
    {
        $project = Project::factory()->withoutOwner()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $viewer = User::factory()->create();
        $this->action()->execute(
            $actor, $project, $viewer, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
        );

        $this->assertNull($project->fresh()->owner_id);

        $admin = User::factory()->create();
        $this->action()->execute(
            $actor, $project, $admin, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $project->refresh();

        $this->assertSame($admin->id, $project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.assigned',
            'actor_user_id' => $actor->id,
            'target_user_id' => $admin->id,
        ]);
    }

    public function test_no_automatic_assignment_when_setting_is_disabled(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $admin = User::factory()->create();

        $this->action()->execute(
            $actor, $project, $admin, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
        ]);
    }

    public function test_no_assignment_for_non_admin_members_even_when_enabled(): void
    {
        $project = Project::factory()->withoutOwner()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $viewer = User::factory()->create();

        $this->action()->execute(
            $actor, $project, $viewer, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
        );

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
        ]);
    }

    public function test_existing_permanent_owner_is_never_replaced(): void
    {
        $project = Project::factory()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $originalOwner = $project->owner;
        $admin = User::factory()->create();

        $this->action()->execute(
            $actor, $project, $admin, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $project->refresh();

        $this->assertSame($originalOwner->id, $project->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
            'target_user_id' => $admin->id,
        ]);
    }

    public function test_existing_temporary_owner_is_never_replaced(): void
    {
        $project = Project::factory()->withTemporaryOwner()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $originalOwner = $project->owner;
        $admin = User::factory()->create();

        $this->action()->execute(
            $actor, $project, $admin, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $project->refresh();

        $this->assertSame($originalOwner->id, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);
    }

    public function test_audit_failure_rolls_back_membership_and_assignment(): void
    {
        $project = Project::factory()->withoutOwner()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $admin = User::factory()->create();
        $role = $this->projectRole('PROJECT_ADMIN');

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, $admin, $role->id, self::REASON);
            $this->fail('Expected the audit failure to roll the transaction back.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertDatabaseCount('project_user', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    /**
     * @return list<mixed>
     */
    private function action(): AddProjectMemberAction
    {
        return $this->app->make(AddProjectMemberAction::class);
    }

    private function systemUser(string $code): User
    {
        $user = User::factory()->create();
        $this->assignSystemRole($user, Role::query()->where('code', $code)->where('scope', 'system')->firstOrFail());

        return $user;
    }

    private function assignSystemRole(User $user, Role $role): void
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

    private function projectAdminMember(Project $project, ?array $permissionCodes = null, string $roleCode = 'PROJECT_ADMIN'): User
    {
        if ($permissionCodes === null) {
            $role = $this->projectRole('PROJECT_ADMIN');
        } else {
            $role = Role::query()->create([
                'name' => ucwords(str_replace(['.', '_'], ' ', $roleCode)),
                'code' => $roleCode,
                'scope' => 'project',
            ]);

            $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all()
            );
        }

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $role->id]);

        return $admin;
    }

    private function assertAuditRow(string $action, int $actorId, int $targetId, array $values): void
    {
        $audit = AuditLog::query()
            ->where('action', $action)
            ->where('actor_user_id', $actorId)
            ->where('target_user_id', $targetId)
            ->firstOrFail();

        foreach ($values as $key => $value) {
            $this->assertSame($value, $audit->after_values[$key], "after_values.$key");
        }
    }
}
