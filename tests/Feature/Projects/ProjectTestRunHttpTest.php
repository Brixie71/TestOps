<?php

namespace Tests\Feature\Projects;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestRun;
use App\Models\TestRunCase;
use App\Models\TestSuite;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTestRunHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_owner_starts_a_run_and_freezes_the_case_snapshots(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $case = $this->createCase($project, $suite, 'Checkout total', [
            ['action' => 'Open the cart', 'expected_result' => 'Cart loads'],
        ]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.store', $project), [
                'name' => 'Release 1 smoke',
                'case_ids' => [$case->id],
            ])
            ->assertRedirect();

        $run = TestRun::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertSame('TR-1', $run->displayKey());
        $this->assertSame(TestRun::STATUS_OPEN, $run->status);
        $this->assertNotNull($run->started_at);

        $snapshot = $run->cases()->firstOrFail();

        $this->assertSame($case->id, $snapshot->test_case_id);
        $this->assertSame(1, $snapshot->position);
        $this->assertSame('Checkout total', $snapshot->title);
        $this->assertSame(TestCaseModel::SEVERITY_MAJOR, $snapshot->severity);
        $this->assertSame(
            [['action' => 'Open the cart', 'expected_result' => 'Cart loads']],
            $snapshot->steps,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test_run.started',
            'actor_user_id' => $project->owner_id,
        ]);
    }

    public function test_run_snapshots_survive_later_case_edits(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $case = $this->createCase($project, $suite, 'Original title', [
            ['action' => 'Original step', 'expected_result' => null],
        ]);

        $this->startRun($project->owner, $project, [$case->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.test-cases.update', [$project, $case]), [
                'title' => 'Rewritten title',
                'status' => TestCaseModel::STATUS_ACTIVE,
                'severity' => TestCaseModel::SEVERITY_CRITICAL,
                'steps' => [
                    ['action' => 'Rewritten step', 'expected_result' => 'New expectation'],
                ],
            ])
            ->assertRedirect();

        $snapshot = TestRunCase::query()->where('test_case_id', $case->id)->firstOrFail();

        $this->assertSame('Original title', $snapshot->title);
        $this->assertSame(TestCaseModel::SEVERITY_MAJOR, $snapshot->severity);
        $this->assertSame([['action' => 'Original step', 'expected_result' => null]], $snapshot->steps);
    }

    public function test_run_keys_are_scoped_per_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $firstCase = $this->createCase(
            $firstProject,
            TestSuite::factory()->create(['project_id' => $firstProject->id]),
            'First project case',
            [],
        );
        $secondCase = $this->createCase(
            $secondProject,
            TestSuite::factory()->create(['project_id' => $secondProject->id]),
            'Second project case',
            [],
        );

        $firstRun = $this->startRun($firstProject->owner, $firstProject, [$firstCase->id]);
        $secondRun = $this->startRun($secondProject->owner, $secondProject, [$secondCase->id]);

        $this->assertSame('TR-1', $firstRun->displayKey());
        $this->assertSame('TR-1', $secondRun->displayKey());
    }

    public function test_project_admin_member_can_start_a_run(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->createMember($project, 'PROJECT_ADMIN');
        $case = $this->createCase(
            $project,
            TestSuite::factory()->create(['project_id' => $project->id]),
            'Member case',
            [],
            $admin,
        );

        $this->actingAs($admin)
            ->post(route('projects.test-runs.store', $project), [
                'name' => 'Admin run',
                'case_ids' => [$case->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('test_runs', ['project_id' => $project->id, 'name' => 'Admin run']);
    }

    public function test_member_without_the_run_permission_is_forbidden(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $member = $this->createMember($project, 'PROJECT_VIEWER');
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $case = TestCaseModel::factory()->create([
            'project_id' => $project->id,
            'test_suite_id' => $suite->id,
        ]);

        $this->actingAs($member)
            ->get(route('projects.test-runs.index', $project))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('projects.test-runs.store', $project), [
                'name' => 'Blocked run',
                'case_ids' => [$case->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('test_runs', ['name' => 'Blocked run']);
    }

    public function test_system_admin_reads_runs_but_cannot_start_or_finish_them(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $case = $this->createCase($project, $suite, 'Visible case', []);
        $run = TestRun::factory()->create(['project_id' => $project->id]);
        TestRunCase::factory()->create([
            'test_run_id' => $run->id,
            'test_case_id' => $case->id,
        ]);

        $systemAdmin = $this->createSystemAdmin(withViewPermission: true);

        $this->actingAs($systemAdmin)
            ->get(route('projects.test-runs.index', $project))
            ->assertOk();

        $this->actingAs($systemAdmin)
            ->get(route('projects.test-runs.show', [$project, $run]))
            ->assertOk();

        $this->actingAs($systemAdmin)
            ->post(route('projects.test-runs.store', $project), [
                'name' => 'System run',
                'case_ids' => [$case->id],
            ])
            ->assertForbidden();

        $this->actingAs($systemAdmin)
            ->patch(route('projects.test-runs.complete', [$project, $run]))
            ->assertForbidden();

        $this->assertSame(TestRun::STATUS_OPEN, $run->refresh()->status);
    }

    public function test_cases_from_another_project_are_rejected(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $foreignCase = $this->createCase(
            $firstProject,
            TestSuite::factory()->create(['project_id' => $firstProject->id]),
            'Foreign case',
            [],
        );

        $this->actingAs($secondProject->owner)
            ->post(route('projects.test-runs.store', $secondProject), [
                'name' => 'Cross project',
                'case_ids' => [$foreignCase->id],
            ])
            ->assertSessionHasErrors('case_ids.0');

        $this->assertDatabaseMissing('test_runs', ['name' => 'Cross project']);
    }

    public function test_starting_a_run_requires_at_least_one_case(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.store', $project), ['name' => 'Empty run'])
            ->assertSessionHasErrors('case_ids');
    }

    public function test_completing_a_run_sets_the_finish_time_and_is_audited(): void
    {
        $project = Project::factory()->create();
        $case = $this->createCase(
            $project,
            TestSuite::factory()->create(['project_id' => $project->id]),
            'Completion case',
            [],
        );
        $run = $this->startRun($project->owner, $project, [$case->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.test-runs.complete', [$project, $run]))
            ->assertRedirect();

        $run->refresh();

        $this->assertSame(TestRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->completed_at);

        $audit = AuditLog::query()
            ->where('action', 'test_run.completed')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame('open', $audit->before_values['status']);
        $this->assertSame('completed', $audit->after_values['status']);
    }

    public function test_a_run_can_be_aborted(): void
    {
        $project = Project::factory()->create();
        $case = $this->createCase(
            $project,
            TestSuite::factory()->create(['project_id' => $project->id]),
            'Abort case',
            [],
        );
        $run = $this->startRun($project->owner, $project, [$case->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.test-runs.abort', [$project, $run]))
            ->assertRedirect();

        $this->assertSame(TestRun::STATUS_ABORTED, $run->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test_run.aborted',
            'actor_user_id' => $project->owner_id,
        ]);
    }

    public function test_finished_runs_cannot_be_finished_again(): void
    {
        $project = Project::factory()->create();
        $case = $this->createCase(
            $project,
            TestSuite::factory()->create(['project_id' => $project->id]),
            'Settled case',
            [],
        );
        $run = $this->startRun($project->owner, $project, [$case->id]);

        $this->actingAs($project->owner)
            ->patch(route('projects.test-runs.complete', [$project, $run]))
            ->assertRedirect();

        $this->actingAs($project->owner)
            ->patch(route('projects.test-runs.abort', [$project, $run]))
            ->assertSessionHasErrors('status');

        $this->assertSame(TestRun::STATUS_COMPLETED, $run->refresh()->status);
    }

    public function test_case_delete_is_refused_while_a_run_references_it(): void
    {
        $project = Project::factory()->create();
        $case = $this->createCase(
            $project,
            TestSuite::factory()->create(['project_id' => $project->id]),
            'Referenced case',
            [],
        );

        $this->startRun($project->owner, $project, [$case->id]);

        $this->actingAs($project->owner)
            ->delete(route('projects.test-cases.destroy', [$project, $case]))
            ->assertSessionHasErrors('test_case');

        $this->assertDatabaseHas('test_cases', ['id' => $case->id]);
    }

    public function test_run_bindings_are_scoped_to_the_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $case = $this->createCase(
            $firstProject,
            TestSuite::factory()->create(['project_id' => $firstProject->id]),
            'Scoped case',
            [],
        );
        $run = $this->startRun($firstProject->owner, $firstProject, [$case->id]);

        $this->actingAs($secondProject->owner)
            ->get(route('projects.test-runs.show', [$secondProject, $run]))
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.test-runs.index', $project))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  list<array{action: string, expected_result: ?string}>  $steps
     */
    private function createCase(Project $project, TestSuite $suite, string $title, array $steps, ?User $actor = null): TestCaseModel
    {
        $this->actingAs($actor ?? $project->owner)
            ->post(route('projects.test-cases.store', $project), [
                'test_suite_id' => $suite->id,
                'title' => $title,
                'status' => TestCaseModel::STATUS_ACTIVE,
                'severity' => TestCaseModel::SEVERITY_MAJOR,
                'steps' => $steps,
            ])
            ->assertRedirect();

        return TestCaseModel::query()
            ->where('project_id', $project->id)
            ->where('title', $title)
            ->firstOrFail();
    }

    /**
     * @param  list<int>  $caseIds
     */
    private function startRun(User $actor, Project $project, array $caseIds): TestRun
    {
        $this->actingAs($actor)
            ->post(route('projects.test-runs.store', $project), [
                'name' => 'Run '.implode('-', $caseIds),
                'case_ids' => $caseIds,
            ])
            ->assertRedirect();

        return TestRun::query()
            ->where('project_id', $project->id)
            ->orderByDesc('sequence')
            ->firstOrFail();
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

        $permissionId = Permission::query()->where('code', 'test_runs.view')->value('id');

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
