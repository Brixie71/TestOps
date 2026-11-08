<?php

namespace Tests\Feature\Actions;

use App\Actions\ReactivateUserAction;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Token;
use RuntimeException;
use Tests\TestCase;

class ReactivateUserActionTest extends TestCase
{
    use RefreshDatabase;

    private ReactivateUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ReactivateUserAction::class);
    }

    public function test_restores_the_soft_deleted_account_and_audits_the_action(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create(['account_status' => 'active']);

        $target->delete();

        $this->action->execute($actor, $target);

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'account_status' => 'active',
            'deleted_at' => null,
        ]);

        $reactivation = AuditLog::query()
            ->where('action', 'user.reactivated')
            ->firstOrFail();

        $this->assertSame($actor->getKey(), $reactivation->actor_user_id);
        $this->assertSame($target->getKey(), $reactivation->target_user_id);
        $this->assertArrayHasKey('deleted_at', $reactivation->before_values);
        $this->assertSame(
            ['account_status' => 'active'],
            $reactivation->after_values,
        );
    }

    public function test_preserves_an_invited_account_status(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create(['account_status' => 'invited']);

        $target->delete();

        $this->action->execute($actor, $target);

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            // An invited account is never activated automatically.
            'account_status' => 'invited',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.reactivated',
            'target_user_id' => $target->getKey(),
        ]);
    }

    public function test_never_restores_revoked_credentials_or_old_sessions(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();

        $accessToken = $this->createAccessToken($target->getKey(), ['revoked' => true]);
        $authCode = $this->createAuthCode($target->getKey(), ['revoked' => true]);

        $target->delete();

        $this->action->execute($actor, $target);

        $this->assertTrue($accessToken->fresh()->revoked);
        $this->assertTrue($authCode->fresh()->revoked);

        // Old database sessions are never restored.
        $this->assertSame(
            0,
            DB::table('sessions')->where('user_id', $target->getKey())->count(),
        );
    }

    public function test_denies_targets_that_are_not_deactivated(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();

        try {
            $this->action->execute($actor, $target);
            $this->fail('Reactivation of an active account was not denied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_blocks_self_reactivation(): void
    {
        $this->createCanonicalSuperAdminRole();
        $target = User::factory()->create();
        $target->delete();

        $trashedTarget = User::withTrashed()->findOrFail($target->getKey());

        $this->expectException(AuthorizationException::class);

        $this->action->execute($trashedTarget, $trashedTarget);
    }

    public function test_denies_actors_without_lifecycle_authorization(): void
    {
        $this->createCanonicalSuperAdminRole();
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $target->delete();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));
    }

    public function test_project_scoped_permissions_cannot_grant_lifecycle_authority(): void
    {
        $actor = $this->createActorWithPermission('PROJECT_ADMIN', 'project');
        $target = User::factory()->create();
        $target->delete();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));
    }

    public function test_system_admin_cannot_reactivate_a_super_admin(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = $this->createSuperAdmin('Protected Super Admin');

        $target->delete();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));
    }

    public function test_a_system_admin_with_permission_can_reactivate_an_eligible_user(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = User::factory()->create();

        $target->delete();

        $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_rejects_a_target_already_reactivated_before_it_obtains_the_role_row_lock(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();
        $target->delete();

        // A concurrent lifecycle change restores the row before this request
        // reaches the role-row lock; the model passed to the action remains
        // stale (still soft-deleted in memory).
        $this->restoreUserConcurrently($target);

        $this->assertTrue($target->trashed(), 'Test setup: the in-memory model must remain stale.');

        try {
            $this->action->execute($actor, $target);
            $this->fail('A target reactivated before the lock was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        // The rejection must not duplicate the reactivation audit event.
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_rejects_an_actor_soft_deleted_before_it_obtains_the_role_row_lock(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();
        $target->delete();

        // A concurrent lifecycle change deactivates the actor's own account
        // before this request reaches the role-row lock.
        $this->deleteUserConcurrently($actor);

        try {
            $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));
            $this->fail('An actor deactivated before the lock was not rejected.');
        } catch (AuthorizationException) {
            $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_an_actor_whose_permission_was_revoked_before_the_lock(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = User::factory()->create();
        $target->delete();

        // Authorization state changes after the actor model was loaded and
        // before the role-row lock is obtained.
        Role::query()->where('code', 'SYSTEM_ADMIN')->firstOrFail()
            ->permissions()->detach(
                Permission::query()->where('code', 'users.deactivate')->firstOrFail()->getKey()
            );

        try {
            $this->action->execute($actor, User::withTrashed()->findOrFail($target->getKey()));
            $this->fail('An actor whose permission was revoked was not rejected.');
        } catch (AuthorizationException) {
            $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_locks_the_canonical_super_admin_role_row_before_participant_reload_and_checks(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();
        $target->delete();
        $staleTarget = User::withTrashed()->findOrFail($target->getKey());

        $statements = [];

        DB::listen(function ($event) use (&$statements): void {
            $statements[] = [
                'sql' => $event->sql,
                // RefreshDatabase wraps each test in an outer transaction, so
                // the lifecycle transaction owned by the action is visible as
                // nesting level 2 on the shared connection.
                'in_lifecycle_transaction' => DB::transactionLevel() > 1,
            ];
        });

        $this->action->execute($actor, $staleTarget);

        // Bound values are not part of $event->sql, so the lock query is
        // identified by its shape: a roles lookup by code and scope, limited
        // to one row. SQLite compiles the lock itself to a no-op, so this
        // proves statement ordering inside the lifecycle transaction — not
        // real row-lock concurrency.
        $roleLockIndex = $this->firstIndexOf($statements, fn (array $statement): bool => str_contains($statement['sql'], 'select * from "roles"')
            && str_contains($statement['sql'], '"code" = ?')
            && str_contains($statement['sql'], '"scope" = ?')
            && str_contains($statement['sql'], 'limit 1')
            && $statement['in_lifecycle_transaction']);

        $this->assertNotFalse($roleLockIndex, 'The canonical role row was not selected inside the lifecycle transaction.');

        $participantReloadIndex = $this->firstIndexOf($statements, fn (array $statement): bool => str_contains($statement['sql'], 'select * from "users"')
            && $statement['in_lifecycle_transaction']);

        $this->assertNotFalse($participantReloadIndex, 'The participants were not reloaded inside the lifecycle transaction.');
        $this->assertGreaterThan(
            $roleLockIndex,
            $participantReloadIndex,
            'Participant reload and subsequent checks must run only after the canonical role-row lock is acquired.',
        );
    }

    public function test_fails_closed_before_lifecycle_changes_when_connections_are_inconsistent(): void
    {
        $actor = $this->createSuperAdmin('Reactivate Super Admin');
        $target = User::factory()->create();

        $target->delete();
        $deletedAt = $target->deleted_at;

        config([
            'session.connection' => 'lifecycle_alt',
            'database.connections.lifecycle_alt' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        try {
            $this->action->execute($actor, $target);
            $this->fail('The inconsistent connection configuration was not rejected.');
        } catch (RuntimeException $caught) {
            $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
            $this->assertSame(0, AuditLog::query()->count());
            $this->assertNotNull($deletedAt);
        }
    }

    private function createSuperAdmin(string $name): User
    {
        $role = $this->createCanonicalSuperAdminRole();

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Create the canonical SUPER_ADMIN system role row that lifecycle
     * transactions acquire as their serialization root.
     */
    private function createCanonicalSuperAdminRole(): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );
    }

    private function createActorWithPermission(string $roleCode, string $scope = 'system'): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => 'Deactivate Test Role', 'scope' => $scope],
        );

        $permission = Permission::query()->firstOrCreate(
            ['code' => 'users.deactivate'],
            ['name' => 'Deactivate User Accounts'],
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        // Lifecycle transactions fail closed without the canonical
        // SUPER_ADMIN role row, so every authorized actor setup seeds it.
        $this->createCanonicalSuperAdminRole();

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Mutate a user directly through the database so the caller keeps a
     * stale in-memory model. Simulates a committed concurrent lifecycle
     * change that lands before the action waits for the role-row lock —
     * SQLite cannot execute truly concurrent transactions in this suite.
     */
    private function deleteUserConcurrently(User $staleModel): void
    {
        User::query()->findOrFail($staleModel->getKey())->delete();
    }

    /**
     * @param  array<int, array{sql: string, in_lifecycle_transaction: bool}>  $statements
     */
    private function firstIndexOf(array $statements, callable $match): int|false
    {
        foreach ($statements as $index => $statement) {
            if ($match($statement)) {
                return $index;
            }
        }

        return false;
    }

    private function restoreUserConcurrently(User $staleModel): void
    {
        User::query()->withTrashed()->findOrFail($staleModel->getKey())->restore();
    }

    /**
     * Create an access token through the installed Passport model.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAccessToken(?int $userId, array $overrides = []): Token
    {
        return Token::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ], $overrides));
    }

    /**
     * Create an authorization code through the installed Passport model.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAuthCode(?int $userId, array $overrides = []): AuthCode
    {
        return AuthCode::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ], $overrides));
    }
}
