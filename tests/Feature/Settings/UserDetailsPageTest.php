<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_permitted_admin_sees_profile_status_role_and_audit_history(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create([
            'job_title' => 'QA Engineer',
            'department' => 'Quality',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee('QA Engineer')
            ->assertSee('Quality')
            ->assertSee('No system role')
            ->assertSee('Audit history');
    }

    public function test_the_role_assignment_form_renders_for_eligible_actors(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $this->createSystemRole('TESTER', 'Tester');

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee('Assign system role')
            ->assertSee('Update role');
    }

    public function test_the_super_admin_role_is_only_offered_to_super_admin_actors(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = User::factory()->create();

        $this->createSystemRole('TESTER', 'Tester');

        $response = $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee('Assign system role');

        $this->assertStringNotContainsString('Super Admin', $response->getContent());
    }

    public function test_the_role_assignment_form_is_hidden_for_protected_targets(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);
        $target = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertDontSee('Assign system role');
    }

    public function test_deactivated_accounts_remain_viewable_without_a_role_form(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $target->delete();

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target->id))
            ->assertOk()
            ->assertSee('Deactivated')
            ->assertDontSee('Assign system role');
    }

    public function test_the_details_page_requires_the_directory_permission(): void
    {
        $actor = User::factory()->create(); // Ordinary active user, no role.

        $target = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertForbidden();
    }

    public function test_audit_history_shows_the_targets_events(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'action' => 'user.account_created',
            'created_at' => now(),
        ]);

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target))
            ->assertOk()
            ->assertSee('user.account_created');
    }

    private function createSystemRole(string $code, string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'scope' => 'system'],
        );
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        if ($withPermission) {
            // The show route is gated by `users.deactivate`; the role form
            // additionally needs `users.roles.assign`.
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'users.deactivate'],
                ['name' => 'Deactivate User Accounts'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);

            $assignPermission = Permission::query()->firstOrCreate(
                ['code' => 'users.roles.assign'],
                ['name' => 'Assign User System Roles'],
            );
            $role->permissions()->syncWithoutDetaching([$assignPermission->id]);
        }

        Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
