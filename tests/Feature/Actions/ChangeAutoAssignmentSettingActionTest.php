<?php

namespace Tests\Feature\Actions;

use App\Actions\AddProjectMemberAction;
use App\Actions\ChangeProjectAutoAssignmentSettingAction;
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

class ChangeAutoAssignmentSettingActionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Adopt automatic representative assignment';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_super_admin_enables_the_setting_and_never_assigns_an_owner(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $admin = $this->attachAdminMember($project, 'PROJECT_VIEWER');

        $this->action()->execute($actor, $project, true, self::REASON);

        $project->refresh();

        $this->assertTrue($project->auto_assign_representative_owner);
        $this->assertNull($project->owner_id);
        $this->assertNull($project->owner_assignment_type);

        $audit = AuditLog::query()
            ->where('action', 'project.ownership.auto_assignment_setting_changed')
            ->where('actor_user_id', $actor->id)
            ->firstOrFail();

        $this->assertFalse($audit->before_values['auto_assign_representative_owner']);
        $this->assertTrue($audit->after_values['auto_assign_representative_owner']);
        $this->assertSame($project->id, $audit->after_values['project_id']);
        $this->assertNull($audit->target_user_id);
        $this->assertSame(self::REASON, $audit->reason);
    }

    public function test_super_admin_disables_the_setting_and_the_owner_remains_untouched(): void
    {
        $project = Project::factory()->withAutoAssignment()->withTemporaryOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $ownerId = $project->owner_id;

        $this->action()->execute($actor, $project, false, self::REASON);

        $project->refresh();

        $this->assertFalse($project->auto_assign_representative_owner);
        $this->assertSame($ownerId, $project->owner_id);
        $this->assertSame('temporary', $project->owner_assignment_type);

        $this->assertSame(
            false,
            AuditLog::query()
                ->where('action', 'project.ownership.auto_assignment_setting_changed')
                ->firstOrFail()
                ->after_values['auto_assign_representative_owner'],
        );
    }

    public function test_enabling_the_setting_never_replaces_an_existing_owner(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $ownerId = $project->owner_id;

        $this->action()->execute($actor, $project, true, self::REASON);

        $project->refresh();

        $this->assertTrue($project->auto_assign_representative_owner);
        $this->assertSame($ownerId, $project->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
        ]);
    }

    public function test_enabling_is_not_retroactive_for_later_project_admin_grants(): void
    {
        // The Part 2 hook fires only when the project state permits it at
        // mutation time; enabling the setting here must not convert the
        // already-granted PROJECT_ADMIN into an owner.
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        $this->action()->execute($actor, $project, true, self::REASON);

        $project->refresh();

        $this->assertSame($project->owner_id, $project->fresh()->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
        ]);
    }

    public function test_auto_assignment_hook_respects_the_disabled_setting(): void
    {
        // Integration with the Part 2 hook: with the setting disabled, a
        // newly granted PROJECT_ADMIN member never becomes the owner. The
        // membership mutation path itself is covered by Part 2 tests.
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $this->attachAdminMember($project, 'PROJECT_VIEWER');

        $member = User::factory()->create();

        $this->app->make(AddProjectMemberAction::class)->execute(
            $actor,
            $project,
            $member,
            $this->projectRole('PROJECT_ADMIN')->id,
            'New representative admin for the release train',
        );

        $this->assertNull($project->fresh()->owner_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'project.ownership.assigned',
        ]);
    }

    public function test_system_admin_changes_the_setting_across_projects_without_membership(): void
    {
        $actor = $this->systemUser('SYSTEM_ADMIN');
        $project = Project::factory()->withoutOwner()->create();

        $this->action()->execute($actor, $project, true, self::REASON);

        $this->assertTrue($project->fresh()->auto_assign_representative_owner);
    }

    public function test_system_admin_without_ownership_permission_is_denied(): void
    {
        $actor = User::factory()->create();
        $systemRole = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemRole->permissions()->detach();
        $this->assignRole($actor, $systemRole);

        $project = Project::factory()->withoutOwner()->create();

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($actor, $project, true, self::REASON);
    }

    public function test_project_admin_is_never_authorized_to_change_the_setting(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->attachAdminMember($project, 'PROJECT_ADMIN');

        $this->expectException(AuthorizationException::class);

        $this->action()->execute($admin, $project, true, self::REASON);
    }

    public function test_no_op_setting_change_is_rejected(): void
    {
        $actor = $this->systemUser('SUPER_ADMIN');

        $disabled = Project::factory()->withoutOwner()->create();
        $enabled = Project::factory()->withAutoAssignment()->create();

        foreach ([
            [$disabled, false],
            [$enabled, true],
        ] as [$project, $currentValue]) {
            try {
                $this->action()->execute($actor, $project, $currentValue, self::REASON);
                $this->fail('Expected no-op rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('auto_assign_representative_owner', $exception->errors());
            }
        }

        $this->assertFalse($disabled->fresh()->auto_assign_representative_owner);
        $this->assertTrue($enabled->fresh()->auto_assign_representative_owner);
    }

    public function test_audit_failure_rolls_back_the_setting_change(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');

        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit storage failure');
        });

        try {
            $this->action()->execute($actor, $project, true, self::REASON);
            $this->fail('Expected audit failure rollback.');
        } catch (RuntimeException $caught) {
            $this->assertSame('Audit storage failure', $caught->getMessage());
        }

        $this->assertFalse($project->fresh()->auto_assign_representative_owner);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_runs_inside_the_outer_test_transaction(): void
    {
        $this->assertTrue(DB::transactionLevel() > 0);
    }

    private function attachAdminMember(Project $project, string $roleCode): User
    {
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole($roleCode)->id]);

        return $member;
    }

    private function action(): ChangeProjectAutoAssignmentSettingAction
    {
        return $this->app->make(ChangeProjectAutoAssignmentSettingAction::class);
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
}
