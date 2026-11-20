<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Finds the record categories that block permanent account deletion (§7.3).
 *
 * A category is blocking when an existing foreign key uses restrictive
 * deletion behaviour, so the account cannot be force-deleted while rows
 * remain. Only categories that exist in the current schema are listed; the
 * approved specification requires this list to be revisited once the
 * remaining domain modules exist (§20, deletion dependencies).
 */
class UserDeletionDependencyInspector
{
    /**
     * Restrictive dependencies, keyed by category.
     *
     * @var array<string, array{table: string, column: string, label: string, resolution: string}>
     */
    private const BLOCKING_DEPENDENCIES = [
        'owned_projects' => [
            'table' => 'projects',
            'column' => 'owner_id',
            'label' => 'Projects owned',
            'resolution' => 'Transfer ownership of these projects before permanent deletion.',
        ],
    ];

    /**
     * Return every blocking category with its current row count.
     *
     * @return array<int, array{key: string, label: string, count: int, resolution: string}>
     */
    public function blockingDependencies(User $user): array
    {
        $dependencies = [];

        foreach (self::BLOCKING_DEPENDENCIES as $key => $definition) {
            $count = DB::table($definition['table'])
                ->where($definition['column'], $user->getKey())
                ->count();

            if ($count === 0) {
                continue;
            }

            $dependencies[] = [
                'key' => $key,
                'label' => $definition['label'],
                'count' => $count,
                'resolution' => $definition['resolution'],
            ];
        }

        return $dependencies;
    }

    /**
     * Whether any restrictive dependency currently blocks deletion.
     */
    public function hasBlockingDependencies(User $user): bool
    {
        return $this->blockingDependencies($user) !== [];
    }
}
