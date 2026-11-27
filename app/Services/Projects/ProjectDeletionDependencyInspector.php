<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Finds the testing-data categories that block project deletion (§20).
 *
 * A project may not be deleted while any of these rows exist, so deleting a
 * project can never silently cascade its testing history. Mirrors
 * UserDeletionDependencyInspector: the deletion guard and any future operator
 * message share this list.
 */
class ProjectDeletionDependencyInspector
{
    /**
     * Blocking dependencies, keyed by category.
     *
     * @var array<string, array{table: string, column: string, label: string}>
     */
    private const BLOCKING_DEPENDENCIES = [
        'test_suites' => [
            'table' => 'test_suites',
            'column' => 'project_id',
            'label' => 'test suite',
        ],
        'test_cases' => [
            'table' => 'test_cases',
            'column' => 'project_id',
            'label' => 'test case',
        ],
        'test_runs' => [
            'table' => 'test_runs',
            'column' => 'project_id',
            'label' => 'test run',
        ],
        'defects' => [
            'table' => 'defects',
            'column' => 'project_id',
            'label' => 'defect',
        ],
    ];

    /**
     * Return every blocking category with its current row count.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function blockingDependencies(Project $project): array
    {
        $dependencies = [];

        foreach (self::BLOCKING_DEPENDENCIES as $key => $definition) {
            $count = DB::table($definition['table'])
                ->where($definition['column'], $project->getKey())
                ->count();

            if ($count === 0) {
                continue;
            }

            $dependencies[] = [
                'key' => $key,
                'label' => $definition['label'],
                'count' => $count,
            ];
        }

        return $dependencies;
    }

    /**
     * Whether any testing data currently blocks deletion.
     */
    public function hasBlockingDependencies(Project $project): bool
    {
        return $this->blockingDependencies($project) !== [];
    }
}
