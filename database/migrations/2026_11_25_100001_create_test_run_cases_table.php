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
        Schema::create('test_run_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_run_id')->constrained()->cascadeOnDelete();
            // A case referenced by a run is never deleted out from under its
            // frozen snapshot: deletion is refused by the application and
            // enforced here by the restrictive foreign key.
            $table->foreignId('test_case_id')->constrained('test_cases')->restrictOnDelete();
            $table->unsignedInteger('position');
            // Frozen authoring snapshot: later case edits never rewrite a run.
            $table->string('title');
            $table->string('severity', 20);
            $table->json('steps');
            $table->timestamps();

            $table->unique(['test_run_id', 'test_case_id']);
            $table->index(['test_run_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_run_cases');
    }
};
