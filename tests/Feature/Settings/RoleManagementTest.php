<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_create_a_role(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->post(route('settings.roles.store'), [
                'name' => 'Quality Manager',
                'code' => 'QUALITY_MANAGER',
                'scope' => 'system',
                'description' => 'Owns quality reporting.',
            ])
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHas('status', 'Role created.');

        $role = Role::query()->where('code', 'QUALITY_MANAGER')->sole();

        $this->assertSame('Quality Manager', $role->name);
        $this->assertSame('system', $role->scope);
        $this->assertSame([], $role->permissions()->pluck('code')->all());

        $audit = AuditLog::query()->where('action', 'role.created')->sole();

        $this->assertSame($actor->getKey(), $audit->actor_user_id);
        $this->assertSame('QUALITY_MANAGER', $audit->after_values['code']);
    }

    public function test_role_codes_must_be_unique_and_uppercase(): void
    {
        $actor = $this->createSuperAdmin();

        Role::query()->create([
            'name' => 'Existing',
            'code' => 'EXISTING_ROLE',
            'scope' => 'system',
        ]);

        $this->actingAs($actor)
            ->post(route('settings.roles.store'), [
                'name' => 'Duplicate',
                'code' => 'EXISTING_ROLE',
                'scope' => 'system',
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($actor)
            ->post(route('settings.roles.store'), [
                'name' => 'Lowercase',
                'code' => 'lowercase_role',
                'scope' => 'system',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('roles', ['name' => 'Duplicate']);
        $this->assertDatabaseMissing('roles', ['name' => 'Lowercase']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_super_admin_can_rename_a_role_and_reassign_its_permissions(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
            'description' => 'Manages one project.',
        ]);

        $viewPermission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);
        $updatePermission = Permission::query()->create([
            'name' => 'Update Projects',
            'code' => 'projects.update',
        ]);

        $role->permissions()->attach($viewPermission);

        $this->actingAs($actor)
            ->patch(route('settings.roles.update', $role->getKey()), [
                'name' => 'Project Owner',
                'description' => 'Owns one project.',
                'permissions' => [$updatePermission->getKey()],
                'code' => 'CHANGED_CODE',
                'scope' => 'system',
            ])
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHas('status', 'Role updated.');

        $role->refresh();

        $this->assertSame('Project Owner', $role->name);
        $this->assertSame('Owns one project.', $role->description);
        $this->assertSame('PROJECT_ADMIN', $role->code);
        $this->assertSame('project', $role->scope);
        $this->assertSame(['projects.update'], $role->permissions()->pluck('code')->all());

        $audit = AuditLog::query()->where('action', 'role.updated')->sole();

        $this->assertSame(['projects.view'], $audit->before_values['permissions']);
        $this->assertSame(['projects.update'], $audit->after_values['permissions']);
    }

    public function test_the_super_admin_role_cannot_be_modified(): void
    {
        $actor = $this->createSuperAdmin();

        $role = $actor->role;

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.roles.update', $role->getKey()), [
                'name' => 'Renamed',
                'permissions' => [$permission->getKey()],
            ])
            ->assertForbidden();

        $role->refresh();

        $this->assertSame('Super Admin', $role->name);
        $this->assertSame([], $role->permissions()->pluck('code')->all());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_super_admin_role_cannot_be_opened_for_editing(): void
    {
        $actor = $this->createSuperAdmin();

        $role = $actor->role;

        $this->actingAs($actor)
            ->get(route('settings.roles', ['edit' => $role->getKey()]))
            ->assertOk()
            ->assertSee('Protected')
            ->assertDontSee(route('settings.roles.update', $role->getKey()));
    }

    public function test_a_system_admin_cannot_create_or_modify_roles(): void
    {
        $actor = $this->createSystemAdmin();

        $role = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $this->actingAs($actor)
            ->post(route('settings.roles.store'), [
                'name' => 'Quality Manager',
                'code' => 'QUALITY_MANAGER',
                'scope' => 'system',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->patch(route('settings.roles.update', $role->getKey()), [
                'name' => 'Renamed',
            ])
            ->assertForbidden();

        $this->assertSame('Project Admin', $role->refresh()->name);
        $this->assertDatabaseMissing('roles', ['code' => 'QUALITY_MANAGER']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $role = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $this->post(route('settings.roles.store'), [
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
        ])->assertRedirect(route('login'));

        $this->patch(route('settings.roles.update', $role->getKey()), [
            'name' => 'Renamed',
        ])->assertRedirect(route('login'));
    }

    public function test_a_super_admin_can_delete_an_unused_custom_role(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
            'description' => 'Owns quality reporting.',
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $role->permissions()->attach($permission);

        $this->actingAs($actor)
            ->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHas('status', 'Role deleted.');

        $this->assertDatabaseMissing('roles', ['code' => 'QUALITY_MANAGER']);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->getKey()]);

        $audit = AuditLog::query()->where('action', 'role.deleted')->sole();

        $this->assertSame($actor->getKey(), $audit->actor_user_id);
        $this->assertSame('QUALITY_MANAGER', $audit->before_values['code']);
        $this->assertSame(['projects.view'], $audit->before_values['permissions']);
        $this->assertNull($audit->after_values);
    }

    public function test_a_role_assigned_to_an_account_cannot_be_deleted(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
        ]);

        User::factory()->create(['role_id' => $role->getKey()]);

        $this->actingAs($actor)
            ->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['code' => 'QUALITY_MANAGER']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_role_assigned_only_to_a_deactivated_account_cannot_be_deleted(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
        ]);

        User::factory()->create(['role_id' => $role->getKey()])->delete();

        $this->actingAs($actor)
            ->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['code' => 'QUALITY_MANAGER']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_role_referenced_by_a_project_membership_cannot_be_deleted(): void
    {
        $actor = $this->createSuperAdmin();

        $role = Role::query()->create([
            'name' => 'Project Contributor',
            'code' => 'PROJECT_CONTRIBUTOR',
            'scope' => 'project',
        ]);

        $project = Project::factory()->create();
        $member = User::factory()->create();

        DB::table('project_user')->insert([
            'project_id' => $project->getKey(),
            'user_id' => $member->getKey(),
            'role_id' => $role->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)
            ->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertRedirect(route('settings.roles'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['code' => 'PROJECT_CONTRIBUTOR']);
        $this->assertDatabaseHas('project_user', ['role_id' => $role->getKey()]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_built_in_roles_cannot_be_deleted(): void
    {
        $actor = $this->createSuperAdmin();

        $roles = collect(['SUPER_ADMIN', 'SYSTEM_ADMIN', 'PROJECT_ADMIN'])->map(
            fn (string $code): Role => Role::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'scope' => in_array($code, ['SUPER_ADMIN', 'SYSTEM_ADMIN'], true) ? 'system' : 'project',
                ],
            ),
        );

        foreach ($roles as $role) {
            $this->actingAs($actor)
                ->delete(route('settings.roles.destroy', $role->getKey()))
                ->assertForbidden();

            $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_system_admin_cannot_delete_roles(): void
    {
        $actor = $this->createSystemAdmin();

        $role = Role::query()->create([
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
        ]);

        $this->actingAs($actor)
            ->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['code' => 'QUALITY_MANAGER']);
    }

    public function test_guests_are_redirected_to_login_when_deleting_a_role(): void
    {
        $role = Role::query()->create([
            'name' => 'Quality Manager',
            'code' => 'QUALITY_MANAGER',
            'scope' => 'system',
        ]);

        $this->delete(route('settings.roles.destroy', $role->getKey()))
            ->assertRedirect(route('login'));
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
