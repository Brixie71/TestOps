<?php

namespace Tests\Feature\Projects;

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

class TestRunReportHttpTest extends TestCase
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
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id]);

        $this->get(route('projects.test-runs.report', [$project, $run]))
            ->assertRedirect(route('login'));
    }

    public function test_member_without_the_run_view_permission_is_forbidden(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id]);
        $member = $this->memberWith($project, []);

        $this->actingAs($member)
            ->get(route('projects.test-runs.report', [$project, $run]))
            ->assertForbidden();
    }

    public function test_run_from_another_project_is_not_found(): void
    {
        $project = Project::factory()->create();
        $foreignRun = TestRun::factory()->create();

        $this->actingAs($project->owner)
            ->get(route('projects.test-runs.report', [$project, $foreignRun]))
            ->assertNotFound();
    }

    public function test_report_shows_frozen_cases_steps_results_and_summary(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create([
            'project_id' => $project->id,
            'sequence' => 1,
            'name' => 'Release 1 regression',
        ]);

        $passedCase = TestRunCase::factory()->create([
            'test_run_id' => $run->id,
            'position' => 1,
            'title' => 'Checkout total',
            'severity' => 'critical',
            'steps' => [
                ['action' => 'Open the cart', 'expected_result' => 'Cart loads'],
                ['action' => 'Add an item', 'expected_result' => null],
            ],
        ]);

        TestResult::factory()->create([
            'test_run_case_id' => $passedCase->id,
            'status' => TestResult::STATUS_PASSED,
            'notes' => 'Total matched',
        ]);

        TestRunCase::factory()->create([
            'test_run_id' => $run->id,
            'position' => 2,
            'title' => 'Refund flow',
            'steps' => [],
        ]);

        $this->actingAs($project->owner)
            ->get(route('projects.test-runs.report', [$project, $run]))
            ->assertOk()
            ->assertSeeText('TR-1')
            ->assertSeeText('Release 1 regression')
            ->assertSeeText('Checkout total')
            ->assertSeeText('Open the cart')
            ->assertSeeText('Cart loads')
            ->assertSeeText('Add an item')
            ->assertSeeText('Total matched')
            ->assertSeeText('2 cases')
            ->assertSeeText('1 passed')
            ->assertSeeText('1 not run')
            ->assertDontSee('Complete run')
            ->assertDontSee('Save result');
    }

    public function test_run_page_links_to_the_report(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id]);

        $this->actingAs($project->owner)
            ->get(route('projects.test-runs.show', [$project, $run]))
            ->assertOk()
            ->assertSee(route('projects.test-runs.report', [$project, $run]), false);
    }

    public function test_defects_are_listed_only_with_the_defect_view_permission(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id, 'sequence' => 1]);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id, 'position' => 1]);
        $result = TestResult::factory()->failed()->create(['test_run_case_id' => $runCase->id]);

        Defect::factory()->create([
            'project_id' => $project->id,
            'test_result_id' => $result->id,
            'sequence' => 7,
            'title' => 'Charge shows raw SQL error',
        ]);

        $this->actingAs($this->memberWith($project, ['test_runs.view']))
            ->get(route('projects.test-runs.report', [$project, $run]))
            ->assertOk()
            ->assertDontSeeText('DEF-7')
            ->assertDontSeeText('Charge shows raw SQL error');

        $this->actingAs($this->memberWith($project, ['test_runs.view', 'defects.view']))
            ->get(route('projects.test-runs.report', [$project, $run]))
            ->assertOk()
            ->assertSeeText('DEF-7')
            ->assertSeeText('Charge shows raw SQL error');
    }

    public function test_csv_export_streams_one_row_per_frozen_case(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create([
            'project_id' => $project->id,
            'sequence' => 4,
            'name' => 'Release 4',
        ]);

        $case = TestRunCase::factory()->create([
            'test_run_id' => $run->id,
            'position' => 1,
            'title' => 'Cart total, with tax',
            'severity' => 'major',
            'steps' => [
                ['action' => 'Open the cart', 'expected_result' => 'Cart loads'],
                ['action' => 'Add an item', 'expected_result' => null],
            ],
        ]);

        TestResult::factory()->failed()->create([
            'test_run_case_id' => $case->id,
            'notes' => 'Showed "0.00", not 12.50',
        ]);

        TestRunCase::factory()->create([
            'test_run_id' => $run->id,
            'position' => 2,
            'title' => 'Refund flow',
            'steps' => [],
        ]);

        $response = $this->actingAs($project->owner)
            ->get(route('projects.test-runs.report.export', [$project, $run]));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'run-TR-4-',
            (string) $response->headers->get('content-disposition'),
        );

        $rows = array_map(
            'str_getcsv',
            array_values(array_filter(explode("\n", trim($response->streamedContent())))),
        );

        $this->assertSame(
            ['Run', 'Run name', 'Run status', 'Position', 'Case title', 'Severity', 'Steps', 'Result', 'Notes', 'Recorded by', 'Recorded at', 'Defects'],
            $rows[0],
        );
        $this->assertCount(3, $rows, 'Header plus one row per frozen case.');
        $this->assertSame('TR-4', $rows[1][0]);
        $this->assertSame('1. Open the cart -> Cart loads; 2. Add an item', $rows[1][6]);
        $this->assertSame('Failed', $rows[1][7]);
        $this->assertSame('Showed "0.00", not 12.50', $rows[1][8]);
        $this->assertSame('Not run', $rows[2][7]);
        $this->assertSame('', $rows[2][6]);
    }

    public function test_csv_export_omits_the_defect_column_without_the_defect_view_permission(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id, 'sequence' => 1]);
        $runCase = TestRunCase::factory()->create(['test_run_id' => $run->id, 'position' => 1]);
        $result = TestResult::factory()->failed()->create(['test_run_case_id' => $runCase->id]);

        Defect::factory()->create([
            'project_id' => $project->id,
            'test_result_id' => $result->id,
            'sequence' => 1,
            'title' => 'Charge shows raw SQL error',
        ]);

        $response = $this->actingAs($this->memberWith($project, ['test_runs.view']))
            ->get(route('projects.test-runs.report.export', [$project, $run]));

        $response->assertOk();

        $rows = array_map(
            'str_getcsv',
            array_values(array_filter(explode("\n", trim($response->streamedContent())))),
        );

        $this->assertSame('Recorded at', $rows[0][10]);
        $this->assertCount(11, $rows[0]);
        $this->assertStringNotContainsString('DEF-1', $response->streamedContent());
        $this->assertStringNotContainsString('Charge shows raw SQL error', $response->streamedContent());
    }

    public function test_csv_export_requires_the_run_view_permission(): void
    {
        $project = Project::factory()->create();
        $run = TestRun::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->memberWith($project, []))
            ->get(route('projects.test-runs.report.export', [$project, $run]))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function memberWith(Project $project, array $permissionCodes): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'REPORT_'.md5(implode('|', $permissionCodes)), 'scope' => 'project'],
            [
                'name' => 'Report test role',
                'description' => 'Test helper project role.',
            ],
        );

        $role->permissions()->sync(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all(),
        );

        $member = User::factory()->create();

        $project->members()->attach($member->id, ['role_id' => $role->id]);

        return $member;
    }
}
