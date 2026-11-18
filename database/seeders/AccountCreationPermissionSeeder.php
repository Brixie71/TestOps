<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountCreationPermissionSeeder extends Seeder
{
    /**
     * Seed the account creation and invitation permissions.
     *
     * `settings.account_link_expiration.view` lets System Admins read the
     * three account-link expiration settings (updates remain Super Admin
     * only). `users.create` and `users.invitations.manage` govern issuing
     * account credentials and managing the invitation lifecycle.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()
                ->where('code', 'SYSTEM_ADMIN')
                ->where('scope', 'system')
                ->first();

            if ($role === null) {
                throw new RuntimeException(
                    'The SYSTEM_ADMIN system role was not found. Run RoleSeeder first.'
                );
            }

            $permissions = [
                Permission::firstOrCreate(
                    ['code' => 'settings.account_link_expiration.view'],
                    [
                        'name' => 'View Account Link Expiration Settings',
                        'description' => 'Allows viewing the configured expiration durations for invitation, recovery-code, and account email-verification links.',
                    ],
                ),
                Permission::firstOrCreate(
                    ['code' => 'users.create'],
                    [
                        'name' => 'Create User Accounts',
                        'description' => 'Allows creating new user accounts through invitations or direct account setup, with generated credentials never exposed to administrators.',
                    ],
                ),
                Permission::firstOrCreate(
                    ['code' => 'users.invitations.manage'],
                    [
                        'name' => 'Manage User Invitations',
                        'description' => 'Allows resending, cancelling, and recovering live user invitations.',
                    ],
                ),
            ];

            $role->permissions()->syncWithoutDetaching(
                collect($permissions)->pluck('id')->all(),
            );
        });
    }
}
