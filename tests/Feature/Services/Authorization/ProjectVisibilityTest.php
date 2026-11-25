<?php

namespace Tests\Feature\Services\Authorization;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\ProjectVisibility;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_sees_every_project(): void
    {
        Project::factory()->count(3)->create();
        $superAdmin = $this->userWithSystemRole('SUPER_ADMIN');

        $this->assertVisibilityParity($superAdmin);
        $this->assertCount(3, $this->sortedViewableIds($superAdmin));
        $this->assertAbilityParity($superAdmin);
    }

    public function test_system_admin_with_management_grants_sees_every_project(): void
    {
        Project::factory()->count(2)->create();
        $systemAdmin = $this->userWithSystemRole('SYSTEM_ADMIN');

        $this->assertVisibilityParity($systemAdmin);
        $this->assertCount(2, $this->sortedViewableIds($systemAdmin));
        $this->assertAbilityParity($systemAdmin);
    }

    public function test_system_admin_without_management_grants_sees_only_member_projects_with_the_view_permission(): void
    {
        $visible = Project::factory()->create();
        Project::factory()->create();

        $systemAdmin = $this->userWithSystemRole('SYSTEM_ADMIN');
        $this->restrictSystemAdmin($systemAdmin);
        $this->grantSystemPermission($systemAdmin, 'projects.view');
        $this->joinProject($visible, $systemAdmin, 'PROJECT_ADMIN');

        $this->assertVisibilityParity($systemAdmin);
        $this->assertSame([$visible->id], $this->sortedViewableIds($systemAdmin));
        $this->assertAbilityParity($systemAdmin);
    }

    public function test_system_admin_ownership_does_not_bypass_the_missing_view_grant(): void
    {
        $systemAdmin = $this->userWithSystemRole('SYSTEM_ADMIN');
        $project = Project::factory()->create(['owner_id' => $systemAdmin->id]);
        $this->restrictSystemAdmin($systemAdmin);

        $this->assertVisibilityParity($systemAdmin);
        $this->assertSame([], $this->sortedViewableIds($systemAdmin));
        $this->assertFalse(Gate::forUser($systemAdmin)->allows('view', $project));
    }

    public function test_project_owner_sees_owned_projects(): void
    {
        $project = Project::factory()->create();
        Project::factory()->create();

        $this->assertVisibilityParity($project->owner);
        $this->assertSame([$project->id], $this->sortedViewableIds($project->owner));
        $this->assertAbilityParity($project->owner);
    }

    public function test_project_admin_member_sees_projects_through_the_project_role(): void
    {
        $project = Project::factory()->create();
        Project::factory()->create();

        $member = User::factory()->create();
        $this->joinProject($project, $member, 'PROJECT_ADMIN');

        $this->assertVisibilityParity($member);
        $this->assertSame([$project->id], $this->sortedViewableIds($member));
        $this->assertAbilityParity($member);
    }

    public function test_member_with_a_custom_testing_role_sees_no_projects_but_keeps_that_ability(): void
    {
        $project = Project::factory()->create();
        Project::factory()->create();

        $member = User::factory()->create();
        $role = $this->createProjectRole('RUN_VIEWER', ['test_runs.view']);
        $this->joinProjectWithRole($project, $member, $role);

        $this->assertVisibilityParity($member);
        $this->assertSame([], $this->sortedViewableIds($member));
        $this->assertAbilityParity($member);
        $this->assertSame([$project->id], $this->sortedIdsFor($member, 'test_runs.view'));
        $this->assertSame([], $this->sortedIdsFor($member, 'defects.view'));
    }

    public function test_a_user_who_owns_and_belongs_to_nothing_sees_no_projects(): void
    {
        Project::factory()->count(2)->create();
        $user = User::factory()->create();

        $this->assertVisibilityParity($user);
        $this->assertSame([], $this->sortedViewableIds($user));
        $this->assertAbilityParity($user);
    }

    private function assertVisibilityParity(User $user): void
    {
        $expected = Project::query()->get()
            ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $this->sortedViewableIds($user));
    }

    private function assertAbilityParity(User $user): void
    {
        $abilities = [
            'test_cases.view' => 'viewTestCases',
            'test_runs.view' => 'viewTestRuns',
            'defects.view' => 'viewDefects',
        ];

        foreach ($abilities as $code => $ability) {
            $expected = Project::query()->get()
                ->filter(fn (Project $project): bool => Gate::forUser($user)->allows($ability, $project))
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(
                $expected,
                $this->sortedIdsFor($user, $code),
                "Visibility parity failed for {$code}.",
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function sortedViewableIds(User $user): array
    {
        $ids = $this->visibility()->viewableProjects($user)->pluck('id')->all();
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function sortedIdsFor(User $user, string $permissionCode): array
    {
        $ids = $this->visibility()->projectIdsFor($user, $permissionCode)->pluck('id')->all();
        sort($ids);

        return $ids;
    }

    private function visibility(): ProjectVisibility
    {
        return app(ProjectVisibility::class);
    }

    private function userWithSystemRole(string $code): User
    {
        $role = Role::query()
            ->where('code', $code)
            ->where('scope', 'system')
            ->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function restrictSystemAdmin(User $user): void
    {
        $user->role->permissions()->detach();
    }

    private function grantSystemPermission(User $user, string $permissionCode): void
    {
        $permissionId = Permission::query()->where('code', $permissionCode)->value('id');

        $user->role->permissions()->syncWithoutDetaching([$permissionId]);
    }

    private function joinProject(Project $project, User $member, string $roleCode): void
    {
        $this->joinProjectWithRole($project, $member, $this->projectRole($roleCode));
    }

    private function joinProjectWithRole(Project $project, User $member, Role $role): void
    {
        $project->members()->attach($member->id, ['role_id' => $role->id]);
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

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function createProjectRole(string $code, array $permissionCodes): Role
    {
        $role = $this->projectRole($code);

        $role->permissions()->sync(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all(),
        );

        return $role;
    }
}
