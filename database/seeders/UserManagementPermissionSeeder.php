<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserManagementPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
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

            $permission = Permission::firstOrCreate(
                ['code' => 'users.deactivate'],
                [
                    'name' => 'Deactivate User Accounts',
                    'description' => 'Allows deactivating and reactivating eligible user accounts, subject to the approved System Admin restrictions.',
                ],
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        });
    }
}
