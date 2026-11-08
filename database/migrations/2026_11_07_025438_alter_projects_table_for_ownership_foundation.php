<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL named CHECK constraint enforcing the owner/type pairing.
     */
    private const CHECK_CONSTRAINT = 'projects_owner_assignment_check';

    /**
     * SQLite cannot add CHECK constraints through ALTER TABLE, so the
     * identical rule is enforced with before-insert/before-update triggers
     * created after the owner_id change() rebuild (a rebuild silently drops
     * them; ownership tests keep verifying the rejection behavior).
     *
     * @var array<string, string>
     */
    private const SQLITE_TRIGGERS = [
        'projects_owner_assignment_insert_check' => 'BEFORE INSERT ON projects',
        'projects_owner_assignment_update_check' => 'BEFORE UPDATE ON projects',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Ownership becomes optional so projects can be unassigned.
            // The foreign key and its restrictive delete behavior are
            // preserved: SQLite replays them during the table rebuild
            // and PostgreSQL only alters nullability.
            $table->foreignId('owner_id')->nullable()->change();

            // Assignment type of the designated owner: permanent,
            // temporary (never expiring), or NULL when unassigned.
            $table->string('owner_assignment_type', 20)->nullable();

            // Per-project automatic Representative Admin assignment.
            $table->boolean('auto_assign_representative_owner')->default(false);
        });

        // Approved mapping: existing rows with an owner are permanent.
        DB::table('projects')
            ->whereNotNull('owner_id')
            ->update(['owner_assignment_type' => 'permanent']);

        $this->addOwnerAssignmentConstraint();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropOwnerAssignmentConstraint();

        Schema::table('projects', function (Blueprint $table) {
            // Unassigned projects must be resolved before reverting
            // ownership to the earlier non-nullable schema.
            $table->dropColumn(['owner_assignment_type', 'auto_assign_representative_owner']);

            $table->foreignId('owner_id')->nullable(false)->change();
        });
    }

    /**
     * Enforce: owner_id NULL requires owner_assignment_type NULL; a
     * non-null owner_id requires 'permanent' or 'temporary'.
     */
    private function addOwnerAssignmentConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (self::SQLITE_TRIGGERS as $name => $timing) {
                DB::unprepared(
                    "CREATE TRIGGER {$name} {$timing} FOR EACH ROW WHEN\n".
                    "((NEW.owner_id IS NULL AND NEW.owner_assignment_type IS NOT NULL) OR\n".
                    "(NEW.owner_id IS NOT NULL AND (NEW.owner_assignment_type IS NULL OR\n".
                    "CAST(NEW.owner_assignment_type AS TEXT) NOT IN ('permanent', 'temporary'))))\n".
                    "BEGIN\n".
                    "SELECT RAISE(ABORT, 'projects.owner_id and owner_assignment_type must both be NULL,' ||\n".
                    "' or the owner requires a permanent or temporary assignment type');\n".
                    "END;\n"
                );
            }

            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE projects ADD CONSTRAINT %s CHECK ('.
            '(owner_id IS NULL AND owner_assignment_type IS NULL) OR '.
            "(owner_id IS NOT NULL AND owner_assignment_type IN ('permanent', 'temporary')))",
            self::CHECK_CONSTRAINT,
        ));
    }

    /**
     * Reverse the owner/type pairing enforcement.
     */
    private function dropOwnerAssignmentConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (self::SQLITE_TRIGGERS as $name => $timing) {
                DB::statement("DROP TRIGGER IF EXISTS {$name}");
            }

            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE projects DROP CONSTRAINT IF EXISTS %s',
            self::CHECK_CONSTRAINT,
        ));
    }
};
