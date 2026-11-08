<?php

namespace Tests\Feature\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_can_be_persisted_without_an_owner(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => $project->name,
            'owner_id' => null,
            'owner_assignment_type' => null,
        ]);

        $this->assertNull($project->owner);
        $this->assertNull($project->owner_assignment_type);
    }

    public function test_project_owner_defaults_to_permanent_assignment(): void
    {
        $project = Project::factory()->create();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'owner_id' => $project->owner_id,
            'owner_assignment_type' => 'permanent',
        ]);

        $this->assertSame('permanent', $project->owner_assignment_type);
    }

    public function test_temporary_ownership_can_be_represented_through_the_factory(): void
    {
        $project = Project::factory()->withTemporaryOwner()->create();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'owner_id' => $project->owner_id,
            'owner_assignment_type' => 'temporary',
        ]);

        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertSame('temporary', $project->owner_assignment_type);
    }

    public function test_automatic_assignment_does_not_imply_temporary_assignment(): void
    {
        $project = Project::factory()->withAutoAssignment()->create();

        $this->assertTrue($project->auto_assign_representative_owner);
        $this->assertNotNull($project->owner_id);
        $this->assertSame('permanent', $project->owner_assignment_type);

        $unassigned = Project::factory()->withoutOwner()->withAutoAssignment()->create();

        $this->assertTrue($unassigned->auto_assign_representative_owner);
        $this->assertNull($unassigned->owner_id);
        $this->assertNull($unassigned->owner_assignment_type);
    }

    public function test_owner_assignment_type_cannot_be_assigned_through_mass_assignment(): void
    {
        $project = Project::create([
            'name' => 'Blocked Type Assignment',
            'code' => 'BLOCKED-003',
            'owner_assignment_type' => 'temporary',
        ]);

        $this->assertNull($project->fresh()->owner_assignment_type);
        $this->assertNull($project->fresh()->owner_id);
    }

    public function test_owner_assignment_type_column_exists_and_is_optional(): void
    {
        $typeColumn = collect(Schema::getColumns('projects'))
            ->firstWhere('name', 'owner_assignment_type');

        $this->assertNotNull($typeColumn);
        $this->assertTrue((bool) $typeColumn['nullable']);
    }

    public function test_owner_set_with_null_assignment_type_is_rejected_by_the_database(): void
    {
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')->insert([
            'name' => 'Missing Assignment Type',
            'code' => 'INVALID-001',
            'owner_id' => $owner->id,
            'owner_assignment_type' => null,
        ]);
    }

    public function test_owner_set_with_invalid_assignment_type_is_rejected_by_the_database(): void
    {
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')->insert([
            'name' => 'Invalid Assignment Type',
            'code' => 'INVALID-002',
            'owner_id' => $owner->id,
            'owner_assignment_type' => 'expired',
        ]);
    }

    public function test_unassigned_owner_with_assignment_type_is_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('projects')->insert([
            'name' => 'Type Without Owner',
            'code' => 'INVALID-003',
            'owner_id' => null,
            'owner_assignment_type' => 'permanent',
        ]);
    }

    public function test_owner_id_cannot_be_assigned_through_mass_assignment(): void
    {
        $assignee = User::factory()->create();

        $project = Project::create([
            'name' => 'Blocked Owner Assignment',
            'code' => 'BLOCKED-001',
            'owner_id' => $assignee->id,
        ]);

        $this->assertNull($project->fresh()->owner_id);
        $this->assertNull($project->fresh()->owner);
    }

    public function test_auto_assign_representative_owner_cannot_be_assigned_through_mass_assignment(): void
    {
        $project = Project::create([
            'name' => 'Blocked Setting Assignment',
            'code' => 'BLOCKED-002',
            'auto_assign_representative_owner' => true,
        ]);

        $this->assertFalse($project->fresh()->auto_assign_representative_owner);
    }

    public function test_projects_can_be_created_through_mass_assignment_without_an_owner(): void
    {
        $project = Project::create([
            'name' => 'Unassigned Project',
            'code' => 'UNASSIGNED-001',
            'description' => null,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Unassigned Project',
            'code' => 'UNASSIGNED-001',
            'owner_id' => null,
            'auto_assign_representative_owner' => false,
        ]);
    }

    public function test_existing_project_ownership_remains_functional(): void
    {
        $project = Project::factory()->create();

        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertSame($project->owner_id, $project->owner->id);

        $project->owner_id = $project->owner->id;
        $project->save();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'owner_id' => $project->owner->id,
        ]);
    }

    public function test_automatic_assignment_defaults_to_disabled(): void
    {
        $project = Project::factory()->create();

        $this->assertFalse($project->auto_assign_representative_owner);

        $enabled = Project::factory()->withAutoAssignment()->create();

        $this->assertTrue($enabled->auto_assign_representative_owner);

        $this->assertDatabaseHas('projects', [
            'id' => $enabled->id,
            'auto_assign_representative_owner' => true,
        ]);
    }

    public function test_owner_id_column_became_nullable_and_kept_the_users_foreign_key(): void
    {
        $ownerColumn = collect(Schema::getColumns('projects'))
            ->firstWhere('name', 'owner_id');

        $this->assertNotNull($ownerColumn);
        $this->assertTrue((bool) $ownerColumn['nullable']);

        $foreignKeys = collect(Schema::getForeignKeys('projects'))
            ->first(fn (array $foreignKey) => in_array('owner_id', $foreignKey['columns'], true));

        $this->assertNotNull($foreignKeys);
        $this->assertSame('users', $foreignKeys['foreign_table']);
        $this->assertSame('restrict', strtolower((string) $foreignKeys['on_delete']));
    }

    public function test_permanently_deleting_an_owner_is_restricted_by_the_foreign_key(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->expectException(QueryException::class);

        $owner->forceDelete();
    }

    public function test_owner_assignment_type_cannot_be_cleared_by_a_direct_database_update(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')
            ->where('id', $project->id)
            ->update(['owner_assignment_type' => null]);
    }

    public function test_owner_assignment_type_cannot_be_changed_to_an_invalid_value_by_a_direct_database_update(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')
            ->where('id', $project->id)
            ->update(['owner_assignment_type' => 'expired']);
    }

    public function test_assignment_type_cannot_be_added_to_an_unassigned_project_by_a_direct_database_update(): void
    {
        $project = Project::factory()->withoutOwner()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')
            ->where('id', $project->id)
            ->update(['owner_assignment_type' => 'permanent']);
    }
}
