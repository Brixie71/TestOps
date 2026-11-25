<?php

namespace Tests\Feature\Projects;

use App\Models\AuditLog;
use App\Models\Defect;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\TestResult;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDefectHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_owner_reports_a_defect_and_it_gets_the_next_project_key(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post(route('projects.defects.store', $project), [
                'title' => 'Checkout total ignores discounts',
                'description' => 'The total stays at full price after applying a coupon.',
                'severity' => Defect::SEVERITY_CRITICAL,
            ])
            ->assertRedirect(route('projects.defects.index', $project));

        $defect = Defect::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertSame('DEF-1', $defect->displayKey());
        $this->assertSame(Defect::STATUS_OPEN, $defect->status);
        $this->assertSame($project->owner_id, $defect->reported_by);

        $audit = AuditLog::query()
            ->where('action', 'defect.created')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame('Checkout total ignores discounts', $audit->after_values['title']);
        $this->assertSame(Defect::STATUS_OPEN, $audit->after_values['status']);
        $this->assertSame(Defect::SEVERITY_CRITICAL, $audit->after_values['severity']);
        $this->assertSame($project->id, $audit->after_values['project_id']);
    }

    public function test_defect_keys_are_scoped_per_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $firstDefect = $this->reportDefect($firstProject->owner, $firstProject, 'First project defect');
        $secondDefect = $this->reportDefect($secondProject->owner, $secondProject, 'Second project defect');

        $this->assertSame('DEF-1', $firstDefect->displayKey());
        $this->assertSame('DEF-1', $secondDefect->displayKey());
    }

    public function test_a_defect_can_be_traced_to_a_result_from_the_same_project(): void
    {
        $project = Project::factory()->create();
        $result = $this->createResult($project);

        $this->actingAs($project->owner)
            ->post(route('projects.defects.store', $project), [
                'title' => 'Failed checkout flow',
                'severity' => Defect::SEVERITY_MAJOR,
                'test_result_id' => $result->id,
            ])
            ->assertRedirect();

        $defect = Defect::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertSame($result->id, $defect->test_result_id);
    }

    public function test_a_result_from_another_project_is_rejected(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $foreignResult = $this->createResult($firstProject);

        $this->actingAs($secondProject->owner)
            ->post(route('projects.defects.store', $secondProject), [
                'title' => 'Cross project result',
                'severity' => Defect::SEVERITY_MAJOR,
                'test_result_id' => $foreignResult->id,
            ])
            ->assertSessionHasErrors('test_result_id');

        $this->assertDatabaseCount('defects', 0);
    }

    public function test_defect_validation_rejects_unknown_values(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post(route('projects.defects.store', $project), [
                'title' => 'Unknown severity',
                'severity' => 'urgent',
            ])
            ->assertSessionHasErrors('severity');

        $this->actingAs($project->owner)
            ->post(route('projects.defects.store', $project), [
                'severity' => Defect::SEVERITY_MAJOR,
            ])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('defects', 0);
    }

    public function test_member_with_the_defect_permission_can_report_and_transition(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->createMember($project, 'PROJECT_ADMIN');

        $this->actingAs($admin)
            ->post(route('projects.defects.store', $project), [
                'title' => 'Member defect',
                'severity' => Defect::SEVERITY_MINOR,
            ])
            ->assertRedirect();

        $defect = Defect::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => Defect::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect();

        $this->assertSame(Defect::STATUS_IN_PROGRESS, $defect->refresh()->status);
    }

    public function test_member_without_the_defect_permission_is_forbidden(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $member = $this->createMember($project, 'PROJECT_VIEWER');
        $defect = Defect::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->get(route('projects.defects.index', $project))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('projects.defects.store', $project), [
                'title' => 'Blocked defect',
                'severity' => Defect::SEVERITY_MAJOR,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => Defect::STATUS_CLOSED,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('defects', ['title' => 'Blocked defect']);
        $this->assertSame(Defect::STATUS_OPEN, $defect->refresh()->status);
    }

    public function test_system_admin_reads_defects_but_cannot_report_or_transition(): void
    {
        $project = Project::factory()->create();
        $defect = Defect::factory()->create(['project_id' => $project->id]);
        $systemAdmin = $this->createSystemAdmin(withViewPermission: true);

        $this->actingAs($systemAdmin)
            ->get(route('projects.defects.index', $project))
            ->assertOk()
            ->assertSee($defect->displayKey());

        $this->actingAs($systemAdmin)
            ->post(route('projects.defects.store', $project), [
                'title' => 'System defect',
                'severity' => Defect::SEVERITY_MAJOR,
            ])
            ->assertForbidden();

        $this->actingAs($systemAdmin)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => Defect::STATUS_RESOLVED,
            ])
            ->assertForbidden();

        $this->assertSame(Defect::STATUS_OPEN, $defect->refresh()->status);
        $this->assertDatabaseMissing('defects', ['title' => 'System defect']);
    }

    public function test_editing_a_defect_audits_the_change(): void
    {
        $project = Project::factory()->create();
        $defect = Defect::factory()->create([
            'project_id' => $project->id,
            'title' => 'Original title',
            'severity' => Defect::SEVERITY_MINOR,
        ]);

        $this->actingAs($project->owner)
            ->patch(route('projects.defects.update', [$project, $defect]), [
                'title' => 'Rewritten title',
                'description' => 'More detail.',
                'severity' => Defect::SEVERITY_BLOCKER,
                'test_result_id' => null,
            ])
            ->assertRedirect();

        $defect->refresh();

        $this->assertSame('Rewritten title', $defect->title);
        $this->assertSame(Defect::SEVERITY_BLOCKER, $defect->severity);

        $audit = AuditLog::query()
            ->where('action', 'defect.updated')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame('Original title', $audit->before_values['title']);
        $this->assertSame('Rewritten title', $audit->after_values['title']);
        $this->assertSame(Defect::SEVERITY_BLOCKER, $audit->after_values['severity']);
    }

    public function test_transitioning_a_defect_audits_the_status_change(): void
    {
        $project = Project::factory()->create();
        $defect = Defect::factory()->open()->create(['project_id' => $project->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => Defect::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect();

        $this->assertSame(Defect::STATUS_IN_PROGRESS, $defect->refresh()->status);

        $audit = AuditLog::query()
            ->where('action', 'defect.status_changed')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame(Defect::STATUS_OPEN, $audit->before_values['status']);
        $this->assertSame(Defect::STATUS_IN_PROGRESS, $audit->after_values['status']);
    }

    public function test_unknown_transition_status_is_rejected(): void
    {
        $project = Project::factory()->create();
        $defect = Defect::factory()->open()->create(['project_id' => $project->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => 'archived',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(Defect::STATUS_OPEN, $defect->refresh()->status);
    }

    public function test_transitioning_to_the_current_status_records_no_audit(): void
    {
        $project = Project::factory()->create();
        $defect = Defect::factory()->open()->create(['project_id' => $project->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.defects.status.update', [$project, $defect]), [
                'status' => Defect::STATUS_OPEN,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'defect.status_changed']);
    }

    public function test_defect_bindings_are_scoped_to_the_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $defect = Defect::factory()->create(['project_id' => $firstProject->id]);

        $this->actingAs($secondProject->owner)
            ->patch(route('projects.defects.update', [$secondProject, $defect]), [
                'title' => 'Hijacked title',
                'severity' => Defect::SEVERITY_MAJOR,
            ])
            ->assertNotFound();

        $this->actingAs($secondProject->owner)
            ->patch(route('projects.defects.status.update', [$secondProject, $defect]), [
                'status' => Defect::STATUS_CLOSED,
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijacked title', $defect->refresh()->title);
    }

    public function test_index_shows_defects_with_their_linked_result(): void
    {
        $project = Project::factory()->create();
        $result = $this->createResult($project);
        Defect::factory()->forResult($result)->create([
            'project_id' => $project->id,
            'title' => 'Visible defect',
        ]);

        $this->actingAs($project->owner)
            ->get(route('projects.defects.index', $project))
            ->assertOk()
            ->assertSee('DEF-1')
            ->assertSee('Visible defect')
            ->assertSee('Traced to');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.defects.index', $project))
            ->assertRedirect(route('login'));
    }

    private function reportDefect(User $actor, Project $project, string $title): Defect
    {
        $this->actingAs($actor)
            ->post(route('projects.defects.store', $project), [
                'title' => $title,
                'severity' => Defect::SEVERITY_MAJOR,
            ])
            ->assertRedirect();

        return Defect::query()
            ->where('project_id', $project->id)
            ->where('title', $title)
            ->firstOrFail();
    }

    private function createResult(Project $project): TestResult
    {
        $run = TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
        ]);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        return TestResult::factory()->create([
            'test_run_case_id' => $runCase->id,
            'recorded_by' => $project->owner_id,
        ]);
    }

    private function createMember(Project $project, string $roleCode): User
    {
        $member = User::factory()->create();

        $project->members()->attach($member->id, [
            'role_id' => $this->projectRole($roleCode)->id,
        ]);

        return $member;
    }

    private function createSystemAdmin(bool $withViewPermission): User
    {
        $role = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->where('scope', 'system')
            ->firstOrFail();

        $permissionId = Permission::query()->where('code', 'defects.view')->value('id');

        if ($withViewPermission) {
            $role->permissions()->syncWithoutDetaching([$permissionId]);
        } else {
            $role->permissions()->detach([$permissionId]);
        }

        return User::factory()->create(['role_id' => $role->id]);
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
