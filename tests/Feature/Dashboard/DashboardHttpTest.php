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
use Illuminate\Testing\TestResponse;
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

        $response = $this->dashboardFor(User::factory()->create());

        $values = collect($response->viewData('metrics'))->pluck('value', 'label')->all();

        $this->assertSame(0, $values['Projects']);
        $this->assertArrayNotHasKey('Open runs', $values);
        $this->assertArrayNotHasKey('Open defects', $values);

        $this->assertCount(0, $response->viewData('projects'));
        $this->assertCount(0, $response->viewData('openRuns'));
        $this->assertCount(0, $response->viewData('openDefects'));
        $response->assertSee('No projects yet')
            ->assertDontSee('Runs in progress')
            ->assertDontSee('Open defects');
    }

    public function test_project_list_orders_by_unresolved_defects_then_name_and_is_capped_at_eight(): void
    {
        $owner = User::factory()->create();

        $busy = Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Busy']);
        $tied = Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Tied']);
        Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Quiet']);
        Project::factory()->count(7)->create(['owner_id' => $owner->id]);

        $this->createDefect($busy, 1);
        $this->createDefect($busy, 2);
        $this->createDefect($tied, 1);
        $this->createDefect($tied, 2);

        $projects = $this->dashboardFor($owner)->viewData('projects');

        $this->assertCount(8, $projects);
        $this->assertSame(['Busy', 'Tied'], $projects->take(2)->pluck('name')->all());
    }

    public function test_runs_list_is_scoped_to_visible_projects_orders_by_recency_and_is_capped_at_five(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $hidden = Project::factory()->create();

        $runs = collect(range(1, 6))->map(fn (int $minutes) => TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'started_at' => now()->subMinutes($minutes),
        ]));

        TestRun::factory()->create([
            'project_id' => $hidden->id,
            'created_by' => $hidden->owner_id,
            'started_at' => now(),
        ]);

        $newest = $runs->first();
        $runCases = TestRunCase::factory()->count(3)->create(['test_run_id' => $newest->id]);
        TestResult::factory()->create([
            'test_run_case_id' => $runCases->first()->id,
            'recorded_by' => $owner->id,
            'recorded_at' => now(),
        ]);

        $listed = $this->dashboardFor($owner)->viewData('openRuns');

        $this->assertCount(5, $listed);
        $this->assertSame($runs->take(5)->pluck('id')->all(), $listed->pluck('id')->all());
        $this->assertSame(1, $listed->first()->recorded_count);
        $this->assertSame(3, $listed->first()->cases_count);
    }

    public function test_defects_list_is_scoped_orders_by_severity_then_recency_and_is_capped_at_five(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $hidden = Project::factory()->create();

        $blocker = $this->createDefect($project, 1, Defect::STATUS_OPEN, Defect::SEVERITY_BLOCKER, now()->subHours(3));
        $critical = $this->createDefect($project, 2, Defect::STATUS_IN_PROGRESS, Defect::SEVERITY_CRITICAL, now()->subHour());
        $majorNewest = $this->createDefect($project, 3, Defect::STATUS_OPEN, Defect::SEVERITY_MAJOR, now());
        $majorOld = $this->createDefect($project, 4, Defect::STATUS_OPEN, Defect::SEVERITY_MAJOR, now()->subHours(2));
        $minor = $this->createDefect($project, 5, Defect::STATUS_OPEN, Defect::SEVERITY_MINOR, now());
        $this->createDefect($project, 6, Defect::STATUS_OPEN, Defect::SEVERITY_TRIVIAL, now());
        $this->createDefect($project, 7, Defect::STATUS_RESOLVED, Defect::SEVERITY_BLOCKER, now());
        $this->createDefect($hidden, 1, Defect::STATUS_OPEN, Defect::SEVERITY_BLOCKER, now());

        $listed = $this->dashboardFor($owner)->viewData('openDefects');

        $this->assertCount(5, $listed);
        $this->assertSame(
            [$blocker->id, $critical->id, $majorNewest->id, $majorOld->id, $minor->id],
            $listed->pluck('id')->all(),
        );
        $this->assertSame($project->name, $listed->first()->project_name);
    }

    public function test_project_viewer_sees_their_project_but_no_testing_lists_or_columns(): void
    {
        $project = Project::factory()->create();
        $viewer = User::factory()->create();
        $this->joinProject($project, $viewer, $this->createProjectRole('PROJECT_VIEWER', ['projects.view']));

        TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
        ]);
        $this->createDefect($project, 1);

        $response = $this->dashboardFor($viewer);

        $this->assertSame([$project->id], $response->viewData('projects')->pluck('id')->all());
        $this->assertSame([], $response->viewData('runScopedProjectIds'));
        $this->assertSame([], $response->viewData('defectScopedProjectIds'));
        $this->assertFalse($response->viewData('showRuns'));
        $this->assertFalse($response->viewData('showDefects'));
        $response->assertSee($project->name)
            ->assertDontSee('Runs in progress')
            ->assertDontSee('Open defects');
    }

    public function test_project_row_shows_run_counts_only_when_the_run_ability_is_held(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $this->joinProject($project, $member, $this->createProjectRole('PROJECT_RUN_VIEWER', ['projects.view', 'test_runs.view']));

        TestRun::factory()->create([
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
        ]);
        $this->createDefect($project, 1);

        $response = $this->dashboardFor($member);

        $this->assertSame([$project->id], $response->viewData('runScopedProjectIds'));
        $this->assertSame([], $response->viewData('defectScopedProjectIds'));
        $this->assertSame(1, $response->viewData('projects')->first()->open_runs_count);
        $this->assertFalse($response->viewData('showDefects'));
    }

    public function test_owner_with_no_activity_sees_the_testing_list_empty_states(): void
    {
        $project = Project::factory()->create();

        $response = $this->dashboardFor($project->owner);

        $this->assertCount(0, $response->viewData('openRuns'));
        $this->assertCount(0, $response->viewData('openDefects'));
        $response->assertSee('No runs in progress.')
            ->assertSee('No open defects.');
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
        /** @var array<string, int> $values */
        $values = collect($this->dashboardFor($user)->viewData('metrics'))->pluck('value', 'label')->all();

        return $values;
    }

    private function dashboardFor(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk();
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

    private function createDefect(
        Project $project,
        int $sequence,
        string $status = Defect::STATUS_OPEN,
        string $severity = Defect::SEVERITY_MAJOR,
        ?\DateTimeInterface $createdAt = null,
    ): Defect {
        return Defect::factory()->create([
            'project_id' => $project->id,
            'sequence' => $sequence,
            'status' => $status,
            'severity' => $severity,
            'created_at' => $createdAt ?? now(),
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
