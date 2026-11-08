<?php

namespace Tests\Feature\Actions;

use App\Actions\DeactivateUserAction;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\AuthCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use RuntimeException;
use Tests\TestCase;

class DeactivateUserActionTest extends TestCase
{
    use RefreshDatabase;

    private DeactivateUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(DeactivateUserAction::class);
    }

    public function test_deactivates_the_target_user_and_audits_both_events(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['account_status' => 'active']);
        $reason = 'Confirmed policy violation.';

        $this->action->execute($actor, $target, $reason);

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);

        // Deactivation never adds a new account_status value.
        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'account_status' => 'active',
        ]);

        $deactivation = AuditLog::query()
            ->where('action', 'user.deactivated')
            ->firstOrFail();

        $this->assertSame($actor->getKey(), $deactivation->actor_user_id);
        $this->assertSame($target->getKey(), $deactivation->target_user_id);
        $this->assertSame($reason, $deactivation->reason);
        $this->assertSame(
            ['account_status' => 'active'],
            $deactivation->before_values,
        );
        $this->assertArrayHasKey('deleted_at', $deactivation->after_values);
    }

    public function test_revokes_the_target_credentials_and_audits_aggregate_counts_only(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $accessToken = $this->createAccessToken($target->getKey());
        $refreshToken = $this->createRefreshToken($accessToken);
        $authCode = $this->createAuthCode($target->getKey());

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        $revocation = AuditLog::query()
            ->where('action', 'oauth.credentials.revoked')
            ->firstOrFail();

        $this->assertSame(
            ['access_tokens' => 1, 'refresh_tokens' => 1, 'authorization_codes' => 1],
            $revocation->after_values,
        );
        $this->assertTrue($accessToken->fresh()->revoked);
        $this->assertTrue($refreshToken->fresh()->revoked);
        $this->assertTrue($authCode->fresh()->revoked);

        // Audit payloads must contain aggregate counts only — never
        // credential identifiers or secrets.
        $serializedAfterValues = (string) json_encode($revocation->after_values);
        $this->assertStringNotContainsString((string) $accessToken->getKey(), $serializedAfterValues);
        $this->assertStringNotContainsString((string) $refreshToken->getKey(), $serializedAfterValues);
        $this->assertStringNotContainsString((string) $authCode->getKey(), $serializedAfterValues);
    }

    public function test_revokes_and_purges_only_the_target_users_credentials_and_sessions(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $other = User::factory()->create();

        $targetToken = $this->createAccessToken($target->getKey());
        $otherToken = $this->createAccessToken($other->getKey());
        $targetCode = $this->createAuthCode($target->getKey());
        $otherCode = $this->createAuthCode($other->getKey());

        $targetSessionId = $this->insertSessionForUser($target);
        $otherSessionId = $this->insertSessionForUser($other);

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        $this->assertTrue($targetToken->fresh()->revoked);
        $this->assertFalse($otherToken->fresh()->revoked);
        $this->assertTrue($targetCode->fresh()->revoked);
        $this->assertFalse($otherCode->fresh()->revoked);

        $this->assertSame(0, $this->countSessionsForIds([$targetSessionId]));
        $this->assertSame(1, $this->countSessionsForIds([$otherSessionId]));
    }

    public function test_rolls_back_the_entire_lifecycle_when_any_step_fails(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $accessToken = $this->createAccessToken($target->getKey());
        $authCode = $this->createAuthCode($target->getKey());
        $sessionId = $this->insertSessionForUser($target);

        AuditLog::creating(function (AuditLog $log): void {
            if ($log->action === 'oauth.credentials.revoked') {
                throw new RuntimeException('Injected audit failure.');
            }
        });

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('Injected audit failure was not raised.');
        } catch (RuntimeException $caught) {
            // The audit failure must roll back every lifecycle change.
            $this->assertTrue(
                User::withTrashed()->whereKey($target->getKey())->whereNull('deleted_at')->exists(),
                'The user must not be soft-deleted when the transaction fails.',
            );
            $this->assertFalse($accessToken->fresh()->revoked);
            $this->assertFalse($authCode->fresh()->revoked);
            $this->assertSame(1, $this->countSessionsForIds([$sessionId]));
            $this->assertSame(0, AuditLog::query()->count());
        }
    }

    public function test_blocks_self_deactivation(): void
    {
        $actor = $this->createSuperAdmin();

        try {
            $this->action->execute($actor, $actor, 'Confirmed policy violation.');
            $this->fail('Self-deactivation was not blocked.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('users', ['id' => $actor->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_denies_actors_without_lifecycle_authorization(): void
    {
        $this->createCanonicalSuperAdminRole();
        $actor = User::factory()->create();
        $target = User::factory()->create();

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('An actor without lifecycle authorization was not denied.');
        } catch (AuthorizationException $caught) {
            $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_project_scoped_permissions_cannot_grant_lifecycle_authority(): void
    {
        $actor = $this->createActorWithPermission('PROJECT_ADMIN', 'project');
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, $target, 'Confirmed policy violation.');
    }

    public function test_system_admin_cannot_deactivate_a_super_admin(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = $this->createSuperAdmin();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, $target, 'Confirmed policy violation.');
    }

    public function test_system_admin_cannot_deactivate_another_system_admin(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = $this->createActorWithPermission('SYSTEM_ADMIN');

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, $target, 'Confirmed policy violation.');
    }

    public function test_a_system_admin_with_permission_can_deactivate_an_eligible_user(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = User::factory()->create();

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_the_last_active_super_admin_is_protected(): void
    {
        $target = $this->createSuperAdmin();

        try {
            app(UserLifecycleAuthorizer::class)->assertLastActiveSuperAdminProtected($target);
            $this->fail('The last active Super Admin was not protected.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        }
    }

    public function test_a_super_admin_can_be_deactivated_while_another_remains_active(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createSuperAdmin();

        // Two active Super Admins exist, so the safeguard must not trigger.
        app(UserLifecycleAuthorizer::class)->assertLastActiveSuperAdminProtected($target);

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_locks_the_canonical_super_admin_role_row_before_the_last_active_check(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createSuperAdmin();

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

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        // Locate the role-row serialization query, the last-active count,
        // and the deactivation mutation, then assert this order. The lock is
        // recorded while the lifecycle transaction is open on the default
        // connection. Bound values are not part of $event->sql, so the lock
        // query is identified by its shape: a roles lookup by code and
        // scope, limited to one row.
        $roleLockIndex = $this->firstIndexOf($statements, fn (array $statement): bool => str_contains($statement['sql'], 'select * from "roles"')
            && str_contains($statement['sql'], '"code" = ?')
            && str_contains($statement['sql'], '"scope" = ?')
            && str_contains($statement['sql'], 'limit 1')
            && $statement['in_lifecycle_transaction']);

        $this->assertNotFalse($roleLockIndex, 'The canonical role row was not selected inside the lifecycle transaction.');

        $activeCountIndex = $this->firstIndexOf($statements, fn (array $statement): bool => str_contains($statement['sql'], 'select count(*)')
            && str_contains($statement['sql'], '"users"')
            && $statement['in_lifecycle_transaction']);

        $this->assertNotFalse($activeCountIndex, 'The last-active Super Admin count was not evaluated inside the lifecycle transaction.');
        $this->assertGreaterThan(
            $roleLockIndex,
            $activeCountIndex,
            'The last-active Super Admin count must be evaluated only after the canonical role-row lock is acquired.',
        );
    }

    public function test_fails_closed_when_the_canonical_super_admin_role_row_is_missing(): void
    {
        // An authorized actor whose role is not the canonical SUPER_ADMIN
        // row: the lifecycle permission alone authorizes the actor, but the
        // missing canonical SUPER_ADMIN role row must abort the operation
        // before any lifecycle change.
        $actor = $this->createActorWithPermission('LIFECYCLE_ACTOR');
        $target = User::factory()->create();

        // No account may reference the canonical row, so it can be removed
        // without violating the `users.role_id` foreign key.
        Role::query()->where('code', 'SUPER_ADMIN')->delete();
        $this->assertSame(0, Role::query()->where('code', 'SUPER_ADMIN')->count());

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('The missing canonical role row was not rejected.');
        } catch (RuntimeException $caught) {
            $this->assertStringContainsStringIgnoringCase('SUPER_ADMIN', $caught->getMessage());
        }

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'account_status' => 'active',
        ]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_trashed_actor_cannot_deactivate_the_remaining_super_admin_after_mutual_deactivation(): void
    {
        // Simulates the two-administrator race sequentially: the check that
        // runs last must observe the committed deactivation instead of the
        // pre-transaction count.
        $actorA = $this->createSuperAdmin();
        $actorB = $this->createSuperAdmin();

        $this->action->execute($actorA, $actorB, 'Confirmed policy violation.');
        $this->assertSoftDeleted('users', ['id' => $actorB->getKey()]);
        $this->assertSame(2, AuditLog::query()->count());

        try {
            $this->action->execute($actorB, $actorA, 'Confirmed policy violation.');
            $this->fail('The final active Super Admin was not protected against the second actor.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('users', ['id' => $actorA->getKey()]);
            $this->assertDatabaseHas('users', [
                'id' => $actorA->getKey(),
                'account_status' => 'active',
            ]);
        }

        // The rejected attempt must not have written audits.
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_rejects_a_target_soft_deleted_before_it_obtains_the_role_row_lock(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create(['account_status' => 'active']);
        $accessToken = $this->createAccessToken($target->getKey());
        $sessionId = $this->insertSessionForUser($target);

        // A concurrent lifecycle change commits before this request reaches
        // the role-row lock; the model passed to the action is stale
        // (deleted_at still null in memory).
        $this->softDeleteTargetConcurrently($target);

        $this->assertFalse($target->trashed(), 'Test setup: the in-memory model must remain stale.');

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('A target deactivated before the lock was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        // Rejection must not duplicate lifecycle state changes: one account,
        // no duplicated audit, untouched credentials and session.
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertFalse($accessToken->fresh()->revoked);
        $this->assertSame(1, $this->countSessionsForIds([$sessionId]));
        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'account_status' => 'active',
            'deleted_at' => $target->fresh()->deleted_at, // unchanged by the rejected call
        ]);
    }

    public function test_rejects_an_actor_deactivated_before_it_obtains_the_role_row_lock(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        // A concurrent lifecycle change deactivates the actor's own account
        // before this request reaches the role-row lock.
        $this->softDeleteTargetConcurrently($actor);

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('An actor deactivated before the lock was not rejected.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_an_actor_whose_permission_was_revoked_before_the_lock(): void
    {
        $actor = $this->createActorWithPermission('SYSTEM_ADMIN');
        $target = User::factory()->create();

        // Authorization state changes after the actor model was loaded and
        // before the role-row lock is obtained.
        Role::query()->where('code', 'SYSTEM_ADMIN')->firstOrFail()
            ->permissions()->detach(
                Permission::query()->where('code', 'users.deactivate')->firstOrFail()->getKey()
            );

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('An actor whose permission was revoked was not rejected.');
        } catch (AuthorizationException) {
            $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_a_reason_longer_than_500_characters(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $tooLong = str_repeat('日', 501);

        try {
            $this->action->execute($actor, $target, $tooLong);
            $this->fail('The over-long reason was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_an_empty_deactivation_reason(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        try {
            $this->action->execute($actor, $target, '   ');
            $this->fail('The empty reason was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_rejects_repeated_deactivation(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        $this->action->execute($actor, $target, 'Confirmed policy violation.');

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('The already deactivated account was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }
    }

    public function test_fails_closed_before_lifecycle_changes_when_connections_are_inconsistent(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        config([
            'session.connection' => 'lifecycle_alt',
            'database.connections.lifecycle_alt' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        try {
            $this->action->execute($actor, $target, 'Confirmed policy violation.');
            $this->fail('Inconsistent lifecycle connections were not rejected.');
        } catch (RuntimeException $caught) {
            // Fail-closed: the account stays active and no lifecycle change
            // (including audits) was written before the guard aborted.
            $this->assertNotSoftDeleted('users', ['id' => $target->getKey()]);
            $this->assertDatabaseHas('users', [
                'id' => $target->getKey(),
                'account_status' => 'active',
            ]);
            $this->assertSame(0, AuditLog::query()->count());
        }
    }

    private function createSuperAdmin(): User
    {
        $role = $this->createCanonicalSuperAdminRole();

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Create the canonical SUPER_ADMIN system role row that deactivation
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

        // Deactivation transactions fail closed without the canonical
        // SUPER_ADMIN role row, so every authorized actor setup seeds it.
        $this->createCanonicalSuperAdminRole();

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Soft-delete a user directly so the caller keeps a stale in-memory
     * model. Simulates a committed concurrent lifecycle change that lands
     * before the action waits for the role-row lock — SQLite cannot execute
     * truly concurrent transactions in this suite, so the concurrency is
     * simulated sequentially.
     */
    private function softDeleteTargetConcurrently(User $staleModel): void
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
     * Create a refresh token linked to the given access token.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createRefreshToken(Token $accessToken, array $overrides = []): RefreshToken
    {
        return RefreshToken::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'access_token_id' => $accessToken->getKey(),
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

    private function insertSessionForUser(User $user): string
    {
        $sessionId = (string) Str::uuid();

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        return $sessionId;
    }

    private function countSessionsForIds(array $ids): int
    {
        return DB::table('sessions')->whereIn('id', $ids)->count();
    }
}
