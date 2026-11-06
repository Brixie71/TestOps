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
     * Requires the controller to paginate with fewer than 16 results per page
     * (Laravel's default of 15 satisfies this — if an explicit larger
     * per-page is ever set, url(2) becomes null and this assertion fails on
     * the undefined key) and to call withQueryString() so filters survive.
     */
    public function test_pagination_preserves_search_and_status_filters(): void
    {
        $admin = $this->superAdmin();

        User::factory()->count(16)->create([
            'name' => 'Pagination Directory User',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('settings.users', [
                'search' => 'Pagination Directory',
                'status' => 'active',
            ]));

        $response->assertOk()
            ->assertViewHas('users', function ($users): bool {
                parse_str(
                    parse_url($users->url(2), PHP_URL_QUERY) ?? '',
                    $query
                );

                return $users->total() === 16
                    && $query['search'] === 'Pagination Directory'
                    && $query['status'] === 'active'
                    && $query['page'] === '2';
            });
    }
}
