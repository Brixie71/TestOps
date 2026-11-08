<?php

namespace Tests\Feature\Actions;

use App\Actions\RemoveProjectMemberAction;
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

class RemoveProjectMemberActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Offboarding after project transition';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_removes_member_and_audits_before_state(): void
    {
        [$actor, $project, $member, $memberRole] = $this->memberFixture();

        $this->action()->execute($actor, $project, $member, self::REASON);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'project.member.removed')
            ->where('actor_user_id', $actor->id)
            ->where('target_user_id', $member->id)
            ->firstOrFail();

        $this->assertSame($project->id, $audit->before_values['project_id']);
        $this->assertSame($member->id, $audit->before_values['user_id']);
        $this->assertSame($memberRole->id, $audit->before_values['project_role_id']);
        $this->assertSame($memberRole->code, $audit->before_values['project_role_code']);
        $this->assertNull($audit->after_values['project_role_id']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_system_admin_removes_members_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');

        $projectA = Project::factory()->withoutOwner()->create();
        $projectB = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();

        $projectA->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);
        $projectB->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $action = $this->action();

        $action->execute($actor, $projectA, $member, self::REASON);
        $action->execute($actor, $projectB, $member, self::REASON);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $projectA->id,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $projectB->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_system_admin_without_membership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        [$project, $member] = $this->viewerProjectAndMember();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $member, self::REASON);
    }

    public function test_project_admin_removes_members_within_their_boundary(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->action()->execute($admin, $project, $member, self::REASON);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_designated_owner_cannot_be_removed(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $project->owner;

        $this->assertNotNull($owner);

        // Factory owners are not pivot members; the invariant target must
        // exist as a member with the canonical admin role first.
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        try {
            $this->action()->execute($actor, $project, $owner, self::REASON);
            $this->fail('Expected owner removal rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);

        $this->assertSame($owner->id, $project->fresh()->owner_id);
    }

    public function test_last_active_project_admin_cannot_be_removed(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        try {
            $this->action()->execute($actor, $project, $admin, self::REASON);
            $this->fail('Expected last-admin removal rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'PROJECT_ADMIN',
                (string) collect($exception->errors()['user'] ?? [])->implode(' '),
            );
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_removal_succeeds_when_another_active_admin_remains(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $remainingAdmin = User::factory()->create();
        $project->members()->attach(
            $remainingAdmin->id,
            ['role_id' => $this->projectRole('PROJECT_ADMIN')->id],
        );

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $project, $admin, self::REASON);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $remainingAdmin->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_project_admin_may_remove_their_own_membership_when_not_the_owner(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        // A second active admin keeps the last-admin invariant satisfied.
        $remainingAdmin = User::factory()->create();
        $project->members()->attach($remainingAdmin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($admin, $project, $admin, self::REASON);

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $remainingAdmin->id,
        ]);
    }

    public function test_non_admin_member_cannot_remove_their_own_membership(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $memberNoSystemRoleUser = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($memberNoSystemRoleUser, $project, $memberNoSystemRoleUser, self::REASON);
    }

    public function test_non_member_target_is_rejected(): void
    {
        [$actor, $project] = $this->memberFixture();
        $outsider = User::factory()->create();

        try {
            $this->action()->execute($actor, $project, $outsider, self::REASON);
            $this->fail('Expected non-member rejection.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['user' => ['The user is not a member of this project.']],
                $exception->errors(),
            );
        }
    }

    public function test_soft_deleted_member_is_rejected(): void
    {
        [$actor, $project, $member] = $this->memberFixture();

        $member->delete();

        try {
            $this->action()->execute($actor, $project, $member, self::REASON);
            $this->fail('Expected soft-deleted member rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_member_can_be_readded_after_removal(): void
    {
        [$actor, $project, $member] = $this->memberFixture();

        $this->action()->execute($actor, $project, $member, self::REASON);

        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
        ]);
    }

    public function test_audit_failure_rolls_back_removal(): void
    {
        [$actor, $project, $member, $memberRole] = $this->memberFixture();

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, $member, self::REASON);
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    /**
     * A SUPER_ADMIN actor, a project, and a member attached with the
     * canonical PROJECT_VIEWER role.
     */
    private function memberFixture(): array
    {
        [$project, $member] = $this->viewerProjectAndMember();
        $actor = $this->systemUser('SUPER_ADMIN');

        return [$actor, $project, $member, $this->projectRole('PROJECT_VIEWER')];
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

    private function action(): RemoveProjectMemberAction
    {
        return $this->app->make(RemoveProjectMemberAction::class);
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
