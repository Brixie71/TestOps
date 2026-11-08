<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an authenticated Super Admin for directory access.
     *
     * Name, email, and account_status are pinned explicitly:
     * - The email contains no "0" so search=0 total counts remain exact.
     * - The status-filter assertions rely on the admin being 'active'.
     */
    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Admin User',
            'email' => 'admin@testops.dev',
            'account_status' => 'active',
        ]);
    }

    private function regularUser(): User
    {
        $role = Role::query()->create([
            'name' => 'Standard User',
            'code' => 'USER',
            'scope' => 'system',
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'account_status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.users'))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_access_user_directory(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('settings.users'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_user_directory_with_roles(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create([
            'name' => 'Directory Test User',
            'role_id' => $admin->role_id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users'));

        $response->assertOk()
            ->assertViewIs('settings.users')
            ->assertViewHas('users', function ($users) use ($user): bool {
                $directoryUser = $users->firstWhere('id', $user->id);

                return $directoryUser !== null
                    && $directoryUser->relationLoaded('role')
                    && $directoryUser->role?->code === 'SUPER_ADMIN';
            });
        $response->assertSee('name="search"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="role"', false)
            ->assertSee('name="department"', false)
            ->assertSee('name="sort"', false)
            ->assertSee('name="direction"', false)
            ->assertSee('Department')
            ->assertSee('System role')
            ->assertSee('Date created')
            ->assertSee('Search name, email, contact, job title...');
    }

    public function test_directory_can_search_by_name(): void
    {
        $admin = $this->superAdmin();

        $matchingUser = User::factory()->create([
            'name' => 'Unique Directory Person',
        ]);

        User::factory()->create([
            'name' => 'Unrelated Person',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', ['search' => 'unique directory']))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($matchingUser): bool {
                return $users->total() === 1
                    && $users->contains('id', $matchingUser->id);
            });
    }

    public function test_directory_can_search_by_email_case_insensitively(): void
    {
        $admin = $this->superAdmin();

        $matchingUser = User::factory()->create([
            'email' => 'directory-search@example.com',
        ]);

        User::factory()->create([
            'email' => 'unrelated@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', ['search' => 'DIRECTORY-SEARCH']))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($matchingUser): bool {
                return $users->total() === 1
                    && $users->contains('id', $matchingUser->id);
            });
    }

    /**
     * Regression guard for the "0 is falsy" defect. PHP treats the string
     * '0' as falsy, so a truthiness guard ($search) skips filtering entirely
     * while the view also hides the active-filter state. Both halves are
     * covered: the controller must apply the filter, and the view must
     * render the Clear control.
     */
    public function test_search_for_zero_is_treated_as_an_active_filter(): void
    {
        $admin = $this->superAdmin();

        $matchingUser = User::factory()->create([
            'name' => 'Agent Zero',
            'email' => 'user0@example.com',
        ]);

        $nonMatchingUser = User::factory()->create([
            'name' => 'Unrelated Person',
            'email' => 'plain@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users', ['search' => '0']))
            ->assertOk()
            // View half: '0' must register as an active filter so the Clear
            // control renders. Results are non-empty here, so this is the
            // only "Clear" on the page.
            ->assertSee('Clear');

        // Controller half: the paginator itself must contain only the match.
        $response->assertViewHas('users', function ($users) use ($matchingUser, $nonMatchingUser): bool {
            return $users->total() === 1
                && $users->contains('id', $matchingUser->id)
                && ! $users->contains('id', $nonMatchingUser->id);
        });
    }

    public function test_directory_can_search_all_profile_fields_case_insensitively(): void
    {
        $admin = $this->superAdmin();

        $cases = [
            ['contact_number', '09171234567', '0917123'],
            ['job_title', 'Senior Backend Engineer', 'BACKEND ENGINEER'],
            ['department', 'Quality Assurance', 'quality assurance'],
        ];

        foreach ($cases as [$field, $value, $search]) {
            $user = User::factory()->create([
                $field => $value,
            ]);

            $this->actingAs($admin)
                ->get(route('settings.users', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('users', function ($users) use ($user): bool {
                    return $users->total() === 1
                        && $users->contains('id', $user->id);
                });
        }
    }

    public function test_directory_can_filter_by_system_role_only(): void
    {
        $admin = $this->superAdmin();

        $systemRole = Role::query()->create([
            'name' => 'System Admin',
            'code' => 'SYSTEM_ADMIN',
            'scope' => 'system',
        ]);

        $projectRole = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $matchingUser = User::factory()->create([
            'role_id' => $systemRole->id,
        ]);

        User::factory()->create([
            'role_id' => $projectRole->id,
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', ['role' => $systemRole->id]))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($matchingUser): bool {
                return $users->total() === 1
                    && $users->contains('id', $matchingUser->id);
            })
            ->assertViewHas('systemRoles', function ($roles) use ($systemRole, $projectRole): bool {
                return $roles->contains('id', $systemRole->id)
                    && ! $roles->contains('id', $projectRole->id);
            });
    }

    public function test_directory_can_filter_by_department(): void
    {
        $admin = $this->superAdmin();

        $matchingUser = User::factory()->create([
            'department' => 'Quality Assurance',
        ]);

        User::factory()->create([
            'department' => 'Information Technology',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', ['department' => 'Quality Assurance']))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($matchingUser): bool {
                return $users->total() === 1
                    && $users->contains('id', $matchingUser->id);
            })
            ->assertViewHas('departments', function ($departments): bool {
                return $departments->contains('Quality Assurance')
                    && $departments->contains('Information Technology');
            });
    }

    public function test_directory_can_sort_by_email_descending(): void
    {
        $admin = $this->superAdmin();

        $first = User::factory()->create([
            'name' => 'First User',
            'email' => 'a-sort@example.com',
        ]);

        $last = User::factory()->create([
            'name' => 'Last User',
            'email' => 'z-sort@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', [
                'sort' => 'email',
                'direction' => 'desc',
            ]))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($last, $first): bool {
                return $users->first()->id === $last->id
                    && $users->last()->id === $first->id;
            });
    }

    public function test_directory_can_sort_by_system_role_name_in_both_directions(): void
    {
        $admin = $this->superAdmin();

        $systemAdminRole = Role::query()->create([
            'name' => 'System Admin',
            'code' => 'SYSTEM_ADMIN',
            'scope' => 'system',
        ]);

        $projectRole = Role::query()->create([
            'name' => 'Project Admin',
            'code' => 'PROJECT_ADMIN',
            'scope' => 'project',
        ]);

        $systemAdmin = User::factory()->create([
            'name' => 'System Admin User',
            'role_id' => $systemAdminRole->id,
        ]);

        $standardUser = $this->regularUser();

        $projectRoleUser = User::factory()->create([
            'name' => 'Project Role User',
            'role_id' => $projectRole->id,
        ]);

        $unassignedUser = User::factory()->create([
            'name' => 'Unassigned User',
            'role_id' => null,
        ]);

        // Ascending: system roles A-Z, followed by non-system roles.
        $ascendingResponse = $this->actingAs($admin)
            ->get(route('settings.users', [
                'sort' => 'role',
                'direction' => 'asc',
            ]));

        $ascendingResponse->assertOk()
            ->assertViewHas('users', function ($users) use (
                $admin,
                $standardUser,
                $systemAdmin,
                $projectRoleUser,
                $unassignedUser
            ): bool {
                $orderedIds = $users->getCollection()->pluck('id')->all();

                $standardPosition = array_search($standardUser->id, $orderedIds, true);
                $adminPosition = array_search($admin->id, $orderedIds, true);
                $systemAdminPosition = array_search($systemAdmin->id, $orderedIds, true);
                $projectRolePosition = array_search($projectRoleUser->id, $orderedIds, true);
                $unassignedPosition = array_search($unassignedUser->id, $orderedIds, true);

                return $standardPosition !== false
                    && $adminPosition !== false
                    && $systemAdminPosition !== false
                    && $projectRolePosition !== false
                    && $unassignedPosition !== false
                    && $standardPosition < $adminPosition
                    && $adminPosition < $systemAdminPosition
                    && $systemAdminPosition < $projectRolePosition
                    && $projectRolePosition < $unassignedPosition;
            });

        // Descending: system roles Z-A, followed by non-system roles.
        $descendingResponse = $this->get(route('settings.users', [
            'sort' => 'role',
            'direction' => 'desc',
        ]));

        $descendingResponse->assertOk()
            ->assertViewHas('users', function ($users) use (
                $admin,
                $standardUser,
                $systemAdmin,
                $projectRoleUser,
                $unassignedUser
            ): bool {
                $orderedIds = $users->getCollection()->pluck('id')->all();

                $systemAdminPosition = array_search($systemAdmin->id, $orderedIds, true);
                $adminPosition = array_search($admin->id, $orderedIds, true);
                $standardPosition = array_search($standardUser->id, $orderedIds, true);
                $projectRolePosition = array_search($projectRoleUser->id, $orderedIds, true);
                $unassignedPosition = array_search($unassignedUser->id, $orderedIds, true);

                return $systemAdminPosition !== false
                    && $adminPosition !== false
                    && $standardPosition !== false
                    && $projectRolePosition !== false
                    && $unassignedPosition !== false
                    && $systemAdminPosition < $adminPosition
                    && $adminPosition < $standardPosition
                    && $standardPosition < $projectRolePosition
                    && $projectRolePosition < $unassignedPosition;
            });
    }

    public function test_directory_falls_back_to_default_sort_for_invalid_sort_parameters(): void
    {
        $admin = $this->superAdmin();

        $alphabeticallyFirst = User::factory()->create([
            'name' => 'Alpha User',
        ]);

        User::factory()->create([
            'name' => 'Zulu User',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', [
                'sort' => 'password',
                'direction' => 'invalid',
            ]))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($admin, $alphabeticallyFirst): bool {
                return $users->first()->id === $admin->id
                    && $users->getCollection()->contains('id', $alphabeticallyFirst->id);
            });
    }

    public function test_directory_can_filter_by_account_status(): void
    {
        $admin = $this->superAdmin();

        $invitedUser = User::factory()->create([
            'account_status' => 'invited',
        ]);

        $activeUser = User::factory()->create([
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users', ['status' => 'invited']))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($invitedUser): bool {
                return $users->total() === 1
                    && $users->contains('id', $invitedUser->id);
            });

        $this->get(route('settings.users', ['status' => 'active']))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($activeUser): bool {
                return $users->total() === 2
                    && $users->contains('id', $activeUser->id)
                    && $users->every(
                        fn (User $user): bool => $user->account_status === 'active'
                    );
            });
    }

    public function test_soft_deleted_users_are_excluded_from_directory(): void
    {
        $admin = $this->superAdmin();

        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $activeUser = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('settings.users'))
            ->assertOk()
            ->assertViewHas('users', function ($users) use ($deletedUser, $activeUser): bool {
                return ! $users->contains('id', $deletedUser->id)
                    && $users->contains('id', $activeUser->id);
            });
    }

    /**
     * Verifies the directory uses 50 rows per page and preserves all active
     * filters and sorting parameters in pagination links.
     */
    public function test_pagination_uses_50_rows_and_preserves_all_filters(): void
    {
        $admin = $this->superAdmin();

        User::factory()->count(51)->create([
            'name' => 'Pagination Directory User',
            'account_status' => 'active',
            'department' => 'Engineering',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users', [
                'search' => 'Pagination Directory',
                'status' => 'active',
                'department' => 'Engineering',
                'sort' => 'email',
                'direction' => 'desc',
            ]));

        $response->assertOk()
            ->assertViewHas('users', function ($users): bool {
                parse_str(
                    parse_url($users->url(2), PHP_URL_QUERY) ?? '',
                    $query
                );

                return $users->total() === 51
                    && $users->perPage() === 50
                    && $users->count() === 50
                    && $query['search'] === 'Pagination Directory'
                    && $query['status'] === 'active'
                    && $query['department'] === 'Engineering'
                    && $query['sort'] === 'email'
                    && $query['direction'] === 'desc'
                    && $query['page'] === '2';
            });
    }
}
