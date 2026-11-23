<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_edit_a_permission_name_and_description(): void
    {
        $actor = $this->createSuperAdmin();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
            'description' => 'Read project records.',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.permissions.update', $permission->getKey()), [
                'name' => 'Browse Projects',
                'description' => 'Read every project the account can reach.',
            ])
            ->assertRedirect(route('settings.permissions'))
            ->assertSessionHas('status', 'Permission updated.');

        $permission->refresh();

        $this->assertSame('Browse Projects', $permission->name);
        $this->assertSame('Read every project the account can reach.', $permission->description);
        $this->assertSame('projects.view', $permission->code);

        $audit = AuditLog::query()->where('action', 'permission.updated')->sole();

        $this->assertSame($actor->getKey(), $audit->actor_user_id);
        $this->assertSame('View Projects', $audit->before_values['name']);
        $this->assertSame('Browse Projects', $audit->after_values['name']);
        $this->assertSame('projects.view', $audit->after_values['code']);
    }

    public function test_a_permission_code_cannot_be_changed_through_the_edit_form(): void
    {
        $actor = $this->createSuperAdmin();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.permissions.update', $permission->getKey()), [
                'name' => 'View Projects',
                'description' => null,
                'code' => 'projects.renamed',
            ])
            ->assertRedirect(route('settings.permissions'));

        $this->assertSame('projects.view', $permission->refresh()->code);
        $this->assertDatabaseMissing('permissions', ['code' => 'projects.renamed']);
    }

    public function test_a_permission_name_is_required(): void
    {
        $actor = $this->createSuperAdmin();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.permissions.update', $permission->getKey()), [
                'name' => '',
                'description' => 'Still described.',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('View Projects', $permission->refresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_system_admin_cannot_edit_permissions(): void
    {
        $actor = $this->createSystemAdmin();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.permissions.update', $permission->getKey()), [
                'name' => 'Browse Projects',
            ])
            ->assertForbidden();

        $this->assertSame('View Projects', $permission->refresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_edit_form_is_preloaded_for_the_selected_permission(): void
    {
        $actor = $this->createSuperAdmin();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
            'description' => 'Read project records.',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.permissions', ['edit' => $permission->getKey()]))
            ->assertOk()
            ->assertSee('Edit permission')
            ->assertSee('projects.view')
            ->assertSee(route('settings.permissions.update', $permission->getKey()));
    }

    public function test_guests_are_redirected_to_login_when_editing_a_permission(): void
    {
        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $this->patch(route('settings.permissions.update', $permission->getKey()), [
            'name' => 'Browse Projects',
        ])->assertRedirect(route('login'));
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
