<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SystemSettingPermissionSeeder extends Seeder
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
                ['code' => 'settings.email_link_expiration.view'],
                [
                    'name' => 'View Email Link Expiration Setting',
                    'description' => 'Allows viewing the configured expiration duration for invitation and email-change links.',
                ],
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        });
    }
}
