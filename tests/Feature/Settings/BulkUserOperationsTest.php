<?php

namespace Tests\Feature\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUserOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_bulk_assign_a_system_role(): void
    {
        $actor = $this->createSuperAdmin();
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $targets = User::factory()->count(3)->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.role'), [
                'user_ids' => $targets->pluck('id')->all(),
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 3
                    && collect($results)->every(fn (array $result): bool => $result['success']);
            });

        foreach ($targets as $target) {
            $this->assertSame($role->id, $target->refresh()->role_id);
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'user.role_changed',
                'target_user_id' => $target->getKey(),
                'actor_user_id' => $actor->getKey(),
            ]);
        }
    }

    public function test_bulk_assignment_of_the_super_admin_role_is_prohibited(): void
    {
        $actor = $this->createSuperAdmin();
        $superAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        $target = User::factory()->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.role'), [
                'user_ids' => [$target->getKey()],
                'role_id' => $superAdminRole->id,
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHasErrors('role_id');

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_bulk_role_assignment_rejects_non_system_roles(): void
    {
        $actor = $this->createSuperAdmin();
        $projectRole = Role::query()->firstOrCreate(
            ['code' => 'PROJECT_MEMBER'],
            ['name' => 'Project Member', 'scope' => 'project'],
        );

        $target = User::factory()->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.role'), [
                'user_ids' => [$target->getKey()],
                'role_id' => $projectRole->id,
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHasErrors('role_id');

        $this->assertNull($target->refresh()->role_id);
    }

    public function test_bulk_role_assignment_reports_individual_failures(): void
    {
        $actor = $this->createSuperAdmin();
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        // One target already holds the role, so it must fail individually.
        $alreadyAssigned = User::factory()->create(['role_id' => $role->id]);
        $pending = User::factory()->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.role'), [
                'user_ids' => [$alreadyAssigned->getKey(), $pending->getKey()],
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 2
                    && $results[0]['success'] === false
                    && $results[1]['success'] === true;
            });

        $this->assertSame($role->id, $pending->refresh()->role_id);
        $this->assertSame($role->id, $alreadyAssigned->refresh()->role_id);
    }

    public function test_an_ordinary_user_is_forbidden_from_bulk_operations(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['role_id' => null]);

        $this->actingAs($actor)
            ->post(route('settings.users.bulk.role'), [
                'user_ids' => [$target->getKey()],
                'role_id' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('settings.users.bulk.deactivate'), [
                'user_ids' => [$target->getKey()],
                'reason' => 'No longer needed.',
            ])
            ->assertForbidden();

        $this->assertNull($target->refresh()->role_id);
        $this->assertNull($target->refresh()->deleted_at);
    }

    public function test_bulk_deactivation_deactivates_the_selected_accounts(): void
    {
        $actor = $this->createSuperAdmin();

        $targets = User::factory()->count(2)->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.deactivate'), [
                'user_ids' => $targets->pluck('id')->all(),
                'reason' => 'Team offboarding wave.',
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 2
                    && collect($results)->every(fn (array $result): bool => $result['success']);
            });

        foreach ($targets as $target) {
            $this->assertSoftDeleted($target->refresh());
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'user.deactivated',
                'target_user_id' => $target->getKey(),
                'actor_user_id' => $actor->getKey(),
                'reason' => 'Team offboarding wave.',
            ]);
        }
    }

    public function test_bulk_deactivation_denies_self_deactivation_with_an_individual_failure(): void
    {
        $actor = $this->createSuperAdmin();

        $ordinaryUser = User::factory()->create(['role_id' => null]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.deactivate'), [
                'user_ids' => [$ordinaryUser->getKey(), $actor->getKey()],
                'reason' => 'Offboarding.',
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 2
                    && $results[0]['success'] === true
                    && $results[1]['success'] === false
                    && $results[1]['message'] !== '';
            });

        $this->assertSoftDeleted($ordinaryUser->refresh());
        $this->assertNull($actor->refresh()->deleted_at);
    }

    public function test_a_system_admin_cannot_bulk_deactivate_another_system_admin(): void
    {
        // The lifecycle action requires the canonical SUPER_ADMIN system
        // role row to enforce last-active protection.
        Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        $actor = $this->createSystemAdmin(withPermission: true);
        $systemAdminRole = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        $peer = User::factory()->create(['role_id' => $systemAdminRole->id]);

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.deactivate'), [
                'user_ids' => [$peer->getKey()],
                'reason' => 'Offboarding.',
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 1 && $results[0]['success'] === false;
            });

        $this->assertNull($peer->refresh()->deleted_at);
    }

    public function test_missing_accounts_are_reported_as_individual_failures(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->from(route('settings.users'))
            ->post(route('settings.users.bulk.deactivate'), [
                'user_ids' => [999999],
                'reason' => 'Offboarding.',
            ])
            ->assertRedirect(route('settings.users'))
            ->assertSessionHas('bulk_results', function (array $results): bool {
                return count($results) === 1
                    && $results[0]['success'] === false
                    && str_contains($results[0]['message'], 'does not exist');
            });
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
            $deactivate = Permission::query()->firstOrCreate(
                ['code' => 'users.deactivate'],
                ['name' => 'Deactivate Users'],
            );
            $assignRoles = Permission::query()->firstOrCreate(
                ['code' => 'users.roles.assign'],
                ['name' => 'Assign System Roles'],
            );
            $role->permissions()->syncWithoutDetaching([$deactivate->id, $assignRoles->id]);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
