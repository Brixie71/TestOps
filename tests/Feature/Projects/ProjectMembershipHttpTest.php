<?php

namespace Tests\Feature\Projects;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMembershipHttpTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Membership roster maintenance';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_can_add_a_member(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->actingAs($actor)
            ->from(route('dashboard'))
            ->post(route('projects.members.add', $project), [
                'user_id' => $member->id,
                'role_id' => $role->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.member.added',
            'actor_user_id' => $actor->id,
            'target_user_id' => $member->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_super_admin_can_change_a_member_role(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $newRole = $this->projectRole('PROJECT_ADMIN');

        $this->actingAs($actor)
            ->from(route('dashboard'))
            ->post(route('projects.members.change-role', [$project, $member]), [
                'role_id' => $newRole->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_id' => $newRole->id,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'project.member.role_changed')
            ->where('actor_user_id', $actor->id)
            ->where('target_user_id', $member->id)
            ->firstOrFail();

        $this->assertSame($this->projectRole('PROJECT_VIEWER')->id, $audit->before_values['project_role_id']);
        $this->assertSame($newRole->id, $audit->after_values['project_role_id']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_super_admin_can_remove_a_member(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->actingAs($actor)
            ->from(route('dashboard'))
            ->post(route('projects.members.remove', [$project, $member]), [
                'reason' => self::REASON,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.member.removed',
            'actor_user_id' => $actor->id,
            'target_user_id' => $member->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_system_admin_with_the_membership_permission_can_operate_across_projects(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $projectA = Project::factory()->withoutOwner()->create();
        $projectB = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.members.add', $projectA), [
                'user_id' => $member->id,
                'role_id' => $role->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('projects.members.add', $projectB), [
                'user_id' => $member->id,
                'role_id' => $role->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', ['project_id' => $projectA->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('project_user', ['project_id' => $projectB->id, 'user_id' => $member->id]);
    }

    public function test_system_admin_without_the_membership_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => $member->id,
                'role_id' => $role->id,
                'reason' => self::REASON,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('project_user', ['user_id' => $member->id]);
    }

    public function test_project_admin_can_add_members_within_their_project(): void
    {
        // Membership authorization must stay with ProjectPolicy inside the
        // actions: a project-scoped user without any system-scope grant
        // would be denied by the permission middleware, which is why no
        // `permission:*` middleware guards membership routes.
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->createProjectAdmin($project);

        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $this->actingAs($admin)
            ->from(route('dashboard'))
            ->post(route('projects.members.add', $project), [
                'user_id' => $member->id,
                'role_id' => $role->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_project_admin_cannot_operate_across_projects(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->createProjectAdmin($project);

        $otherProject = Project::factory()->withoutOwner()->create();
        $outsiderMember = User::factory()->create();
        $otherProject->members()->attach(
            $outsiderMember->id,
            ['role_id' => $this->projectRole('PROJECT_VIEWER')->id],
        );

        $this->actingAs($admin)
            ->post(route('projects.members.change-role', [$otherProject, $outsiderMember]), [
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $this->post(route('projects.members.add', $project), [
            'user_id' => 1,
            'role_id' => 1,
            'reason' => self::REASON,
        ])->assertRedirect(route('login'));
    }

    public function test_deactivated_actors_are_rejected_by_active_account_middleware(): void
    {
        $actor = $this->createSuperAdmin();
        $actor->forceFill(['account_status' => 'invited'])->save();

        $project = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => User::factory()->create()->id,
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertRedirect('/login');
    }

    public function test_missing_or_invalid_request_fields_produce_validation_errors(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => User::factory()->create()->id,
                'role_id' => 999999,
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('role_id');

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => User::factory()->create()->id,
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_whitespace_and_overlong_reasons_surface_the_action_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => $member->id,
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => '   ',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($actor)
            ->post(route('projects.members.add', $project), [
                'user_id' => $member->id,
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_duplicate_membership_surfaces_the_action_validation_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $role = $this->projectRole('PROJECT_VIEWER');

        $payload = [
            'user_id' => $member->id,
            'role_id' => $role->id,
            'reason' => self::REASON,
        ];

        $this->actingAs($actor)->post(route('projects.members.add', $project), $payload)->assertRedirect();

        $this->actingAs($actor)->post(route('projects.members.add', $project), $payload)
            ->assertSessionHasErrors('user');
    }

    public function test_owner_role_change_is_restricted_and_surfaces_the_action_error(): void
    {
        $project = Project::factory()->create();
        $actor = $this->createSuperAdmin();
        $owner = $project->owner;

        $this->assertNotNull($owner);
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->actingAs($actor)
            ->post(route('projects.members.change-role', [$project, $owner]), [
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_owner_removal_is_restricted_and_surfaces_the_action_error(): void
    {
        $project = Project::factory()->create();
        $actor = $this->createSuperAdmin();
        $owner = $project->owner;
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->actingAs($actor)
            ->post(route('projects.members.remove', [$project, $owner]), [
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_last_project_admin_downgrade_surfaces_the_invariant_error(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->createSuperAdmin();

        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->actingAs($actor)
            ->post(route('projects.members.change-role', [$project, $admin]), [
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);
    }

    public function test_soft_deleted_member_removal_surfaces_the_explicit_rejection(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $member->delete();

        $this->actingAs($actor)
            ->post(route('projects.members.remove', [$project, $member]), [
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_missing_member_binding_returns_not_found(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.members.change-role', [$project, 999999]), [
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
                'reason' => self::REASON,
            ])
            ->assertNotFound();
    }

    /**
     * The canonical SUPER_ADMIN system role with an acting user.
     */
    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN', 'scope' => 'system'],
            ['name' => 'Super Admin'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A SYSTEM_ADMIN user; with permission attaches the seeded project
     * membership permissions, without permission detaches them from the
     * role (the ProjectAuthorizationPermissionSeeder grants them by default).
     */
    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN', 'scope' => 'system'],
            ['name' => 'System Admin'],
        );

        $codes = ['projects.manage-members', 'projects.manage-ownership'];

        if ($withPermission) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('code', $codes)->pluck('id')->all(),
            );
        } else {
            $role->permissions()->detach(
                Permission::query()->whereIn('code', $codes)->pluck('id')->all(),
            );
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A PROJECT_ADMIN member of the given project with the canonical
     * project-scoped role and no system role.
     */
    private function createProjectAdmin(Project $project): User
    {
        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return $admin;
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
