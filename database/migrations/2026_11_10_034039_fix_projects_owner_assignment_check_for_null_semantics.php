<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL named CHECK constraint enforcing the owner/type pairing.
     */
    private const CHECK_CONSTRAINT = 'projects_owner_assignment_check';

    /**
     * Run the migrations.
     *
     * The original constraint let PostgreSQL accept an owner without an
     * assignment type: with owner_assignment_type NULL the second branch
     * of the check evaluated to NULL, and a CHECK that evaluates to
     * UNKNOWN is treated as satisfied by PostgreSQL. SQLite was
     * unaffected because its triggers reject NULL assignment types
     * explicitly.
     *
     * Re-create the constraint requiring a non-NULL assignment type for
     * every assigned owner: (NULL, NULL), (owner, permanent), and
     * (owner, temporary) are the only valid pairings.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite keeps its trigger-based enforcement from the original
            // migration, which already rejects this case.
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE projects DROP CONSTRAINT IF EXISTS %s',
            self::CHECK_CONSTRAINT,
        ));

        DB::statement(sprintf(
            'ALTER TABLE projects ADD CONSTRAINT %s CHECK ('.
            '(owner_id IS NULL AND owner_assignment_type IS NULL) OR '.
            '(owner_id IS NOT NULL AND owner_assignment_type IS NOT NULL AND '.
            "owner_assignment_type IN ('permanent', 'temporary')))",
            self::CHECK_CONSTRAINT,
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE projects DROP CONSTRAINT IF EXISTS %s',
            self::CHECK_CONSTRAINT,
        ));

        // Restore the original (looser) definition from
        // 2026_11_07_025438_alter_projects_table_for_ownership_foundation.
        DB::statement(sprintf(
            'ALTER TABLE projects ADD CONSTRAINT %s CHECK ('.
            '(owner_id IS NULL AND owner_assignment_type IS NULL) OR '.
            "(owner_id IS NOT NULL AND owner_assignment_type IN ('permanent', 'temporary')))",
            self::CHECK_CONSTRAINT,
        ));
    }
};
