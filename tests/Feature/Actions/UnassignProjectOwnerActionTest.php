<?php

namespace Tests\Feature\Actions;

use App\Actions\UnassignProjectOwnerAction;
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

class UnassignProjectOwnerActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Ownership released pending reorganization';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_unassigns_a_permanent_owner_and_keeps_membership_and_role(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $formerOwner = $project->owner;

        $this->assertNotNull($formerOwner);
        $project->members()->attach($formerOwner->id, ['role_id' => $this->projectRole('PROJECT_ADMIN')->id]);

        $this->action()->execute($actor, $project, self::REASON);

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $formerOwner->id,
            'role_id' => $this->projectRole('PROJECT_ADMIN')->id,
        ]);

        $audit = $this->auditRow('project.ownership.unassigned', $actor->id, $formerOwner->id);

        $this->assertSame($formerOwner->id, $audit->before_values['owner_id']);
        $this->assertSame('permanent', $audit->before_values['owner_assignment_type']);
        $this->assertNull($audit->after_values['owner_id']);
        $this->assertNull($audit->after_values['owner_assignment_type']);
        $this->assertSame($formerOwner->id, $audit->after_values['previous_owner_id']);
        $this->assertSame('permanent', $audit->after_values['previous_owner_assignment_type']);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_super_admin_unassigns_a_temporary_owner_and_audits_the_previous_type(): void
    {
        $project = Project::factory()->withTemporaryOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $formerOwner = $project->owner;

        $this->action()->execute($actor, $project, self::REASON);

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);

        $this->assertSame(
            'temporary',
            $this->auditRow('project.ownership.unassigned', $actor->id, $formerOwner->id)
                ->after_values['previous_owner_assignment_type'],
        );
    }

    public function test_system_admin_unassigns_ownership_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');
        $project = Project::factory()->create();

        $this->action()->execute($actor, $project, self::REASON);

        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_system_admin_without_ownership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        $project = Project::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, self::REASON);
    }

    public function test_unassignment_on_an_unowned_project_is_rejected(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        try {
            $this->action()->execute($actor, $project, self::REASON);
            $this->fail('Expected unassignment rejection on the unowned project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner', $exception->errors());
        }
    }

    public function test_ownership_held_by_a_soft_deleted_owner_remains_unassignable(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $formerOwner = $project->owner;

        $formerOwner->delete();

        $this->action()->execute($actor, $project, self::REASON);

        $project->refresh();

        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);
    }

    public function test_audit_failure_rolls_back_unassignment(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $previousOwnerId = $project->owner_id;
        $previousType = $project->owner_assignment_type;

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, self::REASON);
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

    private function action(): UnassignProjectOwnerAction
    {
        return $this->app->make(UnassignProjectOwnerAction::class);
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
