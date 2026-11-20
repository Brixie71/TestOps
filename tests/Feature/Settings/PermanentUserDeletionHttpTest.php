<?php

namespace Tests\Feature\Settings;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermanentUserDeletionHttpTest extends TestCase
{
    use RefreshDatabase;

    private const ACTOR_PASSWORD = 'Current#Password1';

    public function test_super_admin_permanently_deletes_a_deactivated_account(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        $this->actingAs($actor)
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => 'Retention period elapsed.',
            ])
            ->assertRedirect(route('settings.users'));

        $this->assertDatabaseMissing('users', ['id' => $target->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.permanently_deleted',
            'target_user_id' => $target->getKey(),
            'reason' => 'Retention period elapsed.',
        ]);
    }

    public function test_a_system_admin_with_lifecycle_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin();
        $target = $this->createDeactivatedUser();

        $this->actingAs($actor)
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => 'Retention period elapsed.',
            ])
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_an_active_account_cannot_be_permanently_deleted(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target->getKey()))
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => 'Retention period elapsed.',
            ])
            ->assertRedirect(route('settings.users.show', $target->getKey()))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $target->getKey(), 'deleted_at' => null]);
    }

    public function test_the_actors_password_is_required_and_verified(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target->getKey()))
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'reason' => 'Retention period elapsed.',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target->getKey()))
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => 'Wrong#Password1',
                'reason' => 'Retention period elapsed.',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_a_reason_is_required(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target->getKey()))
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_an_owned_project_blocks_deletion_with_an_actionable_error(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        Project::factory()->create(['owner_id' => $target->getKey()]);

        $this->actingAs($actor)
            ->from(route('settings.users.show', $target->getKey()))
            ->delete(route('settings.users.permanent-delete', $target->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => 'Retention period elapsed.',
            ])
            ->assertSessionHasErrors([
                'delete' => 'This account still has dependent records: Projects owned (1): Transfer ownership of these projects before permanent deletion.',
            ]);

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_super_admin_cannot_permanently_delete_their_own_account(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->delete(route('settings.users.permanent-delete', $actor->getKey()), [
                'current_password' => self::ACTOR_PASSWORD,
                'reason' => 'Retention period elapsed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $actor->getKey(), 'deleted_at' => null]);
    }

    public function test_the_details_page_offers_permanent_deletion_only_for_deactivated_accounts(): void
    {
        $actor = $this->createSuperAdmin();
        $deactivated = $this->createDeactivatedUser();
        $active = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.users.show', $deactivated->getKey()))
            ->assertOk()
            ->assertSee('Permanent deletion')
            ->assertSee('settings/users/'.$deactivated->getKey().'/permanent', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="reason"', false);

        $this->actingAs($actor)
            ->get(route('settings.users.show', $active->getKey()))
            ->assertOk()
            ->assertDontSee('Permanent deletion');
    }

    public function test_the_details_page_lists_blocking_dependencies_and_disables_the_action(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        Project::factory()->create(['owner_id' => $target->getKey()]);

        $this->actingAs($actor)
            ->get(route('settings.users.show', $target->getKey()))
            ->assertOk()
            ->assertSee('Resolve these dependencies first:')
            ->assertSee('Projects owned: 1.')
            ->assertSee('Transfer ownership of these projects before permanent deletion.')
            ->assertSee('disabled', false);
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => self::ACTOR_PASSWORD,
        ]);
    }

    private function createSystemAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $role->permissions()->syncWithoutDetaching([
            Permission::query()->firstOrCreate(
                ['code' => 'users.deactivate'],
                ['name' => 'Deactivate User Accounts'],
            )->getKey(),
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => self::ACTOR_PASSWORD,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDeactivatedUser(array $attributes = []): User
    {
        $target = User::factory()->create($attributes);
        $target->delete();

        return $target;
    }
}
