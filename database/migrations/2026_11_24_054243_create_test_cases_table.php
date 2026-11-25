<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // A suite keeps its cases: deletion is refused while any case
            // still references it (the approved restrictive policy).
            $table->foreignId('test_suite_id')->constrained('test_suites')->restrictOnDelete();
            // Per-project display sequence; the TC- key is derived from it.
            $table->unsignedInteger('sequence');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            // Case severity is snapshotted into a run when it starts, so it
            // lives on the case and uses the same five-level scale.
            $table->string('severity', 20)->default('major');
            // Authorship survives account deletion through the nullable link.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'sequence']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
