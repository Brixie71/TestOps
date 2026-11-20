<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserManagementPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SuperAdmin#Pass1',
        ]);
    }

    private function createSystemAdmin(bool $withProfilePermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $permissions = [
            Permission::query()->firstOrCreate(
                ['code' => 'users.deactivate'],
                ['name' => 'Deactivate User Accounts'],
            )->id,
        ];

        if ($withProfilePermission) {
            $permissions[] = Permission::query()->firstOrCreate(
                ['code' => 'users.profile.manage'],
                ['name' => 'Edit User Profiles'],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($permissions);

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'SystemAdmin#Pass1',
        ]);
    }

    public function test_a_super_admin_can_edit_another_accounts_profile(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create([
            'name' => 'Target Name',
            'contact_number' => '09170000000',
            'job_title' => 'Analyst',
            'department' => 'Quality',
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $target), [
                'name' => 'Updated Name',
                'contact_number' => '09991234567',
                'job_title' => 'Senior Analyst',
                'department' => 'Engineering',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('09991234567', $target->contact_number);
        $this->assertSame('Senior Analyst', $target->job_title);
        $this->assertSame('Engineering', $target->department);

        $audit = AuditLog::query()
            ->where('action', 'user.profile_updated')
            ->where('target_user_id', $target->getKey())
            ->sole();

        $this->assertSame($actor->getKey(), $audit->actor_user_id);
        $this->assertSame('Target Name', $audit->before_values['name']);
        $this->assertSame('Updated Name', $audit->after_values['name']);
    }

    public function test_a_permitted_system_admin_can_edit_an_ordinary_account(): void
    {
        $actor = $this->createSystemAdmin(withProfilePermission: true);
        $target = User::factory()->create(['name' => 'Ordinary User']);

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $target), [
                'name' => 'Edited By Admin',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasNoErrors();

        $this->assertSame('Edited By Admin', $target->refresh()->name);
    }

    public function test_a_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withProfilePermission: false);
        $target = User::factory()->create(['name' => 'Untouched']);

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $target), [
                'name' => 'Not Allowed',
            ])
            ->assertForbidden();

        $this->assertSame('Untouched', $target->refresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_system_admin_cannot_edit_a_protected_account(): void
    {
        $actor = $this->createSystemAdmin(withProfilePermission: true);

        $superAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        $protected = User::factory()->create([
            'name' => 'Protected Super Admin',
            'role_id' => $superAdminRole->id,
        ]);

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $protected), [
                'name' => 'Compromised',
            ])
            ->assertForbidden();

        $this->assertSame('Protected Super Admin', $protected->refresh()->name);
    }

    public function test_the_administrative_route_rejects_self_editing(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $actor), [
                'name' => 'Self Edited',
            ])
            ->assertForbidden();

        $this->assertNotSame('Self Edited', $actor->refresh()->name);
    }

    public function test_a_deactivated_account_cannot_be_edited(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['name' => 'Deactivated User']);
        $target->delete();

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $target), [
                'name' => 'Should Not Apply',
            ])
            ->assertNotFound();

        $this->assertSame('Deactivated User', $target->refresh()->name);
    }

    public function test_identity_and_security_fields_are_ignored(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['name' => 'Unchanged Name']);
        $originalEmail = $target->email;

        $this->actingAs($actor)
            ->patch(route('settings.users.profile.update', $target), [
                'name' => 'Unchanged Name',
                'email' => 'attacker@example.com',
                'account_status' => 'invited',
                'password' => 'Injected#Password1',
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertSame($originalEmail, $target->email);
        $this->assertSame('active', $target->account_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_profile_field_lengths_are_validated(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target))
            ->patch(route('settings.users.profile.update', $target), [
                'name' => str_repeat('a', 256),
                'contact_number' => str_repeat('1', 31),
                'job_title' => str_repeat('b', 101),
                'department' => str_repeat('c', 101),
            ])
            ->assertRedirect(route('settings.users.show', $target))
            ->assertSessionHasErrors(['name', 'contact_number', 'job_title', 'department']);
    }

    public function test_the_details_page_offers_the_profile_form_only_to_permitted_actors(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->createSuperAdmin())
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee(route('settings.users.profile.update', $target), false);

        $this->actingAs($this->createSystemAdmin(withProfilePermission: false))
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertDontSee(route('settings.users.profile.update', $target), false);
    }

    public function test_the_details_page_hides_the_profile_form_for_protected_accounts(): void
    {
        $actor = $this->createSystemAdmin(withProfilePermission: true);

        $protected = User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(
                ['code' => 'SUPER_ADMIN'],
                ['name' => 'Super Admin', 'scope' => 'system'],
            )->id,
        ]);

        $this->actingAs($actor)
            ->get(route('settings.users.show', $protected))
            ->assertOk()
            ->assertDontSee(route('settings.users.profile.update', $protected), false);
    }

    public function test_the_permission_seeder_grants_profile_management_to_system_admins(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        (new UserManagementPermissionSeeder)->run();

        $this->assertTrue(
            $role->permissions()->where('code', 'users.profile.manage')->exists()
        );

        $this->assertDatabaseHas('permissions', [
            'code' => 'users.profile.manage',
            'name' => 'Edit User Profiles',
        ]);
    }
}
