<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()
                ->where('code', 'SUPER_ADMIN')
                ->where('scope', 'system')
                ->first();

            if ($role === null) {
                throw new RuntimeException(
                    'The SUPER_ADMIN system role was not found. Run RoleSeeder first.'
                );
            }

            $existingAdmins = User::query()
                ->where('role_id', $role->id)
                ->lockForUpdate()
                ->get();

            if ($existingAdmins->count() > 1) {
                throw new RuntimeException(
                    'Multiple SUPER_ADMIN accounts exist. Manual review is required.'
                );
            }

            if ($existingAdmins->count() === 1) {
                $this->command?->info(
                    'Existing SUPER_ADMIN account verified. No account changes were made.'
                );

                return;
            }

            $this->command?->warn(
                'No SUPER_ADMIN account exists. Automatic provisioning is disabled.'
            );
        });
    }
}
