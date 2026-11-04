<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoleSeeder extends Seeder
{
    /**
     * Canonical system roles managed by this seeder.
     *
     * Project-scoped roles are intentionally excluded.
     *
     * @var array<string, array{name: string, description: string, scope: string}>
     */
    private const SYSTEM_ROLES = [
        'SUPER_ADMIN' => [
            'name' => 'Super Admin',
            'description' => 'System-wide administrator with full access.',
            'scope' => 'system',
        ],
        'SYSTEM_ADMIN' => [
            'name' => 'System Admin',
            'description' => 'System administrator with administrative access subject to defined restrictions.',
            'scope' => 'system',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::SYSTEM_ROLES as $code => $attributes) {
                $role = Role::query()
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if ($role !== null && $role->scope !== 'system') {
                    throw new RuntimeException(
                        "Cannot seed the {$code} system role because that code is already assigned to a non-system role."
                    );
                }

                if ($role === null) {
                    Role::query()->create([
                        'code' => $code,
                        ...$attributes,
                    ]);

                    continue;
                }

                $role->update($attributes);
            }
        });
    }
}
