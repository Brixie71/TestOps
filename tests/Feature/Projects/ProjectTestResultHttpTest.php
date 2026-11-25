<?php

namespace Tests\Feature\Projects;

use App\Models\AuditLog;
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

class ProjectTestResultHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_owner_records_a_result_on_an_open_run(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_PASSED,
                'notes' => 'Verified on staging.',
            ])
            ->assertRedirect(route('projects.test-runs.show', [$project, $run]));

        $result = TestResult::query()->where('test_run_case_id', $runCase->id)->firstOrFail();

        $this->assertSame(TestResult::STATUS_PASSED, $result->status);
        $this->assertSame('Verified on staging.', $result->notes);
        $this->assertSame($project->owner_id, $result->recorded_by);
        $this->assertNotNull($result->recorded_at);

        $audit = AuditLog::query()
            ->where('action', 'test_result.recorded')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame('passed', $audit->after_values['status']);
        $this->assertSame($run->id, $audit->after_values['test_run_id']);
        $this->assertSame($runCase->id, $audit->after_values['test_run_case_id']);
    }

    public function test_re_recording_replaces_the_result_and_audits_the_change(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_PASSED,
            ])
            ->assertRedirect();

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_FAILED,
                'notes' => 'Regression found on retest.',
            ])
            ->assertRedirect();

        $this->assertSame(1, TestResult::query()->where('test_run_case_id', $runCase->id)->count());

        $result = TestResult::query()->where('test_run_case_id', $runCase->id)->firstOrFail();

        $this->assertSame(TestResult::STATUS_FAILED, $result->status);

        $audit = AuditLog::query()
            ->where('action', 'test_result.recorded')
            ->where('actor_user_id', $project->owner_id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('passed', $audit->before_values['status']);
        $this->assertSame('failed', $audit->after_values['status']);
    }

    public function test_result_status_is_validated(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('test_results', 0);
    }

    public function test_results_cannot_be_recorded_on_a_finished_run(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->completed()->create(['project_id' => $project->id]);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_PASSED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('test_results', 0);
    }

    public function test_a_run_case_from_another_run_is_rejected(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $firstRun = $this->createRun($firstProject);
        $secondRun = $this->createRun($secondProject);
        $foreignRunCase = TestRunCase::factory()->create(['test_run_id' => $firstRun->id]);

        $this->actingAs($secondProject->owner)
            ->post(route('projects.test-runs.results.store', [$secondProject, $secondRun]), [
                'test_run_case_id' => $foreignRunCase->id,
                'status' => TestResult::STATUS_PASSED,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('test_results', 0);
    }

    public function test_member_without_the_run_permission_is_forbidden(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $member = $this->createMember($project, 'PROJECT_VIEWER');
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($member)
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_PASSED,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('test_results', 0);
    }

    public function test_system_admin_cannot_record_results(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->actingAs($this->createSystemAdmin(withViewPermission: true))
            ->post(route('projects.test-runs.results.store', [$project, $run]), [
                'test_run_case_id' => $runCase->id,
                'status' => TestResult::STATUS_PASSED,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('test_results', 0);
    }

    public function test_run_page_shows_the_recorded_result_and_notes(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        TestResult::factory()->failed()->create([
            'test_run_case_id' => $runCase->id,
            'notes' => 'Checkout button unresponsive.',
            'recorded_by' => $project->owner_id,
        ]);

        $this->actingAs($project->owner)
            ->get(route('projects.test-runs.show', [$project, $run]))
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee('Checkout button unresponsive.');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();
        $run = $this->createRun($project);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        $this->post(route('projects.test-runs.results.store', [$project, $run]), [
            'test_run_case_id' => $runCase->id,
            'status' => TestResult::STATUS_PASSED,
        ])->assertRedirect(route('login'));
    }

    private function createRun(Project $project): TestRun
    {
        return TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
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
