<?php

namespace Tests\Feature\Models;

use App\Models\Project;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_test_suite_belongs_to_a_project(): void
    {
        $project = Project::factory()->create();

        $suite = TestSuite::query()->create([
            'project_id' => $project->getKey(),
            'name' => 'Checkout regression',
            'description' => 'Payment and cart flows.',
        ]);

        $this->assertTrue($suite->project->is($project));
        $this->assertSame('Checkout regression', $suite->name);
        $this->assertSame('Payment and cart flows.', $suite->description);
    }

    public function test_a_project_lists_only_its_own_test_suites(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();

        TestSuite::query()->create(['project_id' => $project->getKey(), 'name' => 'Cart']);
        TestSuite::query()->create(['project_id' => $project->getKey(), 'name' => 'Checkout']);
        TestSuite::query()->create(['project_id' => $otherProject->getKey(), 'name' => 'Reporting']);

        $this->assertCount(2, $project->testSuites);
    }

    public function test_the_description_is_optional(): void
    {
        $project = Project::factory()->create();

        $suite = TestSuite::query()->create([
            'project_id' => $project->getKey(),
            'name' => 'Smoke',
        ]);

        $this->assertNull($suite->description);
    }

    public function test_deleting_a_project_deletes_its_test_suites(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::query()->create([
            'project_id' => $project->getKey(),
            'name' => 'Smoke',
        ]);

        $project->delete();

        $this->assertDatabaseMissing('test_suites', ['id' => $suite->getKey()]);
    }
}
