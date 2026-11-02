<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['code' => 'SUPER_ADMIN'],
            [
                'name' => 'Super Admin',
                'description' => 'System-wide administrator with full access.',
                'scope' => 'system',
            ]
        );
    }
}
