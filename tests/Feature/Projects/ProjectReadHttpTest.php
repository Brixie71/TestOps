<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-page feature tests: server-side access and rendering, never merely
 * button visibility. The directory scope and the show() authorization gate
 * are the behavior under test; the $can flags gate UI affordances only.
 */
class ProjectReadHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    // ------------------------------------------------------------------
    // Projects directory (projects.index)
    // ------------------------------------------------------------------

    public function test_guests_are_redirected_to_login_from_the_projects_directory(): void
    {
        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_deactivated_accounts_are_redirected_to_login_from_the_projects_directory(): void
    {
        $actor = $this->createProjectAdminMember();

        $actor->forceFill(['account_status' => 'invited'])->save();
        $actor->refresh();

        $this->actingAs($actor)
            ->get(route('projects.index'))
            ->assertRedirect('/login');
    }

    public function test_super_admin_sees_all_projects_including_non_member_projects(): void
    {
        $actor = $this->createSuperAdmin();

        $ownedByOthers = Project::factory()->create();
        $unowned = Project::factory()->withoutOwner()->create();

        $this->actingAs($actor)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($ownedByOthers->name)
            ->assertSee($unowned->name);
    }

    public function test_regular_users_see_only_member_projects_they_can_view(): void
    {
        $ownProject = Project::factory()->create();
        $actorMember = $this->createProjectAdmin($ownProject);

        $othersProject = Project::factory()->create();
        $unownedProject = Project::factory()->withoutOwner()->create();

        $this->actingAs($actorMember)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($ownProject->name)
            ->assertDontSee($othersProject->name)
            ->assertDontSee($unownedProject->name);
    }

    public function test_members_without_the_view_permission_are_not_listed_and_are_forbidden_from_details(): void
    {
        $project = Project::factory()->create();

        $viewer = User::factory()->create();
        $project->members()->attach($viewer->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        $this->actingAs($viewer)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee($project->name);

        $this->actingAs($viewer)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_non_members_are_forbidden_from_project_details(): void
    {
        $project = Project::factory()->create();
        $outsider = $this->createProjectAdminMember();

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_deactivated_owner_renders_as_deactivated_in_the_directory(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $project->owner->delete();

        $this->actingAs($actor)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($project->owner->name)
            ->assertSee('Deactivated')
            ->assertDontSee('No Admin Assigned');
    }

    public function test_search_matches_the_project_code(): void
    {
        $actor = $this->createSuperAdmin();

        $target = Project::factory()->create(['code' => 'READ-7777']);
        $other = Project::factory()->create();

        $this->actingAs($actor)
            ->get(route('projects.index', ['search' => 'READ-7777']))
            ->assertOk()
            ->assertSee($target->name)
            ->assertDontSee($other->name);
    }

    // ------------------------------------------------------------------
    // Project details (projects.show)
    // ------------------------------------------------------------------

    public function test_guests_are_redirected_to_login_from_project_details(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.show', $project))
            ->assertRedirect(route('login'));
    }

    public function test_deactivated_accounts_are_redirected_to_login_from_project_details(): void
    {
        $project = Project::factory()->create();
        $member = $this->createProjectAdmin($project);

        $member->forceFill(['account_status' => 'invited'])->save();
        $member->refresh();

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertRedirect('/login');
    }

    public function test_members_with_the_view_permission_see_the_project_details_and_role(): void
    {
        $project = Project::factory()->create();
        $member = $this->createProjectAdmin($project);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->name)
            ->assertSee($project->code)
            ->assertSee($project->owner->name)
            ->assertSee($member->name)
            ->assertSee($member->email)
            ->assertSee('Project Admin');
    }

    public function test_deactivated_owner_renders_as_deactivated_in_project_details(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $project->owner->delete();

        $this->actingAs($actor)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->owner->name)
            ->assertSee('Deactivated')
            ->assertSee('Retained')
            ->assertDontSee('No Admin Assigned')
            ->assertDontSee('No admin assigned');
    }

    public function test_ownership_badges_render_the_semantic_labels(): void
    {
        $actor = $this->createSuperAdmin();

        $permanent = Project::factory()->create();
        $temporary = Project::factory()->withTemporaryOwner()->create();

        $this->actingAs($actor)
            ->get(route('projects.show', $permanent))
            ->assertOk()
            ->assertSee('Assigned Administrator');

        $this->actingAs($actor)
            ->get(route('projects.show', $temporary))
            ->assertOk()
            ->assertSee('Temporary Admin')
            ->assertSee('Temporary ownership remains in effect until changed');
    }

    public function test_super_admin_sees_membership_and_ownership_controls(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $this->createProjectAdmin($project);
        $outsider = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Add member')
            ->assertSee('Change role')
            ->assertSee('Remove')
            ->assertSee('Transfer owner')
            ->assertSee('Change assignment type')
            ->assertSee('Unassign owner')
            ->assertSee('Automatic assignment')
            // Eligibility mirrors the action-layer rules and surfaces in the
            // add-member dialog options.
            ->assertSee($outsider->name)
            // Eligible owners: active members holding the PROJECT_ADMIN role.
            ->assertSee($project->owner->name)
            ->assertSee('Only active members with the Project Admin role are eligible.');
    }

    public function test_project_admin_sees_membership_controls_but_not_ownership_controls(): void
    {
        $project = Project::factory()->create();
        $admin = $this->createProjectAdmin($project);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Add member')
            ->assertSee('Change role')
            ->assertSee('Remove')
            ->assertDontSee('Transfer owner')
            ->assertDontSee('Change assignment type')
            ->assertDontSee('Unassign owner')
            ->assertDontSee('Automatic assignment');
    }

    // ------------------------------------------------------------------
    // Helpers (same conventions as the mutation HTTP tests)
    // ------------------------------------------------------------------

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN', 'scope' => 'system'],
            ['name' => 'Super Admin'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A member with the canonical PROJECT_ADMIN project role and no
     * system role.
     */
    private function createProjectAdmin(Project $project): User
    {
        $admin = User::factory()->create();
        $project->members()->attach($admin->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return $admin;
    }

    /**
     * A working user who is not a member of any project.
     */
    private function createProjectAdminMember(): User
    {
        return User::factory()->create();
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
