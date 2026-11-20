<?php

namespace Tests\Feature\Actions;

use App\Actions\PermanentlyDeleteUserAction;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmailAddress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Token;
use RuntimeException;
use Tests\TestCase;

class PermanentlyDeleteUserActionTest extends TestCase
{
    use RefreshDatabase;

    private const ACTOR_PASSWORD = 'Current#Password1';

    private PermanentlyDeleteUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(PermanentlyDeleteUserAction::class);
    }

    public function test_permanently_deletes_a_deactivated_account_and_audits_both_events(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();
        $reason = 'Retention period elapsed.';

        $this->action->execute($actor, $target, self::ACTOR_PASSWORD, $reason);

        $this->assertDatabaseMissing('users', ['id' => $target->getKey()]);

        $deletion = AuditLog::query()
            ->where('action', 'user.permanently_deleted')
            ->firstOrFail();

        $this->assertSame($actor->getKey(), $deletion->actor_user_id);
        $this->assertSame($target->getKey(), $deletion->target_user_id);
        $this->assertSame($target->email, $deletion->target_email);
        $this->assertSame($reason, $deletion->reason);
        $this->assertSame($target->getKey(), $deletion->before_values['user_id']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'oauth.credentials.revoked',
            'target_user_id' => $target->getKey(),
        ]);
    }

    public function test_releases_every_email_address_so_it_can_be_reused(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser(['email' => 'release.me@example.com']);

        UserEmailAddress::query()->create([
            'user_id' => $target->getKey(),
            'email' => 'previous.address@example.com',
            'status' => UserEmailAddress::STATUS_PREVIOUS,
        ]);

        $this->assertSame(2, UserEmailAddress::query()->where('user_id', $target->getKey())->count());

        $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');

        $this->assertSame(0, UserEmailAddress::query()->where('user_id', $target->getKey())->count());

        User::factory()->create(['email' => 'release.me@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'release.me@example.com']);
    }

    public function test_revokes_credentials_and_purges_only_the_target_sessions(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();
        $other = User::factory()->create();

        $targetToken = $this->createAccessToken($target->getKey());
        $otherToken = $this->createAccessToken($other->getKey());
        $targetCode = $this->createAuthCode($target->getKey());
        $targetSession = $this->insertSessionForUser($target);
        $otherSession = $this->insertSessionForUser($other);

        $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');

        $this->assertTrue($targetToken->fresh()->revoked);
        $this->assertFalse($otherToken->fresh()->revoked);
        $this->assertTrue($targetCode->fresh()->revoked);

        $this->assertSame(0, $this->countSessionsForIds([$targetSession]));
        $this->assertSame(1, $this->countSessionsForIds([$otherSession]));
    }

    public function test_requires_the_actors_current_password(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        try {
            $this->action->execute($actor, $target, 'Wrong#Password1', 'Retention period elapsed.');
            $this->fail('A wrong acting password was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_password', $exception->errors());
        }

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_an_account_that_is_not_deactivated(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');
            $this->fail('An active account was permanently deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delete', $exception->errors());
        }

        $this->assertDatabaseHas('users', ['id' => $target->getKey(), 'deleted_at' => null]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_blocks_self_deletion(): void
    {
        $actor = $this->createSuperAdmin();

        $this->expectException(AuthorizationException::class);

        $this->action->execute($actor, $actor, self::ACTOR_PASSWORD, 'Retention period elapsed.');
    }

    public function test_denies_actors_that_are_not_super_admin(): void
    {
        $this->createSuperAdmin();
        $actor = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'System Admin',
                'code' => 'SYSTEM_ADMIN',
                'scope' => 'system',
            ])->id,
            'password' => self::ACTOR_PASSWORD,
        ]);
        $target = $this->createDeactivatedUser();

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');
            $this->fail('A non-Super-Admin actor was allowed to permanently delete an account.');
        } catch (AuthorizationException) {
            $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_blocks_deletion_while_the_account_owns_a_project(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        Project::factory()->create(['owner_id' => $target->getKey()]);

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');
            $this->fail('An account owning a project was permanently deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delete', $exception->errors());
            $this->assertStringContainsString('Projects owned', $exception->errors()['delete'][0]);
        }

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_rejects_an_empty_reason(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, '   ');
            $this->fail('An empty deletion reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
    }

    public function test_rolls_back_every_change_when_a_later_step_fails(): void
    {
        $actor = $this->createSuperAdmin();
        $target = $this->createDeactivatedUser();

        $token = $this->createAccessToken($target->getKey());
        $sessionId = $this->insertSessionForUser($target);

        AuditLog::creating(function (AuditLog $log): void {
            if ($log->action === 'user.permanently_deleted') {
                throw new RuntimeException('Injected audit failure.');
            }
        });

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');
            $this->fail('Injected audit failure was not raised.');
        } catch (RuntimeException) {
            $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
            $this->assertFalse($token->fresh()->revoked);
            $this->assertSame(1, $this->countSessionsForIds([$sessionId]));
            $this->assertDatabaseHas('user_email_addresses', [
                'user_id' => $target->getKey(),
                'email' => $target->email,
            ]);
            $this->assertSame(0, AuditLog::query()->count());
        }
    }

    public function test_fails_closed_when_the_canonical_super_admin_role_row_is_missing(): void
    {
        $actor = User::factory()->create([
            'password' => self::ACTOR_PASSWORD,
        ]);
        $target = $this->createDeactivatedUser();

        try {
            $this->action->execute($actor, $target, self::ACTOR_PASSWORD, 'Retention period elapsed.');
            $this->fail('The missing canonical role row was not rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsStringIgnoringCase('SUPER_ADMIN', $exception->getMessage());
        }

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);
        $this->assertSame(0, AuditLog::query()->count());
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

    /**
     * Create a previously deactivated target account.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createDeactivatedUser(array $attributes = []): User
    {
        $target = User::factory()->create($attributes);
        $target->delete();

        return $target;
    }

    private function createAccessToken(int $userId): Token
    {
        return Token::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ]);
    }

    private function createAuthCode(int $userId): AuthCode
    {
        return AuthCode::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ]);
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

    /**
     * @param  array<int, string>  $ids
     */
    private function countSessionsForIds(array $ids): int
    {
        return DB::table('sessions')->whereIn('id', $ids)->count();
    }
}
