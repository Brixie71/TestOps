<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnsurePermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            EnsurePermission::class.':projects.view',
        ])->get('/_test/permission', function () {
            return response()->json([
                'message' => 'Access granted',
            ]);
        });
    }

    public function test_authorized_user_can_access_protected_route(): void
    {
        [$user, $permission] = $this->createAuthorizedUser();

        $this->actingAs($user);

        $response = $this->getJson('/_test/permission');

        $response->assertOk()
            ->assertJson(['message' => 'Access granted']);
    }

    public function test_user_without_permission_receives_forbidden_response(): void
    {
        $role = $this->createSystemRole();

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/permission');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_protected_route_without_permission_assignment(): void
    {
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/permission');

        $response->assertOk()
            ->assertJson(['message' => 'Access granted']);
    }

    public function test_unauthenticated_user_is_denied_access(): void
    {
        $response = $this->get('/_test/permission');

        $response->assertRedirect(route('login'));
    }

    public function test_project_role_cannot_access_system_protected_route(): void
    {
        $role = Role::query()->create([
            'name' => 'Project Administrator',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/_test/permission');

        $response->assertForbidden();
    }

    private function createAuthorizedUser(): array
    {
        $role = $this->createSystemRole();

        $permission = Permission::query()->create([
            'name' => 'View Projects',
            'code' => 'projects.view',
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        return [$user, $permission];
    }

    private function createSystemRole(): Role
    {
        return Role::query()->create([
            'name' => 'QA Supervisor',
            'code' => 'QA_SUPERVISOR',
            'scope' => 'system',
        ]);
    }
}
