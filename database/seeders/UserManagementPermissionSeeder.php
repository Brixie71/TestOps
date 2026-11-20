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

            $roleAssignment = Permission::firstOrCreate(
                ['code' => 'users.roles.assign'],
                [
                    'name' => 'Assign User System Roles',
                    'description' => 'Allows assigning eligible existing system roles to user accounts, subject to the approved System Admin restrictions.',
                ],
            );

            $auditAccess = Permission::firstOrCreate(
                ['code' => 'audit.view'],
                [
                    'name' => 'View Audit Logs',
                    'description' => 'Allows viewing, filtering, and exporting the audit log, subject to the approved System Admin restrictions.',
                ],
            );

            $notificationHistory = Permission::firstOrCreate(
                ['code' => 'notifications.view'],
                [
                    'name' => 'View Email Delivery History',
                    'description' => 'Allows viewing the email delivery history and retrying eligible failed deliveries, subject to the approved System Admin restrictions.',
                ],
            );

            $passwordManagement = Permission::firstOrCreate(
                ['code' => 'users.password.manage'],
                [
                    'name' => 'Manage User Passwords',
                    'description' => 'Allows administrative password management — manual sets and forced email-link resets — subject to the approved System Admin restrictions.',
                ],
            );

            $emailManagement = Permission::firstOrCreate(
                ['code' => 'users.email.manage'],
                [
                    'name' => 'Manage User Email Changes',
                    'description' => 'Allows cancelling another account\'s pending email change, subject to the approved System Admin restrictions.',
                ],
            );

            $profileManagement = Permission::firstOrCreate(
                ['code' => 'users.profile.manage'],
                [
                    'name' => 'Edit User Profiles',
                    'description' => 'Allows editing another account\'s name, contact number, job title, and department, subject to the approved System Admin restrictions.',
                ],
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
                $roleAssignment->id,
                $auditAccess->id,
                $notificationHistory->id,
                $passwordManagement->id,
                $emailManagement->id,
                $profileManagement->id,
            ]);
        });
    }
}
