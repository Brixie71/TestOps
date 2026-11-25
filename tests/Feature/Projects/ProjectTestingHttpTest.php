<?php

namespace Tests\Feature\Projects;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestSuite;
use App\Models\User;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTestingHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();
    }

    public function test_owner_can_view_the_testing_workspace(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        TestCaseModel::factory()->create([
            'project_id' => $project->id,
            'test_suite_id' => $suite->id,
            'sequence' => 1,
        ]);

        $this->actingAs($project->owner)
            ->get(route('projects.testing.index', $project))
            ->assertOk()
            ->assertSee($suite->name)
            ->assertSee('TC-1');
    }

    public function test_project_admin_member_can_create_a_suite(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $admin = $this->createMember($project, 'PROJECT_ADMIN');

        $this->actingAs($admin)
            ->post(route('projects.test-suites.store', $project), [
                'name' => 'Checkout regression',
                'description' => 'Payment paths.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('test_suites', [
            'project_id' => $project->id,
            'name' => 'Checkout regression',
        ]);
    }

    public function test_member_without_the_testing_permission_is_forbidden(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $member = $this->createMember($project, 'PROJECT_VIEWER');

        $this->actingAs($member)
            ->get(route('projects.testing.index', $project))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('projects.test-suites.store', $project), ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_non_member_is_forbidden(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.testing.index', $project))
            ->assertForbidden();
    }

    public function test_system_admin_with_the_view_permission_reads_but_cannot_manage(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $systemAdmin = $this->createSystemAdmin(withViewPermission: true);

        $this->actingAs($systemAdmin)
            ->get(route('projects.testing.index', $project))
            ->assertOk()
            ->assertSee($suite->name);

        $this->actingAs($systemAdmin)
            ->post(route('projects.test-suites.store', $project), ['name' => 'Blocked'])
            ->assertForbidden();

        $this->assertDatabaseMissing('test_suites', ['name' => 'Blocked']);
    }

    public function test_system_admin_without_the_view_permission_is_forbidden(): void
    {
        $project = Project::factory()->create();
        $systemAdmin = $this->createSystemAdmin(withViewPermission: false);

        $this->actingAs($systemAdmin)
            ->get(route('projects.testing.index', $project))
            ->assertForbidden();
    }

    public function test_suite_can_be_updated_and_deleted_while_empty(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $owner = $project->owner;

        $this->actingAs($owner)
            ->patch(route('projects.test-suites.update', [$project, $suite]), [
                'name' => 'Renamed suite',
                'description' => 'Updated.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('test_suites', ['id' => $suite->id, 'name' => 'Renamed suite']);

        $this->actingAs($owner)
            ->delete(route('projects.test-suites.destroy', [$project, $suite]))
            ->assertRedirect();

        $this->assertDatabaseMissing('test_suites', ['id' => $suite->id]);
    }

    public function test_suite_delete_is_refused_while_it_holds_cases(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        TestCaseModel::factory()->create([
            'project_id' => $project->id,
            'test_suite_id' => $suite->id,
        ]);

        $this->actingAs($project->owner)
            ->delete(route('projects.test-suites.destroy', [$project, $suite]))
            ->assertSessionHasErrors('suite');

        $this->assertDatabaseHas('test_suites', ['id' => $suite->id]);
    }

    public function test_case_creation_assigns_a_per_project_key_and_ordered_steps(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $owner = $project->owner;

        $first = $this->createCase($owner, $project, $suite, 'First case', [
            ['action' => 'Open the page', 'expected_result' => 'Page loads'],
            ['action' => '', 'expected_result' => ''],
            ['action' => 'Submit the form', 'expected_result' => 'Saved'],
            ['action' => 'Check the log', 'expected_result' => ''],
        ]);

        $second = $this->createCase($owner, $project, $suite, 'Second case', []);

        $this->assertSame(1, $first->sequence);
        $this->assertSame('TC-1', $first->displayKey());
        $this->assertSame(2, $second->sequence);
        $this->assertSame('TC-2', $second->displayKey());

        $steps = $first->steps()->get();

        $this->assertCount(3, $steps);
        $this->assertSame([1, 2, 3], $steps->pluck('position')->all());
        $this->assertSame(
            ['Open the page', 'Submit the form', 'Check the log'],
            $steps->pluck('action')->all(),
        );
        $this->assertSame('Page loads', $steps[0]->expected_result);
        $this->assertSame('Saved', $steps[1]->expected_result);
        $this->assertNull($steps[2]->expected_result);
    }

    public function test_case_keys_are_scoped_per_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $first = $this->createCase(
            $firstProject->owner,
            $firstProject,
            TestSuite::factory()->create(['project_id' => $firstProject->id]),
            'First project case',
            [],
        );

        $second = $this->createCase(
            $secondProject->owner,
            $secondProject,
            TestSuite::factory()->create(['project_id' => $secondProject->id]),
            'Second project case',
            [],
        );

        $this->assertSame('TC-1', $first->displayKey());
        $this->assertSame('TC-1', $second->displayKey());
    }

    public function test_case_creation_is_audited(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);

        $case = $this->createCase($project->owner, $project, $suite, 'Audited case', []);

        $audit = AuditLog::query()
            ->where('action', 'test_case.created')
            ->where('actor_user_id', $project->owner_id)
            ->firstOrFail();

        $this->assertSame($case->id, $audit->after_values['test_case_id']);
        $this->assertSame('Audited case', $audit->after_values['title']);
        $this->assertSame(1, $audit->after_values['sequence']);
    }

    public function test_case_update_replaces_steps_and_is_audited(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $owner = $project->owner;

        $case = $this->createCase($owner, $project, $suite, 'Original', [
            ['action' => 'Old step', 'expected_result' => null],
        ]);

        $this->actingAs($owner)
            ->patch(route('projects.test-cases.update', [$project, $case]), [
                'title' => 'Updated',
                'description' => null,
                'status' => TestCaseModel::STATUS_ACTIVE,
                'severity' => TestCaseModel::SEVERITY_CRITICAL,
                'steps' => [
                    ['action' => 'New step one', 'expected_result' => 'One'],
                    ['action' => 'New step two', 'expected_result' => 'Two'],
                ],
            ])
            ->assertRedirect();

        $case->refresh();

        $this->assertSame('Updated', $case->title);
        $this->assertSame(TestCaseModel::STATUS_ACTIVE, $case->status);
        $this->assertSame(TestCaseModel::SEVERITY_CRITICAL, $case->severity);
        $this->assertSame(
            ['New step one', 'New step two'],
            $case->steps()->pluck('action')->all(),
        );

        $audit = AuditLog::query()
            ->where('action', 'test_case.updated')
            ->where('actor_user_id', $owner->id)
            ->firstOrFail();

        $this->assertSame('Original', $audit->before_values['title']);
        $this->assertSame('Updated', $audit->after_values['title']);
        $this->assertSame(1, $audit->before_values['steps']);
        $this->assertSame(2, $audit->after_values['steps']);
    }

    public function test_case_delete_removes_its_steps_and_is_audited(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);
        $owner = $project->owner;

        $case = $this->createCase($owner, $project, $suite, 'Disposable', [
            ['action' => 'Do a thing', 'expected_result' => null],
        ]);

        $this->actingAs($owner)
            ->delete(route('projects.test-cases.destroy', [$project, $case]))
            ->assertRedirect();

        $this->assertDatabaseMissing('test_cases', ['id' => $case->id]);
        $this->assertSame(0, TestCaseStep::query()->where('test_case_id', $case->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test_case.deleted',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_case_and_suite_bindings_are_scoped_to_the_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $firstProject->id]);
        $case = TestCaseModel::factory()->create([
            'project_id' => $firstProject->id,
            'test_suite_id' => $suite->id,
        ]);

        $this->actingAs($secondProject->owner)
            ->patch(route('projects.test-cases.update', [$secondProject, $case]), [
                'title' => 'Hijacked',
                'status' => TestCaseModel::STATUS_ACTIVE,
                'severity' => TestCaseModel::SEVERITY_MAJOR,
            ])
            ->assertNotFound();

        $this->actingAs($secondProject->owner)
            ->patch(route('projects.test-suites.update', [$secondProject, $suite]), ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_case_cannot_be_created_into_a_suite_from_another_project(): void
    {
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();
        $foreignSuite = TestSuite::factory()->create(['project_id' => $firstProject->id]);

        $this->actingAs($secondProject->owner)
            ->post(route('projects.test-cases.store', $secondProject), [
                'test_suite_id' => $foreignSuite->id,
                'title' => 'Cross project',
                'status' => TestCaseModel::STATUS_DRAFT,
                'severity' => TestCaseModel::SEVERITY_MAJOR,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('test_cases', ['title' => 'Cross project']);
    }

    public function test_case_status_and_severity_are_validated(): void
    {
        $project = Project::factory()->create();
        $suite = TestSuite::factory()->create(['project_id' => $project->id]);

        $this->actingAs($project->owner)
            ->post(route('projects.test-cases.store', $project), [
                'test_suite_id' => $suite->id,
                'title' => 'Invalid',
                'status' => 'unknown',
                'severity' => 'unknown',
            ])
            ->assertSessionHasErrors(['status', 'severity']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.testing.index', $project))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  list<array{action: string, expected_result: ?string}>  $steps
     */
    private function createCase(
        User $actor,
        Project $project,
        TestSuite $suite,
        string $title,
        array $steps,
    ): TestCaseModel {
        $this->actingAs($actor)
            ->post(route('projects.test-cases.store', $project), [
                'test_suite_id' => $suite->id,
                'title' => $title,
                'status' => TestCaseModel::STATUS_DRAFT,
                'severity' => TestCaseModel::SEVERITY_MAJOR,
                'steps' => $steps,
            ])
            ->assertRedirect();

        return TestCaseModel::query()
            ->where('project_id', $project->id)
            ->where('title', $title)
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

        $permissionId = Permission::query()->where('code', 'test_cases.view')->value('id');

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
