<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailLinkExpirationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const VIEW_PERMISSION = 'settings.email_link_expiration.view';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'permission:'.self::VIEW_PERMISSION,
        ])->get('/_test/settings/email-link-expiration', function () {
            return response('View access granted');
        });

        Route::middleware([
            'web',
            'auth',
            'super.admin',
        ])->put('/_test/settings/email-link-expiration', function () {
            return response('Update access granted');
        });
    }

    public function test_system_admin_with_view_permission_can_access_setting(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->assignViewPermission($user->role);

        $this->actingAs($user)
            ->get('/_test/settings/email-link-expiration')
            ->assertOk()
            ->assertSeeText('View access granted');
    }

    public function test_system_admin_without_view_permission_is_denied(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->actingAs($user)
            ->get('/_test/settings/email-link-expiration')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_setting_without_permission_assignment(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $this->actingAs($user)
            ->get('/_test/settings/email-link-expiration')
            ->assertOk();
    }

    public function test_other_system_role_with_view_permission_can_access_setting(): void
    {
        $user = $this->createUserWithRole('QA_SUPERVISOR', 'system');

        $this->assignViewPermission($user->role);

        $this->actingAs($user)
            ->get('/_test/settings/email-link-expiration')
            ->assertOk();
    }

    public function test_project_role_with_view_permission_is_denied(): void
    {
        $user = $this->createUserWithRole('PROJECT_ADMIN', 'project');

        $this->assignViewPermission($user->role);

        $this->actingAs($user)
            ->get('/_test/settings/email-link-expiration')
            ->assertForbidden();
    }

    public function test_only_super_admin_can_update_setting(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN', 'system');

        $this->actingAs($user)
            ->put('/_test/settings/email-link-expiration')
            ->assertOk()
            ->assertSeeText('Update access granted');
    }

    public function test_system_admin_cannot_update_setting(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->actingAs($user)
            ->put('/_test/settings/email-link-expiration')
            ->assertForbidden();
    }

    public function test_guests_cannot_view_or_update_setting(): void
    {
        $this->get('/_test/settings/email-link-expiration')
            ->assertRedirect(route('login'));

        $this->put('/_test/settings/email-link-expiration')
            ->assertRedirect(route('login'));
    }

    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->create([
            'name' => $code,
            'code' => $code,
            'scope' => $scope,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function assignViewPermission(Role $role): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => self::VIEW_PERMISSION],
            [
                'name' => 'View Email Link Expiration Setting',
                'description' => 'Allows viewing the configured expiration duration for invitation and email-change links.',
            ],
        );

        $role->permissions()->attach($permission);
    }
}
