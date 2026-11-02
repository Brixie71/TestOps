<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $config = config('testops.super_admin');

        if (
            empty($config['name']) ||
            empty($config['email']) ||
            empty($config['password'])
        ) {
            throw new RuntimeException(
                'Super Admin credentials are missing from the environment configuration.'
            );
        }

        $role = Role::query()
            ->where('code', 'SUPER_ADMIN')
            ->where('scope', 'system')
            ->first();

        if ($role === null) {
            throw new RuntimeException(
                'The SUPER_ADMIN system role was not found. Run RoleSeeder first.'
            );
        }

        $user = User::query()->updateOrCreate(
            ['email' => $config['email']],
            [
                'name' => $config['name'],
                'password' => $config['password'],
            ]
        );

        $user->role()->associate($role);
        $user->save();
    }
}
