<?php

namespace Tests\Feature\Dashboard;

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

class DashboardHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_owner_sees_each_scoped_metric(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        $run = TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
        ]);
        $this->createResult($run, now()->subDay());

        $this->createDefect($project, 1, Defect::STATUS_OPEN);
        $this->createDefect($project, 2, Defect::STATUS_IN_PROGRESS);
        $this->createDefect($project, 3, Defect::STATUS_RESOLVED);

        $values = $this->metricsFor($owner);

        $this->assertSame(1, $values['Projects']);
        $this->assertSame(1, $values['Open runs']);
        $this->assertSame(1, $values['Results (7 days)']);
        $this->assertSame(2, $values['Open defects']);
    }

    public function test_metrics_exclude_other_projects_and_inactive_records(): void
    {
        $visible = Project::factory()->create();
        $hidden = Project::factory()->create();

        $visibleRun = TestRun::factory()->create([
            'project_id' => $visible->id,
            'created_by' => $visible->owner_id,
        ]);
        $this->createResult($visibleRun, now()->subDay());
        // Outside the seven-day window and no longer open: neither counts.
        $this->createResult($visibleRun, now()->subDays(8));
        TestRun::factory()->completed()->create([
            'project_id' => $visible->id,
            'created_by' => $visible->owner_id,
        ]);

        $hiddenRun = TestRun::factory()->create([
            'project_id' => $hidden->id,
            'created_by' => $hidden->owner_id,
        ]);
        $this->createResult($hiddenRun, now()->subDay());
        foreach (range(1, 3) as $sequence) {
            $this->createDefect($hidden, $sequence);
        }

        $values = $this->metricsFor($visible->owner);

        $this->assertSame(1, $values['Projects']);
        $this->assertSame(1, $values['Open runs']);
        $this->assertSame(1, $values['Results (7 days)']);
        $this->assertSame(0, $values['Open defects']);
    }

    public function test_project_viewer_sees_only_the_projects_card(): void
    {
        $project = Project::factory()->create();
        $viewer = User::factory()->create();
        $this->joinProject($project, $viewer, $this->createProjectRole('PROJECT_VIEWER', ['projects.view']));

        $this->createDefect($project, 1);

        $values = $this->metricsFor($viewer);

        $this->assertSame(1, $values['Projects']);
        $this->assertArrayNotHasKey('Open runs', $values);
        $this->assertArrayNotHasKey('Results (7 days)', $values);
        $this->assertArrayNotHasKey('Open defects', $values);
    }

    public function test_member_with_only_test_run_access_sees_no_defect_card(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $this->joinProject($project, $member, $this->createProjectRole('RUN_VIEWER', ['test_runs.view']));

        TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
        ]);
        $this->createDefect($project, 1);

        $values = $this->metricsFor($member);

        $this->assertSame(0, $values['Projects']);
        $this->assertSame(1, $values['Open runs']);
        $this->assertSame(0, $values['Results (7 days)']);
        $this->assertArrayNotHasKey('Open defects', $values);
    }

    public function test_user_with_no_projects_sees_zero_projects_and_no_testing_cards(): void
    {
        Project::factory()->count(2)->create();

        $values = $this->metricsFor(User::factory()->create());

        $this->assertSame(0, $values['Projects']);
        $this->assertArrayNotHasKey('Open runs', $values);
        $this->assertArrayNotHasKey('Open defects', $values);
    }

    public function test_system_admin_sees_every_project(): void
    {
        Project::factory()->count(3)->create();

        $role = Role::query()->where('code', 'SYSTEM_ADMIN')->where('scope', 'system')->firstOrFail();
        $systemAdmin = User::factory()->create(['role_id' => $role->id]);

        $values = $this->metricsFor($systemAdmin);

        $this->assertSame(3, $values['Projects']);
        $this->assertSame(0, $values['Open runs']);
        $this->assertSame(0, $values['Open defects']);
    }

    /**
     * @return array<string, int>
     */
    private function metricsFor(User $user): array
    {
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        /** @var array<string, int> $values */
        $values = collect($response->viewData('metrics'))->pluck('value', 'label')->all();

        return $values;
    }

    private function createResult(TestRun $run, \DateTimeInterface $recordedAt): TestResult
    {
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id]);

        return TestResult::factory()->create([
            'test_run_case_id' => $runCase->id,
            'recorded_by' => $run->created_by,
            'recorded_at' => $recordedAt,
        ]);
    }

    private function joinProject(Project $project, User $member, Role $role): void
    {
        $project->members()->attach($member->id, ['role_id' => $role->id]);
    }

    private function createDefect(Project $project, int $sequence, string $status = Defect::STATUS_OPEN): Defect
    {
        return Defect::factory()->create([
            'project_id' => $project->id,
            'sequence' => $sequence,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private function createProjectRole(string $code, array $permissionCodes): Role
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => 'project'],
            [
                'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                'description' => 'Test helper project role.',
            ],
        );

        $role->permissions()->sync(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all(),
        );

        return $role;
    }
}
