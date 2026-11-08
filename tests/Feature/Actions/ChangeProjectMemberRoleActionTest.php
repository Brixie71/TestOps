<?php

namespace Tests\Feature\Actions;

use App\Actions\ChangeProjectMemberRoleAction;
use App\Models\AuditLog;
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

class ChangeProjectMemberRoleActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Roster rebalance for QA coverage';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_changes_member_role_and_audits_before_and_after(): void
    {
        [$actor, $project, $member, $fromRole] = $this->memberFixture();

        $toRole = $this->projectRole('PROJECT_ADMIN');

        $this->action()->execute($actor, $project, $member, $toRole->id, self::REASON);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $toRole->id,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'project.member.role_changed')
            ->where('actor_user_id', $actor->id)
            ->where('target_user_id', $member->id)
            ->firstOrFail();

        $this->assertSame($fromRole->id, $audit->before_values['project_role_id']);
        $this->assertSame($fromRole->code, $audit->before_values['project_role_code']);
        $this->assertSame($toRole->id, $audit->after_values['project_role_id']);
        $this->assertSame($toRole->code, $audit->after_values['project_role_code']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_system_admin_changes_roles_across_projects_through_membership_permission(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $this->assignRole($actor, $systemRole);

        $admin = User::factory()->create();
        $project = Project::factory()->withoutOwner()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $otherProject = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $otherProject->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute(
            $actor, $otherProject, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $this->assertDatabaseHas('project_user', [
            'project_id' => $otherProject->id,
            'user_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_system_admin_without_membership_permission_cannot_change_roles(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        [$project, $member] = $this->viewerProjectAndMember();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute(
            $actor, $project, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );
    }

    public function test_project_admin_changes_roles_within_their_membership_boundary(): void
    {
        [$project, $admin] = $this->adminProjectFixture();

        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute(
            $admin, $project, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_project_admin_cannot_change_roles_of_users_outside_their_project_membership(): void
    {
        [$project, $admin] = $this->adminProjectFixture();

        $outsider = User::factory()->create();

        try {
            $this->action()->execute(
                $admin, $project, $outsider, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
            );
            $this->fail('Expected non-member rejection.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['user' => ['The user is not a member of this project.']],
                $exception->errors(),
            );
        }

        $this->assertDatabaseMissing('project_user', [
            'user_id' => $outsider->id,
        ]);
    }

    public function test_project_admin_cannot_change_their_own_role(): void
    {
        [$project, $admin] = $this->adminProjectFixture();

        try {
            $this->action()->execute(
                $admin, $project, $admin, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
            );
            $this->fail('Expected self role change rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }
    }

    public function test_no_op_role_changes_are_rejected(): void
    {
        [$actor, $project, $member] = $this->memberFixture();

        $currentRole = $this->projectRole('PROJECT_VIEWER');

        try {
            $this->action()->execute($actor, $project, $member, $currentRole->id, self::REASON);
            $this->fail('Expected the no-op change to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_designated_owner_role_change_is_rejected(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $project->owner;

        $this->assertNotNull($owner);

        // Factory owners are not pivot members; the invariant target must
        // exist as a member with the canonical admin role first.
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        try {
            $this->action()->execute(
                $actor, $project, $owner, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
            );
            $this->fail('Expected owner role downgrade rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_last_active_project_admin_upgrade_is_protected_from_downgrade(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $member = User::factory()->create();
        $project->members()->attach(
            $member->id,
            ['role_id' => $this->projectRole('PROJECT_ADMIN')->id],
        );

        try {
            $this->action()->execute(
                $actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
            );
            $this->fail('Expected last-admin downgrade rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'PROJECT_ADMIN',
                (string) collect($exception->errors()['role'] ?? [])->implode(' '),
            );
        }
    }

    public function test_downgrade_succeeds_when_another_active_admin_remains(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $remainingAdmin = User::factory()->create();
        $project->members()->attach(
            $remainingAdmin->id,
            ['role_id' => $this->projectRole('PROJECT_ADMIN')->id],
        );

        $member = User::factory()->create();
        $project->members()->attach(
            $member->id,
            ['role_id' => $this->projectRole('PROJECT_ADMIN')->id],
        );

        $this->action()->execute(
            $actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
        );

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $remainingAdmin->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_system_scoped_target_role_rejected(): void
    {
        $actor = $this->systemUser('SUPER_ADMIN');
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $this->projectRole('PROJECT_VIEWER');

        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $systemRole = Role::query()->where('code', 'SUPER_ADMIN')->where('scope', 'system')->firstOrFail();

        try {
            $this->action()->execute($actor, $project, $member, $systemRole->id, self::REASON);
            $this->fail('Expected system scope rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_non_member_target_rejected(): void
    {
        $actor = $this->systemUser('SUPER_ADMIN');
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();

        try {
            $this->action()->execute(
                $actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON,
            );
            $this->fail('Expected non-member rejection.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['user' => ['The user is not a member of this project.']],
                $exception->errors(),
            );
        }
    }

    public function test_role_change_granting_project_admin_triggers_automatic_assignment(): void
    {
        $project = Project::factory()->withoutOwner()->withAutoAssignment()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute(
            $actor, $project, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $project->refresh();

        $this->assertSame($member->id, $project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.assigned',
            'target_user_id' => $member->id,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function test_role_change_does_not_assign_ownership_when_disabled_or_owner_exists(): void
    {
        $disabled = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $member = User::factory()->create();
        $disabled->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute(
            $actor, $disabled, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $this->assertNull($disabled->fresh()->owner_id);

        $owned = Project::factory()->withAutoAssignment()->create();
        $originalOwner = $owned->owner;

        $otherMember = User::factory()->create();
        $owned->members()->attach($otherMember->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute(
            $actor, $owned, $otherMember, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
        );

        $owned->refresh();

        $this->assertSame($originalOwner->id, $owned->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
            'target_user_id' => $otherMember->id,
        ]);
    }

    public function test_audit_failure_rolls_back_role_change(): void
    {
        [$actor, $project, $member] = $this->memberFixture();

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute(
                $actor, $project, $member, $this->projectRole('PROJECT_ADMIN')->id, self::REASON,
            );
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    /**
     * A project with a PROJECT_ADMIN member who acts as the actor. The
     * admin holds no system role, so member-level rules apply to it.
     *
     * @return array{0: Project, 1: User}
     */
    private function adminProjectFixture(): array
    {
        $project = Project::factory()->withoutOwner()->create();

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return [$project, $admin];
    }

    /**
     * A SUPER_ADMIN actor, a project, and a member attached with the
     * canonical PROJECT_VIEWER role.
     */
    private function memberFixture(): array
    {
        $project = Project::factory()->withoutOwner()->create();

        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();
        $fromRole = $this->projectRole('PROJECT_VIEWER');

        $project->members()->attach($member->id, ['role_id' => $fromRole->id]);

        return [$actor, $project, $member, $fromRole];
    }

    /**
     * @return array{0: Project, 1: User}
     */
    private function viewerProjectAndMember(): array
    {
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        return [$project, $member];
    }

    private function action(): ChangeProjectMemberRoleAction
    {
        return $this->app->make(ChangeProjectMemberRoleAction::class);
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
}
