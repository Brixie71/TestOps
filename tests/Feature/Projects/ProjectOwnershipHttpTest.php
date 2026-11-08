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

class ProjectOwnershipHttpTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Ownership governance change';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_can_assign_the_first_owner(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $owner = $this->createProjectAdmin($project);

        $this->actingAs($actor)
            ->from(route('dashboard'))
            ->post(route('projects.ownership.assign', $project), [
                'user_id' => $owner->id,
                'assignment_type' => 'permanent',
                'reason' => self::REASON,
            ])
            ->assertRedirect(route('dashboard'));

        $project->refresh();

        $this->assertSame($owner->id, $project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.assigned',
            'actor_user_id' => $actor->id,
            'target_user_id' => $owner->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_super_admin_can_transfer_and_the_previous_owner_keeps_membership_and_role(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $previousOwnerId = $project->owner_id;

        // Factory owners are not pivot members; the transfer invariant test
        // needs the previous owner's membership and role preserved.
        $project->members()->attach($previousOwnerId, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $newOwner = $this->createProjectAdmin($project);

        $this->actingAs($actor)
            ->post(route('projects.ownership.transfer', $project), [
                'user_id' => $newOwner->id,
                'assignment_type' => 'temporary',
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame($newOwner->id, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $previousOwnerId,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'project.ownership.transferred')
            ->where('actor_user_id', $actor->id)
            ->where('target_user_id', $newOwner->id)
            ->firstOrFail();

        $this->assertSame($previousOwnerId, $audit->before_values['owner_id']);
        $this->assertSame($newOwner->id, $audit->after_values['owner_id']);
        $this->assertSame('temporary', $audit->after_values['owner_assignment_type']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_super_admin_can_unassign_and_both_owner_fields_are_cleared(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formerOwnerId = $project->owner_id;

        $this->actingAs($actor)
            ->post(route('projects.ownership.unassign', $project), [
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.unassigned',
            'actor_user_id' => $actor->id,
            'target_user_id' => $formerOwnerId,
            'reason' => self::REASON,
        ]);
    }

    public function test_super_admin_can_change_the_assignment_type(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $ownerId = $project->owner_id;
        $project->members()->attach($ownerId, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->actingAs($actor)
            ->post(route('projects.ownership.change-type', $project), [
                'assignment_type' => 'temporary',
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame($ownerId, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.assignment_type_changed',
            'actor_user_id' => $actor->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_super_admin_can_change_the_auto_assignment_setting_without_assigning_an_owner(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $this->createProjectAdmin($project);

        $this->actingAs($actor)
            ->post(route('projects.ownership.change-auto-assignment', $project), [
                'enabled' => '1',
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $project->refresh();

        $this->assertTrue($project->auto_assign_representative_owner);
        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.ownership.auto_assignment_setting_changed',
            'actor_user_id' => $actor->id,
            'reason' => self::REASON,
        ]);
    }

    public function test_system_admin_with_the_ownership_permission_can_operate(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);

        $assignProject = Project::factory()->withoutOwner()->create();
        $owner = $this->createProjectAdmin($assignProject);

        $this->actingAs($actor)
            ->post(route('projects.ownership.assign', $assignProject), [
                'user_id' => $owner->id,
                'assignment_type' => 'permanent',
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $this->assertSame($owner->id, $assignProject->fresh()->owner_id);

        $unassignProject = Project::factory()->create();

        $this->actingAs($actor)
            ->post(route('projects.ownership.unassign', $unassignProject), [
                'reason' => self::REASON,
            ])
            ->assertRedirect();

        $this->assertNull($unassignProject->fresh()->owner_id);
    }

    public function test_system_admin_without_the_ownership_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);
        $project = Project::factory()->create();

        $this->actingAs($actor)
            ->post(route('projects.ownership.unassign', $project), [
                'reason' => self::REASON,
            ])
            ->assertForbidden();

        $this->assertNotNull($project->fresh()->owner_id);
    }

    public function test_project_admin_cannot_manage_ownership_even_with_project_scoped_permissions(): void
    {
        $project = Project::factory()->create();

        // The canonical PROJECT_ADMIN role holds projects.manage-members and
        // projects.assign-roles; even these project-scoped grants must never
        // reach ownership management, whose authority is system-scope only.
        $admin = $this->createProjectAdmin($project);

        $this->actingAs($admin)
            ->post(route('projects.ownership.unassign', $project), [
                'reason' => self::REASON,
            ])
            ->assertForbidden();

        $this->assertNotNull($project->fresh()->owner_id);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->post(route('projects.ownership.unassign', $project), [
            'reason' => self::REASON,
        ])->assertRedirect(route('login'));
    }

    public function test_deactivated_actors_are_rejected_by_active_account_middleware(): void
    {
        $actor = $this->createSuperAdmin();
        $actor->forceFill(['account_status' => 'invited'])->save();

        $project = Project::factory()->create();

        $this->actingAs($actor)
            ->post(route('projects.ownership.unassign', $project), [
                'reason' => self::REASON,
            ])
            ->assertRedirect('/login');

        $this->assertNotNull($project->fresh()->owner_id);
    }

    public function test_invalid_request_fields_produce_validation_errors(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();
        $owner = $this->createProjectAdmin($project);

        $this->actingAs($actor)
            ->post(route('projects.ownership.assign', $project), [
                'user_id' => $owner->id,
                'assignment_type' => 'forever',
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('assignment_type');

        $this->actingAs($actor)
            ->post(route('projects.ownership.assign', $project), [
                'assignment_type' => 'permanent',
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($actor)
            ->post(route('projects.ownership.change-auto-assignment', $project), [
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('enabled');

        $this->actingAs($actor)
            ->post(route('projects.ownership.assign', $project), [
                'user_id' => $owner->id,
                'assignment_type' => 'permanent',
                'reason' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('reason');

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_assigning_an_already_owned_project_surfaces_the_domain_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $currentOwnerId = $project->owner_id;
        $otherAdmin = $this->createProjectAdmin($project);

        $this->actingAs($actor)
            ->post(route('projects.ownership.assign', $project), [
                'user_id' => $otherAdmin->id,
                'assignment_type' => 'permanent',
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('owner');

        $this->assertSame($currentOwnerId, $project->fresh()->owner_id);
    }

    public function test_transfer_to_the_current_owner_surfaces_the_domain_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $owner = $project->owner;
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->actingAs($actor)
            ->post(route('projects.ownership.transfer', $project), [
                'user_id' => $owner->id,
                'assignment_type' => 'temporary',
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user');

        $this->assertSame($owner->id, $project->fresh()->owner_id);
    }

    public function test_unassigning_an_unowned_project_surfaces_the_domain_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.ownership.unassign', $project), [
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('owner');
    }

    public function test_assignment_type_change_on_an_ineligible_owner_surfaces_the_domain_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $owner = $project->owner;
        $project->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        // The owner loses the canonical PROJECT_ADMIN role but keeps
        // ownership: the type-change safeguard must reject it while
        // transfer and unassignment stay available.
        $project->members()->updateExistingPivot(
            $owner->id,
            ['role_id' => $this->projectRole('PROJECT_VIEWER')->id],
        );

        $this->actingAs($actor)
            ->post(route('projects.ownership.change-type', $project), [
                'assignment_type' => 'temporary',
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('user');

        $this->assertSame('permanent', $project->fresh()->owner_assignment_type);
    }

    public function test_no_op_setting_change_surfaces_the_domain_error(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->post(route('projects.ownership.change-auto-assignment', $project), [
                'enabled' => false,
                'reason' => self::REASON,
            ])
            ->assertSessionHasErrors('auto_assign_representative_owner');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.auto_assignment_setting_changed',
        ]);
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
     * A SYSTEM_ADMIN user; with permission attaches the seeded ownership
     * permission, without permission detaches it from the role (the
     * ProjectAuthorizationPermissionSeeder grants it by default).
     */
    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN', 'scope' => 'system'],
            ['name' => 'System Admin'],
        );

        $permission = Permission::query()->firstOrCreate(
            ['code' => 'projects.manage-ownership'],
            ['name' => 'Manage Project Ownership'],
        );

        if ($withPermission) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } else {
            $role->permissions()->detach([$permission->id]);
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
