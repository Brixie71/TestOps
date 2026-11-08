<?php

namespace Tests\Feature\Actions;

use App\Actions\AssignProjectOwnerAction;
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

class AssignProjectOwnerActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Foundational ownership handover';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_assigns_first_permanent_owner_and_audits_the_manual_operation(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $this->attachAdminMember($project);

        $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);

        $project->refresh();

        $this->assertSame($owner->id, $project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);

        $audit = $this->auditRow('project.ownership.assigned', $actor->id, $owner->id);

        $this->assertNull($audit->before_values['owner_id']);
        $this->assertNull($audit->before_values['owner_assignment_type']);
        $this->assertSame($owner->id, $audit->after_values['owner_id']);
        $this->assertSame('permanent', $audit->after_values['owner_assignment_type']);
        $this->assertSame('manual', $audit->after_values['transfer_type']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_super_admin_assigns_first_owner_with_explicit_temporary_type(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $this->attachAdminMember($project);

        $this->action()->execute($actor, $project, $owner, 'temporary', self::REASON);

        $project->refresh();

        $this->assertSame($owner->id, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);
    }

    public function test_system_admin_assigns_ownership_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');
        $owner = User::factory()->create();

        $projectA = Project::factory()->withoutOwner()->create();
        $projectB = Project::factory()->withoutOwner()->create();

        $projectA->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);
        $projectB->members()->attach($owner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $projectA, $owner, 'permanent', self::REASON);
        $this->action()->execute($actor, $projectB, $owner, 'temporary', self::REASON);

        $this->assertSame($owner->id, $projectA->fresh()->owner_id);
        $this->assertSame($owner->id, $projectB->fresh()->owner_id);
    }

    public function test_system_admin_without_ownership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        $project = Project::factory()->withoutOwner()->create();
        $owner = $this->attachAdminMember($project);

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
    }

    public function test_project_admin_is_never_authorized_to_assign_ownership(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $admin = $this->attachAdminMember($project);
        $owner = $this->attachAdminMember($project);

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, $owner, 'permanent', self::REASON);
    }

    public function test_ownership_cannot_be_assigned_when_an_owner_already_exists(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachAdminMember($project);

        try {
            $this->action()->execute($actor, $project, $newOwner, 'permanent', self::REASON);
            $this->fail('Expected assignment rejection on an owned project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner', $exception->errors());
        }

        $this->assertSame($project->owner_id, $project->fresh()->owner_id);
        $this->assertNotSame($newOwner->id, $project->fresh()->owner_id);
    }

    public function test_non_member_cannot_become_owner(): void
    {
        [$project, $actor] = $this->assignableFixture();

        $outsider = User::factory()->create();

        try {
            $this->action()->execute($actor, $project, $outsider, 'permanent', self::REASON);
            $this->fail('Expected rejection for the non-member owner target.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_member_holding_a_non_admin_project_role_cannot_become_owner(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_VIEWER')->id]);

        try {
            $this->action()->execute($actor, $project, $member, 'permanent', self::REASON);
            $this->fail('Expected rejection for the non-PROJECT_ADMIN member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_soft_deleted_member_cannot_become_owner(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        $owner->delete();

        try {
            $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
            $this->fail('Expected rejection for the soft-deleted member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_invited_member_cannot_become_owner(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        // account_status is not mass-assignable; the controlled write is
        // required for the invite state.
        $owner->forceFill(['account_status' => 'invited'])->save();

        try {
            $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
            $this->fail('Expected rejection for the invited member.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_invalid_assignment_types_are_rejected(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        foreach (['forever', '', 'PERMANENT'] as $invalidType) {
            try {
                $this->action()->execute($actor, $project, $owner, $invalidType, self::REASON);
                $this->fail('Expected rejection for the assignment type.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('assignment_type', $exception->errors());
            }
        }

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_missing_actor_fails_closed(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        $actor->forceDelete();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
    }

    public function test_soft_deleted_actor_fails_closed(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        $actor->delete();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
    }

    public function test_manual_assignment_does_not_replace_the_automatic_hook_payload(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $this->attachAdminMember($project);

        $this->action()->execute($actor, $project, $owner, 'temporary', self::REASON);

        $this->assertSame(
            'manual',
            $this->auditRow('project.ownership.assigned', $actor->id, $owner->id)->after_values['transfer_type'],
        );

        $this->assertDatabaseMissing('audit_logs', [
            'transfer_type' => 'automatic',
        ]);
    }

    public function test_audit_failure_rolls_back_assignment(): void
    {
        [$project, $actor, $owner] = $this->assignableFixture();

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, $owner, 'permanent', self::REASON);
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertNull($project->fresh()->owner_id);
        $this->assertNull($project->fresh()->owner_assignment_type);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    /**
     * A project without an owner, a SUPER_ADMIN actor, and an attached
     * PROJECT_ADMIN member eligible to become the owner.
     *
     * @return array{0: Project, 1: User, 2: User}
     */
    private function assignableFixture(): array
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $owner = $this->attachAdminMember($project);

        return [$project, $actor, $owner];
    }

    private function attachAdminMember(Project $project): User
    {
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        return $member;
    }

    private function action(): AssignProjectOwnerAction
    {
        return $this->app->make(AssignProjectOwnerAction::class);
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
