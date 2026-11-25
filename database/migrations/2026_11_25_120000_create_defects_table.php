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
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // A defect may point at the result that exposed it. The link is
            // optional and clears (never deletes the defect) if the result
            // disappears.
            $table->foreignId('test_result_id')->nullable()->constrained('test_results')->nullOnDelete();
            // Per-project display sequence; the DEF- key is derived from it.
            $table->unsignedInteger('sequence');
            $table->string('title');
            $table->text('description')->nullable();
            // Defect severity reuses the five-level testing scale.
            $table->string('severity', 20)->default('major');
            $table->string('status', 20)->default('open');
            // Authorship survives account deletion through the nullable link.
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('defects');
    }
};
