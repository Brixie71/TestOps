<?php

namespace Tests\Feature\Actions;

use App\Actions\TransferProjectOwnerAction;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TransferProjectOwnerActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Ownership succession between representatives';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_transfers_to_another_admin_and_previous_owner_keeps_membership_and_role(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $previousOwner = $project->owner;

        $this->assertNotNull($previousOwner);
        $project->members()->attach($previousOwner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $newOwner = $this->attachAdminMember($project);

        $this->action()->execute($actor, $project, $newOwner, 'temporary', self::REASON);

        $project->refresh();

        $this->assertSame($newOwner->id, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $previousOwner->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);

        $audit = $this->auditRow('project.ownership.transferred', $actor->id, $newOwner->id);

        $this->assertSame($previousOwner->id, $audit->before_values['owner_id']);
        $this->assertSame('permanent', $audit->before_values['owner_assignment_type']);
        $this->assertSame($newOwner->id, $audit->after_values['owner_id']);
        $this->assertSame('temporary', $audit->after_values['owner_assignment_type']);
        $this->assertSame($previousOwner->id, $audit->after_values['previous_owner_id']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_transfer_sets_the_explicit_type_without_inheriting_the_previous_type(): void
    {
        $project = Project::factory()->withTemporaryOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $newOwner = $this->attachAdminMember($project);
        $previousOwnerId = $project->owner_id;

        $this->assertSame('temporary', $project->owner_assignment_type);

        $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);

        $project->refresh();

        $this->assertSame($newOwner->id, $project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);
        $this->assertNotSame($previousOwnerId, $project->owner_id);
    }

    public function test_system_admin_transfers_ownership_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');

        $project = $this->ownedTransferFixture();
        $newOwner = $this->attachAdminMember($project);
        $previousOwnerId = $project->owner_id;

        $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);

        $project->refresh();

        $this->assertSame($newOwner->id, $project->owner_id);
        $this->assertNotSame($previousOwnerId, $project->owner_id);
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $actor->id,
        ]);
    }

    public function test_system_admin_without_ownership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        $project = $this->ownedTransferFixture();
        $newOwner = $this->attachAdminMember($project);

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);
    }

    public function test_project_admin_is_never_authorized_to_transfer_ownership(): void
    {
        $project = Project::factory()->create();

        $admin = $this->attachAdminMember($project);
        $newOwner = $this->attachAdminMember($project);

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, $newOwner, 'permanent', self::REASON);
    }

    public function test_transfer_to_the_current_owner_is_rejected(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $project->members()->attach($project->owner_id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $currentOwner = User::query()->findOrFail($project->owner_id);

        try {
            $this->action()->execute($actor, $project, $currentOwner, 'temporary', self::REASON);
            $this->fail('Expected transfer-to-current-owner rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $project->refresh();

        $this->assertSame($currentOwner->id, $project->owner_id);
    }

    public function test_transfer_on_an_unowned_project_is_rejected(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachAdminMember($project);

        try {
            $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);
            $this->fail('Expected transfer rejection on the unowned project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner', $exception->errors());
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_non_member_incoming_owner_is_rejected(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');
        $outsider = User::factory()->create();

        try {
            $this->action()->execute($actor, $project, $outsider, 'permanent', self::REASON);
            $this->fail('Expected rejection for the non-member incoming owner.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNotSame($outsider->id, $project->fresh()->owner_id);
    }

    public function test_member_without_the_admin_role_cannot_receive_ownership(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');

        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        try {
            $this->action()->execute($actor, $project, $member, 'permanent', self::REASON);
            $this->fail('Expected rejection for the non-PROJECT_ADMIN member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNotSame($member->id, $project->fresh()->owner_id);
    }

    public function test_soft_deleted_incoming_owner_is_rejected(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachAdminMember($project);

        $newOwner->delete();

        try {
            $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);
            $this->fail('Expected rejection for the soft-deleted incoming owner.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNotSame($newOwner->id, $project->fresh()->owner_id);
    }

    public function test_invited_incoming_owner_is_rejected(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachAdminMember($project);

        $newOwner->forceFill(['account_status' => 'invited'])->save();

        try {
            $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);
            $this->fail('Expected rejection for the invited incoming owner.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNotSame($newOwner->id, $project->fresh()->owner_id);
    }

    public function test_ownership_held_by_a_soft_deleted_owner_remains_transferable(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');

        $formerOwner = User::query()->withTrashed()->find($project->owner_id);
        $formerOwner->delete();

        $newOwner = $this->attachAdminMember($project);

        $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);

        $project->refresh();

        $this->assertSame($newOwner->id, $project->owner_id);
    }

    public function test_invalid_assignment_types_are_rejected(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachAdminMember($project);

        try {
            $this->action()->execute($actor, $project, $newOwner, 'forever', self::REASON);
            $this->fail('Expected rejection for the assignment type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment_type', $exception->errors());
        }

        $this->assertNotSame($newOwner->id, $project->fresh()->owner_id);
    }

    public function test_audit_failure_rolls_back_transfer(): void
    {
        $project = $this->ownedTransferFixture();
        $actor = $this->systemUser('SUPER_ADMIN');
        $previousOwnerId = $project->owner_id;
        $previousType = $project->owner_assignment_type;
        $newOwner = $this->attachAdminMember($project);

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, $newOwner, 'temporary', self::REASON);
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $project->refresh();

        $this->assertSame($previousOwnerId, $project->owner_id);
        $this->assertSame($previousType, $project->owner_assignment_type);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    /**
     * The default (owned) project with factory-generated permanent owner;
     * used as the source side of transfer operations.
     */
    private function ownedTransferFixture(): Project
    {
        return Project::factory()->create();
    }

    private function attachAdminMember(Project $project): User
    {
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return $member;
    }

    private function action(): TransferProjectOwnerAction
    {
        return $this->app->make(TransferProjectOwnerAction::class);
    }

    private function systemUser(string $code): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('code', $code)->where('scope', 'system')->firstOrFail();
        $user->role()->associate($role);
        $user->save();

        return $user;
    }

    private function assignRole(User $user, Role $role): void
    {
        $user->role()->associate($role);
        $user->save();
    }

    private function projectRole(string $code): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => 'project'],
            [
                'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                'description' => 'Test helper project role.',
            ],
        );
    }

    private function auditRow(string $action, int $actorId, int $targetId): AuditLog
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('actor_user_id', $actorId)
            ->where('target_user_id', $targetId)
            ->firstOrFail();
    }
}
