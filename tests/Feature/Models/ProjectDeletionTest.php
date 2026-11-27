<?php

namespace Tests\Feature\Models;

use App\Models\Defect;
use App\Models\Project;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestRun;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_with_testing_data_cannot_be_deleted(): void
    {
        $project = Project::factory()->create();

        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        TestCaseModel::factory()->create([
            'project_id' => $project->id,
            'test_suite_id' => $suite->id,
        ]);
        TestRun::factory()->create(['project_id' => $project->id]);
        Defect::factory()->create(['project_id' => $project->id, 'sequence' => 1]);

        try {
            $project->delete();
            $this->fail('Deleting a project with testing data should be refused.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'cannot be deleted while it holds testing data (1 test suite, 1 test case, 1 test run, 1 defect)',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('Retain the project to preserve that history.', $exception->getMessage());
        }

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('test_suites', ['project_id' => $project->id]);
        $this->assertDatabaseHas('test_cases', ['project_id' => $project->id]);
        $this->assertDatabaseHas('test_runs', ['project_id' => $project->id]);
        $this->assertDatabaseHas('defects', ['project_id' => $project->id]);
    }

    public function test_project_without_testing_data_can_be_deleted(): void
    {
        $project = Project::factory()->create();

        $project->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
