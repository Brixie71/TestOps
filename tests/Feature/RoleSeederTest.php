<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_all_canonical_system_roles(): void
    {
        (new RoleSeeder())->run();

        $this->assertDatabaseCount('roles', 2);

        $this->assertDatabaseHas('roles', [
            'code' => 'SUPER_ADMIN',
            'name' => 'Super Admin',
            'description' => 'System-wide administrator with full access.',
            'scope' => 'system',
        ]);

        $this->assertDatabaseHas('roles', [
            'code' => 'SYSTEM_ADMIN',
            'name' => 'System Admin',
            'description' => 'System administrator with administrative access subject to defined restrictions.',
            'scope' => 'system',
        ]);
    }

    public function test_it_is_idempotent_when_executed_repeatedly(): void
    {
        $seeder = new RoleSeeder();

        $seeder->run();

        $originalIds = Role::query()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        $seeder->run();

        $currentIds = Role::query()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        $this->assertDatabaseCount('roles', 2);
        $this->assertSame($originalIds, $currentIds);
    }

    public function test_it_corrects_metadata_of_existing_system_roles(): void
    {
        Role::query()->create([
            'name' => 'Incorrect Super Admin',
            'code' => 'SUPER_ADMIN',
            'description' => 'Incorrect description.',
            'scope' => 'system',
        ]);

        Role::query()->create([
            'name' => 'Incorrect System Admin',
            'code' => 'SYSTEM_ADMIN',
            'description' => 'Incorrect description.',
            'scope' => 'system',
        ]);

        $originalIds = Role::query()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        (new RoleSeeder())->run();

        $this->assertDatabaseCount('roles', 2);

        $this->assertDatabaseHas('roles', [
            'code' => 'SUPER_ADMIN',
            'name' => 'Super Admin',
            'description' => 'System-wide administrator with full access.',
            'scope' => 'system',
        ]);

        $this->assertDatabaseHas('roles', [
            'code' => 'SYSTEM_ADMIN',
            'name' => 'System Admin',
            'description' => 'System administrator with administrative access subject to defined restrictions.',
            'scope' => 'system',
        ]);

        $currentIds = Role::query()
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();

        $this->assertSame($originalIds, $currentIds);
    }

    public function test_it_rejects_scope_conflicts_and_rolls_back_the_transaction(): void
    {
        $conflictingRole = Role::query()->create([
            'name' => 'Project System Admin',
            'code' => 'SYSTEM_ADMIN',
            'description' => 'Project-scoped role using a reserved system code.',
            'scope' => 'project',
        ]);

        $exception = null;

        try {
            (new RoleSeeder())->run();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('SYSTEM_ADMIN', $exception->getMessage());

        // SUPER_ADMIN was processed first but must be rolled back.
        $this->assertDatabaseMissing('roles', [
            'code' => 'SUPER_ADMIN',
        ]);

        $this->assertDatabaseCount('roles', 1);

        $this->assertDatabaseHas('roles', [
            'id' => $conflictingRole->id,
            'name' => 'Project System Admin',
            'code' => 'SYSTEM_ADMIN',
            'description' => 'Project-scoped role using a reserved system code.',
            'scope' => 'project',
        ]);
    }

    public function test_it_does_not_modify_existing_project_scoped_roles(): void
    {
        $projectAdmin = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'description' => 'Project administrator.',
            'scope' => 'project',
        ]);

        $projectViewer = Role::query()->create([
            'name' => 'Project Viewer',
            'code' => 'PROJECT_VIEWER',
            'description' => 'Read-only project member.',
            'scope' => 'project',
        ]);

        (new RoleSeeder())->run();

        $this->assertDatabaseHas('roles', [
            'id' => $projectAdmin->id,
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'description' => 'Project administrator.',
            'scope' => 'project',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $projectViewer->id,
            'name' => 'Project Viewer',
            'description' => 'Read-only project member.',
            'code' => 'PROJECT_VIEWER',
            'scope' => 'project',
        ]);

        $this->assertDatabaseCount('roles', 4);
    }
}
