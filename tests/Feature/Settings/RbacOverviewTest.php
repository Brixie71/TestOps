<?php

namespace Tests\Feature\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_sees_role_definitions_and_their_permissions(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
            'description' => 'Manages one project.',
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
            'description' => 'Read project records.',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs($actor)
            ->get(route('settings.roles'))
            ->assertOk()
            ->assertSee('Project Admin')
            ->assertSee('PROJECT_ADMIN')
            ->assertSee('projects.view')
            ->assertSee('Manages one project.');
    }

    public function test_a_super_admin_sees_permissions_and_the_roles_that_grant_them(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
            'description' => 'Read project records.',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs($actor)
            ->get(route('settings.permissions'))
            ->assertOk()
            ->assertSee('View Projects')
            ->assertSee('projects.view')
            ->assertSee('PROJECT_ADMIN');
    }

    public function test_a_system_admin_cannot_view_the_rbac_overview(): void
    {
        $actor = $this->createSystemAdmin();

        $this->actingAs($actor)
            ->get(route('settings.roles'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('settings.permissions'))
            ->assertForbidden();
    }

    public function test_an_ordinary_user_cannot_view_the_rbac_overview(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.roles'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('settings.permissions'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('settings.roles'))->assertRedirect(route('login'));
        $this->get(route('settings.permissions'))->assertRedirect(route('login'));
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
